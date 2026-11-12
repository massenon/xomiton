<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Domain\Feed\Exceptions\VideoQuotaExceeded;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Carbon\Carbon;

/**
 * Limiteur de vidéos par compte (Q96) : `xomiton.feed.video_per_day` envois sur 24 heures glissantes, **retraits et refus
 * compris** (supprimer sa vidéo ne rend pas le quota). Calculé sur `post_media` (publications supprimées logiquement
 * comprises), donc sans état de cache à purger ; le limiteur `feed_write` (10 / min) reste appliqué en plus.
 */
final class VideoQuota
{
    /**
     * @throws VideoQuotaExceeded
     */
    public function assertAvailable(User $author): void
    {
        $max = max(1, (int) config('xomiton.feed.video_per_day', 5));
        $since = now()->subDay();

        $window = PostMedia::query()
            ->where('kind', PostMedia::KIND_VIDEO)
            ->where('created_at', '>=', $since)
            ->whereIn('post_id', Post::query()->withTrashed()->select('id')->where('user_id', $author->getKey()));

        if ((clone $window)->count() < $max) {
            return;
        }

        $oldest = (clone $window)->min('created_at');
        $retryAfter = is_string($oldest) ? max(1, (int) now()->diffInSeconds(Carbon::parse($oldest)->addDay(), false)) : 3600;

        throw new VideoQuotaExceeded($max, $retryAfter);
    }
}
