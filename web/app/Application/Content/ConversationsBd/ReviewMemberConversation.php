<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Application\Admin\Content\PublicContentCache;
use App\Application\Community\UniqueSlug;
use App\Domain\Content\Exceptions\BdConversationRefused;
use App\Enums\BdConversationStatus;
use App\Models\BdConversation;
use App\Models\User;
use App\Notifications\TeamNotice;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * File de modération des conversations en BD proposées par les membres (P20-08, PRD P0-7 ; `/admin/moderation/
 * conversations-bd`, permission `moderation.manage` rejouée par l'écran à chaque action) :
 *  - publier : la grammaire est revérifiée, slug lisible et unique d'après le titre (l'adresse provisoire du brouillon
 *    n'est jamais servie), statut `publiee` → page publique, liste et plan du site (cache oublié) ;
 *  - refuser : motif obligatoire écrit pour l'auteur·rice (≤ 500), statut `refusee` ; rien n'est public, la personne
 *    corrige puis repropose ;
 *  - retirer après un signalement (ContentVisibility) et rendre visible de nouveau une création retirée par la modération.
 * Jamais sa propre création (comme les vidéos). L'auteur·rice est notifié·e (TeamNotice, cloche et push) ; journal
 * `conversations_bd.member_published|member_refused` (titre seulement). Retrait et remise en ligne depuis un signalement :
 * journalisés par DecideReport (`moderation.hidden|unhidden|approved`), auteur·rice prévenu·e par ModerationNotice ;
 * `member_hidden|member_restored` seulement quand une personne de l'équipe est passée en argument.
 */
final class ReviewMemberConversation
{
    public const REASON_MAX = 500;

    public function __construct(
        private readonly MemberConversations $conversations,
        private readonly ConversationGrammar $grammar,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @throws BdConversationRefused
     */
    public function publish(BdConversation $conversation, User $actor): BdConversation
    {
        $this->assertPending($conversation, $actor);
        $errors = ConversationGrammar::errors($this->grammar->check(['panels' => $this->conversations->grammarPanels($conversation)]));

        if ($errors !== []) {
            throw new BdConversationRefused('bd_invalid', [], $errors, ['message' => $errors[0]['message']]);
        }

        $slug = str_starts_with((string) $conversation->slug, 'brouillon-')
            ? UniqueSlug::for(BdConversation::class, (string) $conversation->title, 110)
            : (string) $conversation->slug;

        $conversation->forceFill([
            'slug' => $slug,
            // Après les modèles dans la liste publique (les plus récentes à la fin).
            'position' => min(65535, (int) BdConversation::query()->max('position') + 1),
            'status' => BdConversationStatus::Published->value,
            'published_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $actor->getKey(),
            'refusal_reason' => null,
            'withdrawn_at' => null,
            'withdrawn_by' => null,
        ])->save();

        Audit::log('conversations_bd.member_published', $conversation, $conversation->title, $actor);
        $this->cache->flush();
        $this->notify($conversation, new TeamNotice(TeamNotice::BD_PUBLISHED, (string) $conversation->title, null, route('conversations-bd.show', ['slug' => $slug])));

        return $conversation;
    }

    /**
     * @throws BdConversationRefused
     */
    public function refuse(BdConversation $conversation, string $reason, User $actor): BdConversation
    {
        $this->assertPending($conversation, $actor);
        $reason = trim($reason);

        if ($reason === '') {
            throw new BdConversationRefused('bd_reason_required', ['reason' => [(string) __('conversations_bd.errors.bd_reason_required')]]);
        }

        $conversation->forceFill([
            'status' => BdConversationStatus::Refused->value,
            'refusal_reason' => mb_substr($reason, 0, self::REASON_MAX),
            'reviewed_at' => now(),
            'reviewed_by' => $actor->getKey(),
        ])->save();

        Audit::log('conversations_bd.member_refused', $conversation, $conversation->title, $actor);
        $this->notify($conversation, new TeamNotice(TeamNotice::BD_REFUSED, (string) $conversation->title, (string) $conversation->refusal_reason, route('mes-conversations-bd.edit', ['ulid' => $conversation->ulid])));

        return $conversation;
    }

    /** Retrait par la modération (signalement traité, ContentVisibility) ; vrai si quelque chose a changé. */
    public function hide(BdConversation $conversation, ?User $actor, ?string $reason = null): bool
    {
        if (! $conversation->isPublished()) {
            return false;
        }

        $conversation->forceFill([
            'status' => BdConversationStatus::Withdrawn->value,
            'withdrawn_at' => now(),
            // Le système (masquage automatique) n'a pas de compte : 0 marque un retrait qui n'est pas celui de l'auteur·rice.
            'withdrawn_by' => $actor?->getKey() ?? 0,
            'refusal_reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, self::REASON_MAX) : null,
        ])->save();

        if ($actor !== null) {
            Audit::log('conversations_bd.member_hidden', $conversation, $conversation->title, $actor);
        }

        // L'auteur·rice est prévenu·e par la décision de modération (ModerationNotice « contenu masqué », DecideReport).
        $this->cache->flush();

        return true;
    }

    /** Rend visible une création retirée par la modération (jamais une création retirée par son auteur·rice). */
    public function restore(BdConversation $conversation, ?User $actor): bool
    {
        if (! $conversation->isWithdrawnByModeration()) {
            return false;
        }

        $conversation->forceFill([
            'status' => BdConversationStatus::Published->value,
            'withdrawn_at' => null,
            'withdrawn_by' => null,
            'refusal_reason' => null,
        ])->save();

        if ($actor !== null) {
            Audit::log('conversations_bd.member_restored', $conversation, $conversation->title, $actor);
        }

        $this->cache->flush();

        return true;
    }

    /**
     * @throws BdConversationRefused
     */
    private function assertPending(BdConversation $conversation, User $actor): void
    {
        $conversation->refresh();

        if (! $conversation->isMemberCreation() || $conversation->statusValue() !== BdConversationStatus::Submitted) {
            throw new BdConversationRefused('bd_not_pending');
        }

        if ($conversation->isAuthoredBy($actor)) {
            throw new BdConversationRefused('bd_own');
        }
    }

    private function notify(BdConversation $conversation, TeamNotice $notice): void
    {
        $author = $conversation->author_id !== null ? User::query()->find($conversation->author_id) : null;

        if ($author === null || ! $author->isActive()) {
            return;
        }

        try {
            $author->notify($notice);
        } catch (Throwable $exception) {
            Log::warning('Conversations en BD : notification non envoyée.', ['conversation_id' => $conversation->getKey(), 'error' => $exception->getMessage()]);
        }
    }
}
