<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Community\ForumAudience;
use App\Models\ForumReply;
use App\Models\User;

/**
 * Réponses de forum (P8-05) : jamais plus visibles que leur sujet (ForumTopicPolicy::view), mêmes blocages que le fil
 * (App\Domain\Community\ForumAudience : une réponse anonyme d'un compte que la personne bloque reste lisible, RG-02) ;
 * l'auteur·rice supprime la sienne, `posts.moderate` modère tout.
 */
final class ForumReplyPolicy
{
    public function __construct(private readonly ForumTopicPolicy $topics) {}

    public function view(User $user, ForumReply $reply): bool
    {
        $isAuthor = $reply->user_id !== null && $reply->user_id === $user->getKey();

        if ($user->can('posts.moderate') || $isAuthor) {
            return true;
        }

        if ($reply->topic === null || ! $this->topics->view($user, $reply->topic)) {
            return false;
        }

        return ForumAudience::allows(
            isAuthor: false,
            isPublished: $reply->status->isPublished() && ! $reply->trashed(),
            blockedByAuthor: ForumTopicPolicy::blocks($reply->user_id, $user->getKey()),
            blockedByViewer: ForumTopicPolicy::blocks($user->getKey(), $reply->user_id),
            anonymous: (bool) $reply->is_anonymous,
        );
    }

    public function delete(User $user, ForumReply $reply): bool
    {
        return ($reply->user_id !== null && $reply->user_id === $user->getKey()) || $user->can('posts.moderate');
    }

    public function report(User $user, ForumReply $reply): bool
    {
        return $reply->user_id !== $user->getKey() && $this->view($user, $reply);
    }
}
