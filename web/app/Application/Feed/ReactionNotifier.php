<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\DatabaseNotification;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\ReactionGrouped;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloche « Réaction ou commentaire » (NOTIFICATIONS §2, §4.2) : une seule notification non lue par publication et par
 * nature (j'aime, commentaire, réponse) ; une nouvelle réaction remplace la précédente (« Estelle et 4 autres ont aimé
 * ta publication »). Le compte est celui des **personnes distinctes** qui ont réagi depuis l'ouverture du regroupement
 * (`since`, gardé dans la notification), relu dans `post_likes` ou `comments` : retirer puis remettre son j'aime, ou
 * commenter trois fois, compte une personne. Aucun identifiant de qui réagit n'est gardé dans la notification (un
 * commentaire anonyme reste anonyme). Jamais pour soi-même, jamais entre comptes qui se bloquent (ni dans le compte),
 * jamais vers un compte inactif. Un échec d'envoi ne fait jamais échouer le « j'aime » ou le commentaire (journalisé).
 */
final class ReactionNotifier
{
    public function liked(Post $post, User $actor): void
    {
        $likedAt = PostLike::query()->where('post_id', $post->getKey())->where('user_id', $actor->getKey())->value('created_at');

        $this->notify($post->user, $post, $actor, ReactionGrouped::KIND_LIKE, $likedAt instanceof CarbonInterface ? $likedAt : now());
    }

    public function commented(Post $post, Comment $comment, User $actor): void
    {
        $at = $comment->created_at ?? now();
        $this->notify($post->user, $post, $actor, ReactionGrouped::KIND_COMMENT, $at, $comment->is_anonymous);

        $parentAuthor = $comment->parent?->user;

        if ($parentAuthor !== null && ! $parentAuthor->is($post->user)) {
            $this->notify($parentAuthor, $post, $actor, ReactionGrouped::KIND_REPLY, $at, $comment->is_anonymous);
        }
    }

    private function notify(?User $recipient, Post $post, User $actor, string $kind, CarbonInterface $occurredAt, bool $anonymousActor = false): void
    {
        if ($recipient === null || $recipient->is($actor) || ! $recipient->isActive() || self::blocked($recipient, $actor)) {
            return;
        }

        try {
            $previous = DatabaseNotification::query()
                ->where('notifiable_type', $recipient->getMorphClass())
                ->where('notifiable_id', $recipient->getKey())
                ->where('type', ReactionGrouped::class)
                ->whereNull('read_at')
                ->where('data->post_id', $post->getKey())
                ->where('data->kind', $kind)
                ->latest()
                ->first();

            $since = self::since($previous) ?? CarbonImmutable::instance($occurredAt);
            $count = max(1, self::distinctActors($recipient, $post, $kind, $since));
            $previous?->delete();

            $recipient->notify(new ReactionGrouped(
                postId: (int) $post->getKey(),
                kind: $kind,
                actorName: $anonymousActor ? (string) __('feed.post.anonymous_author') : (string) $actor->pseudo,
                othersCount: $count - 1,
                url: PostLinks::show($post),
                since: $since->toIso8601String(),
            ));
        } catch (Throwable $exception) {
            Log::warning('Fil : notification de réaction non envoyée.', ['post_id' => $post->getKey(), 'kind' => $kind, 'error' => $exception->getMessage()]);
        }
    }

    /**
     * Début du regroupement en cours : `since` de la notification non lue, à défaut sa date (notification antérieure).
     */
    private static function since(?DatabaseNotification $previous): ?CarbonImmutable
    {
        if ($previous === null) {
            return null;
        }

        $since = $previous->data['since'] ?? null;

        if (is_string($since) && $since !== '') {
            try {
                return CarbonImmutable::parse($since)->setTimezone((string) config('app.timezone'));
            } catch (Throwable) {
                // Valeur illisible : on retombe sur la date de la notification.
            }
        }

        return $previous->created_at !== null ? CarbonImmutable::instance($previous->created_at) : null;
    }

    /**
     * Personnes distinctes (hors destinataire et comptes bloqués dans un sens ou dans l'autre) qui ont aimé, commenté la
     * publication ou répondu à un commentaire du ou de la destinataire depuis `since`.
     */
    private static function distinctActors(User $recipient, Post $post, string $kind, CarbonInterface $since): int
    {
        $recipientId = $recipient->getKey();

        /** @var Builder<PostLike>|Builder<Comment> $query */
        $query = match ($kind) {
            ReactionGrouped::KIND_LIKE => PostLike::query()->where('post_id', $post->getKey()),
            ReactionGrouped::KIND_COMMENT => Comment::query()->where('post_id', $post->getKey())->where('status', PostStatus::Published),
            default => Comment::query()->where('post_id', $post->getKey())->where('status', PostStatus::Published)
                ->whereIn('parent_id', Comment::query()->select('id')->where('post_id', $post->getKey())->where('user_id', $recipientId)),
        };

        return $query
            ->where('created_at', '>=', $since)
            ->whereNotNull('user_id')
            ->where('user_id', '<>', $recipientId)
            ->whereNotIn('user_id', UserBlock::query()->select('blocked_user_id')->where('user_id', $recipientId))
            ->whereNotIn('user_id', UserBlock::query()->select('user_id')->where('blocked_user_id', $recipientId))
            ->distinct()
            ->count('user_id');
    }

    private static function blocked(User $a, User $b): bool
    {
        return UserBlock::query()
            ->where(fn ($q) => $q->where('user_id', $a->getKey())->where('blocked_user_id', $b->getKey()))
            ->orWhere(fn ($q) => $q->where('user_id', $b->getKey())->where('blocked_user_id', $a->getKey()))
            ->exists();
    }
}
