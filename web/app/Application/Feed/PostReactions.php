<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Models\Post;
use App\Models\PostHide;
use App\Models\PostLike;
use App\Models\PostSave;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Réactions d'une personne à une publication (P7-02, FR-SOC-03) : j'aime, enregistrer, masquer de son fil. Toutes
 * **idempotentes** (mise à jour optimiste côté client, rejeu sans effet) : l'unicité (publication, membre) en base fait
 * foi, le compteur `likes_count` ne bouge que si une ligne a vraiment été ajoutée ou retirée. Un nouveau « j'aime »
 * prévient l'auteur·rice par la cloche regroupée (ReactionNotifier).
 */
final class PostReactions
{
    public function __construct(
        private readonly FeedCounters $counters,
        private readonly ReactionNotifier $notifier,
    ) {}

    public function like(Post $post, User $user): bool
    {
        $added = DB::transaction(function () use ($post, $user): bool {
            $added = $this->insert(PostLike::class, $post, $user);

            if ($added) {
                $this->counters->increment($post, 'likes_count');
            }

            return $added;
        });

        if ($added) {
            $this->notifier->liked($post, $user);
        }

        return $added;
    }

    public function unlike(Post $post, User $user): bool
    {
        return DB::transaction(function () use ($post, $user): bool {
            $removed = PostLike::query()->where('post_id', $post->getKey())->where('user_id', $user->getKey())->delete() > 0;

            if ($removed) {
                $this->counters->decrement($post, 'likes_count');
            }

            return $removed;
        });
    }

    public function save(Post $post, User $user): bool
    {
        return $this->insert(PostSave::class, $post, $user);
    }

    public function unsave(Post $post, User $user): bool
    {
        return PostSave::query()->where('post_id', $post->getKey())->where('user_id', $user->getKey())->delete() > 0;
    }

    public function hide(Post $post, User $user): bool
    {
        return $this->insert(PostHide::class, $post, $user);
    }

    public function unhide(Post $post, User $user): bool
    {
        return PostHide::query()->where('post_id', $post->getKey())->where('user_id', $user->getKey())->delete() > 0;
    }

    public static function isLiked(Post $post, User $user): bool
    {
        return PostLike::query()->where('post_id', $post->getKey())->where('user_id', $user->getKey())->exists();
    }

    /**
     * Insertion « une seule fois » : `INSERT … ON CONFLICT DO NOTHING` (MySQL `INSERT IGNORE`), sans lecture préalable.
     *
     * @param  class-string<PostLike|PostSave|PostHide>  $model
     */
    private function insert(string $model, Post $post, User $user): bool
    {
        return $model::query()->insertOrIgnore([
            'post_id' => $post->getKey(),
            'user_id' => $user->getKey(),
            'created_at' => now(),
        ]) > 0;
    }
}
