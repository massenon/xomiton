<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PostVisibility;
use App\Models\Post;
use App\Models\User;

/**
 * Publications du fil (ROLE_ET_PERMISSIONS.md §4) : `social.access` lit, `posts.create` publie,
 * l'auteur·rice modifie, `posts.moderate` masque, restaure ou supprime n'importe quelle publication.
 */
final class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Post $post): bool
    {
        if ($user->can('posts.moderate') || $post->user_id === $user->getKey()) {
            return true;
        }

        if (! $user->can('social.access') || ! $post->status->isPublished()) {
            return false;
        }

        return match ($post->visibility) {
            PostVisibility::Public => true,
            PostVisibility::Followers => $post->user_id !== null
                && $user->following()->whereKey($post->user_id)->exists(),
            PostVisibility::Me => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->getKey() && $user->can('posts.create');
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->getKey() || $user->can('posts.moderate');
    }

    public function moderate(User $user, Post $post): bool
    {
        return $user->can('posts.moderate');
    }

    public function like(User $user, Post $post): bool
    {
        return $this->view($user, $post);
    }

    public function save(User $user, Post $post): bool
    {
        return $this->view($user, $post);
    }

    public function comment(User $user, Post $post): bool
    {
        return $user->can('comments.create') && $this->view($user, $post);
    }

    public function report(User $user, Post $post): bool
    {
        return $post->user_id !== $user->getKey() && $this->view($user, $post);
    }
}
