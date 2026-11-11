<?php

declare(strict_types=1);

namespace App\Application\Community\Pages;

use App\Models\Page;
use App\Models\PageFollower;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Suivre / ne plus suivre une page (P8-03, pages.php l.8-13) : idempotent (unique `(page_id, user_id)` ; un rejeu rend
 * `changed: false` sans toucher au compteur), `followers_count` par incrément atomique dans la transaction, jamais
 * sous zéro (comme App\Application\Feed\FeedCounters).
 */
final class FollowPage
{
    /**
     * @return array{following: bool, followers_count: int, changed: bool}
     */
    public function follow(User $user, Page $page): array
    {
        $changed = false;

        try {
            DB::transaction(static function () use ($user, $page, &$changed): void {
                $created = PageFollower::query()->firstOrCreate(['page_id' => $page->getKey(), 'user_id' => $user->getKey()]);

                if ($created->wasRecentlyCreated) {
                    Page::query()->whereKey($page->getKey())->toBase()->increment('followers_count');
                    $changed = true;
                }
            });
        } catch (QueryException) {
            // Deux abonnements simultanés : la contrainte unique a gardé le premier.
            $changed = false;
        }

        return $this->state($user, $page, $changed);
    }

    /**
     * @return array{following: bool, followers_count: int, changed: bool}
     */
    public function unfollow(User $user, Page $page): array
    {
        $changed = DB::transaction(static function () use ($user, $page): bool {
            $deleted = PageFollower::query()->where('page_id', $page->getKey())->where('user_id', $user->getKey())->delete();

            if ($deleted > 0) {
                Page::query()->whereKey($page->getKey())->where('followers_count', '>=', 1)->toBase()->decrement('followers_count');
            }

            return $deleted > 0;
        });

        return $this->state($user, $page, $changed);
    }

    /**
     * @return array{following: bool, followers_count: int, changed: bool}
     */
    private function state(User $user, Page $page, bool $changed): array
    {
        $count = (int) Page::query()->whereKey($page->getKey())->value('followers_count');
        $page->setAttribute('followers_count', $count);

        return [
            'following' => PageFollower::query()->where('page_id', $page->getKey())->where('user_id', $user->getKey())->exists(),
            'followers_count' => $count,
            'changed' => $changed,
        ];
    }
}
