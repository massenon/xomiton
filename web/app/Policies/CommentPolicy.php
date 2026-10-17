<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Commentaires : `comments.create`, l'auteur·rice modifie ou supprime, `posts.moderate` modère tout.
 * Un commentaire n'est jamais plus visible que sa publication (visibilité abonné·es / « moi seulement », masquage).
 */
final class CommentPolicy
{
    public function __construct(private readonly PostPolicy $posts) {}

    public function view(User $user, Comment $comment): bool
    {
        if ($user->can('posts.moderate') || $comment->user_id === $user->getKey()) {
            return true;
        }

        if (! $comment->status->isPublished() || ! $user->can('social.access')) {
            return false;
        }

        return $comment->post !== null && $this->posts->view($user, $comment->post);
    }

    public function create(User $user): bool
    {
        return $user->can('comments.create');
    }

    public function update(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey() && $user->can('comments.create');
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $comment->user_id === $user->getKey() || $user->can('posts.moderate');
    }

    public function moderate(User $user, Comment $comment): bool
    {
        return $user->can('posts.moderate');
    }

    public function report(User $user, Comment $comment): bool
    {
        return $comment->user_id !== $user->getKey() && $this->view($user, $comment);
    }
}
