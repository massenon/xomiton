<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use App\Application\Messaging\ConversationView;
use App\Models\ConversationParticipant;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Conversation vue par une personne (P8-01, docs/API.md §12.3) : identifiant ULID (ADR-27), nom affiché (l'autre
 * personne d'une conversation privée, sinon le titre ou les premiers pseudonymes), participant·es présent·es (`show`
 * seulement : la liste sondée les omet, attribut COMPACT),
 * dernier message (aperçu déchiffré à la lecture, `null` + `preview_hidden` en mode discret), non lus, sourdine et
 * droits (`can_send`, `can_manage`, `blocked_by_me`). Un blocage **par l'autre personne** n'est jamais dit : seul
 * `can_send = false` (écran : « Cette conversation n'est plus disponible. »).
 *
 * @mixin ConversationView
 */
final class ConversationResource extends JsonResource
{
    /** Pseudonymes repris dans le nom d'un groupe sans titre. */
    private const GROUP_NAME_PSEUDOS = 3;

    /** Attribut de requête : liste (sondée) sans le tableau `participants` (seul `show` le rend). */
    public const COMPACT = 'messaging.compact';

    /** Réponse de liste : `participants` omis (jusqu'à 50 par conversation), `participants_count` et `other` gardés. */
    public static function compact(): void
    {
        request()->attributes->set(self::COMPACT, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ConversationView $view */
        $view = $this->resource;
        $conversation = $view->conversation;
        $others = $view->participants->filter(fn (ConversationParticipant $p) => (int) $p->user_id !== (int) $view->viewer->getKey());

        return [
            'id' => $conversation->ulid,
            'kind' => $conversation->kind->value,
            'name' => $this->name($view),
            'title' => $conversation->title,
            'avatar_url' => $view->isGroup() ? null : $view->other?->avatar_url,
            'other' => $view->other === null ? null : ParticipantPayload::for($view->other),
            'participants' => $this->unless((bool) $request->attributes->get(self::COMPACT, false), fn (): array => $view->participants
                ->filter(fn (ConversationParticipant $p) => $p->user !== null)
                ->map(fn (ConversationParticipant $p) => ParticipantPayload::for($p->user, $p, $view->viewer))->values()->all()),
            'participants_count' => $view->participants->count(),
            'last_message' => $this->lastMessage($view),
            'last_message_at' => ($conversation->last_message_at ?? $conversation->created_at)?->toIso8601String(),
            'unread_count' => $view->unread,
            'muted' => $view->isMuted(),
            'muted_until' => $view->isMuted() ? $view->me?->muted_until?->toIso8601String() : null,
            'role' => $view->me?->role,
            'can_send' => $view->canSend,
            'can_manage' => $view->canManage,
            'blocked_by_me' => $view->blockedByMe,
            'others_count' => $others->count(),
            'created_at' => $conversation->created_at?->toIso8601String(),
        ];
    }

    private function name(ConversationView $view): string
    {
        if (! $view->isGroup()) {
            return (string) ($view->other?->pseudo ?? __('community.members.guest'));
        }

        $title = trim((string) $view->conversation->title);

        if ($title !== '') {
            return $title;
        }

        $names = $view->participants
            ->filter(fn (ConversationParticipant $p) => (int) $p->user_id !== (int) $view->viewer->getKey() && $p->user !== null)
            ->take(self::GROUP_NAME_PSEUDOS)
            ->map(fn (ConversationParticipant $p) => (string) $p->user?->pseudo)
            ->implode(', ');

        return $names !== '' ? $names : (string) __('community.messages.group_default_title');
    }

    /**
     * @return array{id: int, preview: string|null, preview_hidden: bool, mine: bool, author: string|null, deleted: bool, attachment: bool, at: string|null}|null
     */
    private function lastMessage(ConversationView $view): ?array
    {
        $message = $view->lastMessage;

        if (! $message instanceof Message) {
            return null;
        }

        $mine = $message->user_id !== null && (int) $message->user_id === (int) $view->viewer->getKey();

        return [
            'id' => (int) $message->getKey(),
            'preview' => $view->previewVisible ? self::preview($message) : null,
            'preview_hidden' => ! $view->previewVisible,
            'mine' => $mine,
            'author' => $view->isGroup() && ! $mine ? $message->user?->pseudo : null,
            'deleted' => $message->isDeletedForEveryone(),
            'attachment' => ! $message->isDeletedForEveryone() && $message->hasMedia(),
            'at' => $message->created_at?->toIso8601String(),
        ];
    }

    /** Aperçu calculé à la lecture (ADR-33) : corps déchiffré, sur une ligne, tronqué. */
    public static function preview(Message $message): string
    {
        if ($message->isDeletedForEveryone()) {
            return (string) __('community.messages.deleted');
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $message->body));

        if ($text === '') {
            return $message->hasMedia() ? (string) __('community.messages.attachment') : '';
        }

        return Str::limit($text, (int) config('xomiton.messaging.preview_length', 80));
    }
}
