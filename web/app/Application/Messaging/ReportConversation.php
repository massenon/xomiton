<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Application\Moderation\ReportContent;
use App\Application\Moderation\ReportOutcome;
use App\Domain\Messaging\Exceptions\NothingToReport;
use App\Enums\ContentReportReason;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;

/**
 * « Signaler cette conversation » (P8-01, prototype messaging.php l.85-100, FR-SOC-06) → `moderation_reports` +
 * ContentReported (cloche de la modération, App\Application\Moderation\Listeners\NotifyModerators).
 *
 *  - sujet : le message désigné (`message_id`, d'une autre personne), sinon le dernier message d'une autre personne,
 *    sinon — conversation privée sans message reçu — l'autre personne (`utilisateur`) ; groupe sans message reçu → 422 ;
 *  - **aucun extrait** : le corps est chiffré au repos, `moderation_reports.excerpt` est en clair (ADR-33) ; la lecture
 *    du message signalé par la modération relève de P12-06 (Q à valider) ;
 *  - un signalement par personne et par sujet : un second envoi rend le premier (200), sans double compte
 *    (App\Application\Moderation\ReportContent::confidential, création et dédoublonnage communs avec le fil) ;
 *  - un message signalé que son autrice supprime ensuite reste lisible par la modération (ManageConversation) ;
 *  - ni masquage automatique (RG-16 vise le fil), ni réponse qui dirait qui a déjà signalé.
 */
final class ReportConversation
{
    public function __construct(private readonly ReportContent $reports) {}

    public function __invoke(User $reporter, Conversation $conversation, ContentReportReason $reason, ?string $details, ?int $messageId): ReportOutcome
    {
        return $this->reports->confidential($reporter, $this->subject($reporter, $conversation, $messageId), $reason, $details);
    }

    private function subject(User $reporter, Conversation $conversation, ?int $messageId): Message|User
    {
        $floor = $conversation->isGroup() ? ConversationParticipant::query()->where('conversation_id', $conversation->getKey())
            ->where('user_id', $reporter->getKey())->value('joined_after_message_id') : null;
        $fromOthers = Message::query()->where('conversation_id', $conversation->getKey())
            ->whereNotNull('user_id')->where('user_id', '!=', $reporter->getKey())
            ->when($floor !== null, fn ($q) => $q->where('id', '>', (int) $floor));

        if ($messageId !== null) {
            $message = (clone $fromOthers)->whereKey($messageId)->first();

            if ($message === null) {
                throw new NothingToReport;
            }

            return $message;
        }

        $latest = (clone $fromOthers)->whereNull('deleted_for_everyone_at')->orderByDesc('id')->first();

        if ($latest !== null) {
            return $latest;
        }

        $other = $conversation->isGroup() ? null : ConversationParticipant::query()->with('user')
            ->where('conversation_id', $conversation->getKey())->where('user_id', '!=', $reporter->getKey())->first()?->user;

        if ($other === null) {
            throw new NothingToReport;
        }

        return $other;
    }
}
