<?php

declare(strict_types=1);

namespace App\Application\Community\Forums;

use App\Models\DatabaseNotification;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Notifications\ForumReplyPosted;
use App\Policies\ForumReplyPolicy;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloche « Nouvelle réponse » (P3-02 ForumReplyPosted, préférence « Communauté ») pour l'auteur·rice du sujet, anonyme
 * ou non : jamais pour soi-même, jamais vers un compte inactif, et seulement si le sujet et la réponse lui sont
 * **visibles** (ForumReplyPolicy) — un blocage n'est donc jamais trahi par une notification manquante ou de trop
 * (RG-02). Le nom est le pseudo, ou « Membre anonyme » pour une réponse anonyme ; l'adresse mène au sujet par ULID
 * (`#reponses`). Une notification non lue du même sujet est remplacée par la plus récente (pas de pile dans la cloche).
 * Un échec d'envoi ne fait jamais échouer la réponse (journalisé).
 */
final class ForumReplyNotifier
{
    public function __construct(private readonly ForumReplyPolicy $replies) {}

    public function replied(ForumTopic $topic, ForumReply $reply, User $actor): void
    {
        $recipient = $topic->user;

        if ($recipient === null || $recipient->is($actor) || ! $recipient->isActive() || ! $this->replies->view($recipient, $reply)) {
            return;
        }

        try {
            DatabaseNotification::query()
                ->where('notifiable_type', $recipient->getMorphClass())
                ->where('notifiable_id', $recipient->getKey())
                ->where('type', ForumReplyPosted::class)
                ->whereNull('read_at')
                ->where('data->topic_id', $topic->getKey())
                ->delete();

            $recipient->notify(new ForumReplyPosted(
                topicId: (int) $topic->getKey(),
                replyId: (int) $reply->getKey(),
                authorName: $reply->authorLabel(),
                topicTitle: (string) $topic->title,
                url: ForumLinks::show($topic, 'reponses'),
            ));
        } catch (Throwable $exception) {
            Log::warning('Forums : notification de réponse non envoyée.', ['topic_id' => $topic->getKey(), 'error' => $exception->getMessage()]);
        }
    }
}
