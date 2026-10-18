<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Feed\PostAudience;
use App\Enums\PostVisibility;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;

/**
 * Publications du fil (ROLE_ET_PERMISSIONS.md §4) : `social.access` lit, `posts.create` publie, `polls.create` publie un
 * sondage, l'auteur·rice modifie, `posts.moderate` masque, restaure ou supprime n'importe quelle publication.
 * Visibilité (P7-01) : App\Domain\Feed\PostAudience, plus la confidentialité du groupe (FR-SOC-05 : un groupe privé est
 * invisible aux non-membres) et les blocages dans les deux sens — pour une publication anonyme, seul celui posé par
 * l'auteur·rice (Q72 : bloquer quelqu'un ne doit pas révéler qui a écrit).
 */
final class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Post $post): bool
    {
        $isAuthor = $post->user_id !== null && $post->user_id === $user->getKey();

        if ($user->can('posts.moderate') || $isAuthor) {
            return true;
        }

        if (! $user->can('social.access')) {
            return false;
        }

        if ($post->group_id !== null && ($post->group === null || ! $user->can('view', $post->group))) {
            return false;
        }

        $anonymous = (bool) $post->is_anonymous;
        $followsAuthor = $post->visibility === PostVisibility::Followers
            && ! $anonymous
            && $post->user_id !== null
            && $user->following()->whereKey($post->user_id)->exists();

        return PostAudience::allows(
            visibility: $post->visibility,
            isPublished: $post->status->isPublished() && ! $post->trashed(),
            isAuthor: false,
            followsAuthor: $followsAuthor,
            blocked: self::blocked($user, $post->user_id, onlyByAuthor: $anonymous),
            anonymous: $anonymous,
        );
    }

    public function create(User $user): bool
    {
        return $user->can('posts.create');
    }

    public function createPoll(User $user): bool
    {
        return $user->can('posts.create') && $user->can('polls.create');
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

    /** « Masquer » : retirer la publication de son propre fil. */
    public function hide(User $user, Post $post): bool
    {
        return $this->view($user, $post);
    }

    public function vote(User $user, Post $post): bool
    {
        return $this->view($user, $post);
    }

    public function comment(User $user, Post $post): bool
    {
        return $user->can('comments.create') && $this->view($user, $post) && $post->status->isPublished();
    }

    public function report(User $user, Post $post): bool
    {
        return $post->user_id !== $user->getKey() && $this->view($user, $post);
    }

    /**
     * `onlyByAuthor` (contenu anonyme) : seul le blocage posé par l'auteur·rice envers la personne qui lit compte.
     */
    private static function blocked(User $viewer, ?int $authorId, bool $onlyByAuthor = false): bool
    {
        if ($authorId === null) {
            return false;
        }

        $byAuthor = UserBlock::query()->where('user_id', $authorId)->where('blocked_user_id', $viewer->getKey());

        if ($onlyByAuthor) {
            return $byAuthor->exists();
        }

        return $byAuthor
            ->orWhere(static fn ($q) => $q->where('user_id', $viewer->getKey())->where('blocked_user_id', $authorId))
            ->exists();
    }
}
