<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostLike;

/**
 * Compteurs dénormalisés du fil (P7-01, DATABASE_DICTIONARY.md §4) : `posts.likes_count`, `comments_count`,
 * `reports_count`, `polls.votes_count`, `voters_count`, `poll_options.votes_count`. Les cas d'usage les font varier par
 * incréments atomiques (`UPDATE … SET n = n + 1`, jamais lu-modifié-écrit) dans leur transaction ; un décrément ne
 * descend jamais sous zéro. `recount*()` recalcule depuis les tables de liaison (retrait d'un commentaire masqué ou
 * supprimé, reprise après incident). Requêtes sur le Query Builder (`toBase()`) : un compteur ne touche jamais
 * `updated_at` (aimer ou commenter n'est pas modifier la publication).
 */
final class FeedCounters
{
    public function increment(Post $post, string $column, int $by = 1): void
    {
        if ($by <= 0) {
            return;
        }

        Post::query()->whereKey($post->getKey())->toBase()->increment($column, $by);
        $post->setAttribute($column, (int) $post->getAttribute($column) + $by);
    }

    public function decrement(Post $post, string $column, int $by = 1): void
    {
        if ($by <= 0) {
            return;
        }

        Post::query()->whereKey($post->getKey())->where($column, '>=', $by)->toBase()->decrement($column, $by);
        $post->setAttribute($column, max(0, (int) $post->getAttribute($column) - $by));
    }

    /**
     * Commentaires publiés (réponses comprises), comme le compteur « Commentaires (n) » du prototype.
     */
    public function recountComments(Post $post): int
    {
        $count = Comment::query()->where('post_id', $post->getKey())->where('status', PostStatus::Published)->count();

        Post::query()->whereKey($post->getKey())->toBase()->update(['comments_count' => $count]);
        $post->setAttribute('comments_count', $count);

        return $count;
    }

    public function recountLikes(Post $post): int
    {
        $count = PostLike::query()->where('post_id', $post->getKey())->count();

        Post::query()->whereKey($post->getKey())->toBase()->update(['likes_count' => $count]);
        $post->setAttribute('likes_count', $count);

        return $count;
    }

    /**
     * Votes par option, total des votes et participant·es distinct·es d'un sondage.
     */
    public function recountPoll(Poll $poll): void
    {
        $perOption = PollVote::query()->where('poll_id', $poll->getKey())
            ->selectRaw('poll_option_id, count(*) as aggregate')->groupBy('poll_option_id')
            ->pluck('aggregate', 'poll_option_id');

        foreach (PollOption::query()->where('poll_id', $poll->getKey())->get() as $option) {
            $option->forceFill(['votes_count' => (int) ($perOption[$option->getKey()] ?? 0)])->saveQuietly();
        }

        $poll->forceFill([
            'votes_count' => (int) $perOption->sum(),
            'voters_count' => PollVote::query()->where('poll_id', $poll->getKey())->distinct()->count('user_id'),
        ])->saveQuietly();
    }
}
