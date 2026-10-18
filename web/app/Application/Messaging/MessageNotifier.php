<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DatabaseNotification;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * « Nouveau message » (P8-01, NOTIFICATIONS §2, App\Notifications\NewMessage livrée en P3-02) aux autres
 * participant·es présent·es, hors sourdine, joignables et sans blocage avec l'auteur·rice.
 *
 *  - **aucun extrait du message** dans la cloche ni dans le push : `notifications.data` est en clair et la charge push
 *    transite par FCM / Web Push, or le corps est chiffré au repos (ADR-33) ; le texte dit qui écrit et combien de
 *    messages attendent (« Awa : 2 nouveaux messages ») ; mode discret / aperçus masqués → « Nouveau message »
 *    (NewMessage::discreetContent, appliqué par le socle) ;
 *  - une seule notification non lue par conversation : la précédente est remplacée ; si elle date de moins de
 *    `xomiton.messaging.notify_cooldown_seconds`, elle est mise à jour sans nouvel envoi (pas de rafale de push) ;
 *  - lire la conversation marque sa notification comme lue (markConversationRead).
 *
 * Un échec d'envoi n'empêche jamais le message d'être enregistré (journalisé, sans contenu).
 */
final class MessageNotifier
{
    public function __construct(private readonly MessagingContacts $contacts) {}

    public function messageSent(Message $message, User $author, Conversation $conversation): void
    {
        $recipients = ConversationParticipant::query()
            ->notifiable()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', '!=', $author->getKey())
            ->with(['user.settings', 'user.profile'])
            ->get();

        foreach ($recipients as $participant) {
            $recipient = $participant->user;

            if ($recipient === null || ! $this->contacts->reachable($recipient) || $this->contacts->blockedBetween($author, $recipient)) {
                continue;
            }

            try {
                $this->notify($recipient, $participant, $message, $author, $conversation);
            } catch (Throwable $exception) {
                Log::warning('messaging.notify_failed', ['conversation_id' => $conversation->getKey(), 'error' => $exception::class]);
            }
        }
    }

    public function markConversationRead(User $user, Conversation $conversation): int
    {
        return $this->pending($user, $conversation)->update(['read_at' => now()]);
    }

    public static function url(Conversation $conversation): string
    {
        return Route::has('messages') ? route('messages', ['c' => $conversation->ulid]) : url('/messages?c='.$conversation->ulid);
    }

    private function notify(User $recipient, ConversationParticipant $participant, Message $message, User $author, Conversation $conversation): void
    {
        $unread = max(1, $this->unreadFor($participant));
        $notification = new NewMessage(
            conversationId: (int) $conversation->getKey(),
            messageId: (int) $message->getKey(),
            senderName: (string) $author->pseudo,
            preview: trans_choice('notifications.new_message.count', $unread, ['count' => $unread]),
            conversationTitle: $conversation->isGroup() ? ($conversation->title ?: (string) __('community.messages.group_default_title')) : null,
            url: self::url($conversation),
        );

        $previous = $this->pending($recipient, $conversation)->latest()->first();
        $cooldown = (int) config('xomiton.messaging.notify_cooldown_seconds', 60);

        if ($previous !== null && $cooldown > 0 && $previous->created_at !== null && $previous->created_at->gt(now()->subSeconds($cooldown))) {
            $previous->forceFill(['data' => $notification->toDatabase($recipient)])->save();

            return;
        }

        $previous?->delete();
        $recipient->notify($notification);
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function pending(User $user, Conversation $conversation): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey())
            ->where('type', NewMessage::class)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversation->getKey());
    }

    private function unreadFor(ConversationParticipant $participant): int
    {
        return Message::query()
            ->where('conversation_id', $participant->conversation_id)
            ->where('id', '>', $participant->last_read_message_id ?? 0)
            ->whereNull('deleted_for_everyone_at')
            ->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $participant->user_id))
            ->count();
    }
}
