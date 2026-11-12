<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Community\ForumAudience;
use App\Models\ForumTopic;
use App\Models\User;
use App\Models\UserBlock;

/**
 * Sujets de forum (P8-05 ; ROLE_ET_PERMISSIONS.md §4, aucune permission propre : celles du fil) : `social.access` lit,
 * `posts.create` ouvre un sujet, `comments.create` répond, l'auteur·rice supprime le sien, `posts.moderate` lit, masque
 * ou supprime tout. Visibilité : App\Domain\Community\ForumAudience (publié, forum actif, blocages comme le fil : pour un
 * sujet anonyme, seul celui posé par l'auteur·rice compte, Q72). Un sujet fermé (`is_locked`) reste lisible ; la
 * réponse y est refusée par le cas d'usage (422 `topic_locked`, message lisible) et `can.reply` le dit au client.
 */
final class ForumTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, ForumTopic $topic): bool
    {
        $isAuthor = $topic->user_id !== null && $topic->user_id === $user->getKey();

        if ($user->can('posts.moderate') || $isAuthor) {
            return true;
        }

        if (! $user->can('social.access')) {
            return false;
        }

        return ForumAudience::allows(
            isAuthor: false,
            isPublished: $topic->isPublished() && ! $topic->trashed(),
            forumActive: $topic->forum?->is_active === true,
            blockedByAuthor: self::blocks($topic->user_id, $user->getKey()),
            blockedByViewer: self::blocks($user->getKey(), $topic->user_id),
            anonymous: (bool) $topic->is_anonymous,
        );
    }

    public function create(User $user): bool
    {
        return $user->can('social.access') && $user->can('posts.create');
    }

    /**
     * Répondre : sujet visible, publié, d'un forum ouvert (même pour l'auteur·rice ou la modération, qui voient un forum
     * fermé) ; un sujet fermé l'interdit (TopicLocked, 422).
     */
    public function reply(User $user, ForumTopic $topic): bool
    {
        return self::acceptsReplies($user, $topic) && $this->view($user, $topic);
    }

    /**
     * Répondre à un sujet **déjà visible** de la personne (liste filtrée par ForumDirectory, fiche contrôlée par view()) :
     * sans requête de blocage, pour `can.reply` de ForumTopicResource (PERFORMANCE.md §5, pas de N+1).
     */
    public static function acceptsReplies(User $user, ForumTopic $topic): bool
    {
        return $user->can('comments.create')
            && $topic->isPublished()
            && ! $topic->trashed()
            && $topic->forum?->is_active === true;
    }

    public function delete(User $user, ForumTopic $topic): bool
    {
        return ($topic->user_id !== null && $topic->user_id === $user->getKey()) || $user->can('posts.moderate');
    }

    /**
     * Valider (ou retirer) la réponse qui résout le sujet (P18-03, Q97) : l'auteur·rice du sujet — même anonyme : le
     * compte reste lié au sujet — ou la modération (`posts.moderate`). Le sujet doit être visible de la personne.
     */
    public function accept(User $user, ForumTopic $topic): bool
    {
        $isAuthor = $topic->user_id !== null && $topic->user_id === $user->getKey();

        return ($isAuthor || $user->can('posts.moderate')) && $user->can('social.access') && ! $topic->trashed();
    }

    public function moderate(User $user, ForumTopic $topic): bool
    {
        return $user->can('posts.moderate');
    }

    public function report(User $user, ForumTopic $topic): bool
    {
        return $topic->user_id !== $user->getKey() && $this->view($user, $topic);
    }

    /** `$by` bloque `$target` (aucun des deux nul). */
    public static function blocks(?int $by, ?int $target): bool
    {
        if ($by === null || $target === null) {
            return false;
        }

        return UserBlock::query()->where('user_id', $by)->where('blocked_user_id', $target)->exists();
    }
}
