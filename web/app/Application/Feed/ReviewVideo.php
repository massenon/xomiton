<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Moderation\Listeners\NotifyModerators;
use App\Application\Shared\MediaUploader;
use App\Domain\Moderation\Exceptions\ModerationRefused;
use App\Enums\PostStatus;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Notifications\VideoPendingReview;
use App\Notifications\VideoReviewed;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Relecture des vidéos par la modération (Q96) : une vidéo envoyée est une publication au statut `relecture`
 * (PostStatus::Pending) — invisible hors auteur·rice et modération, jamais dans le fil, la recherche, les compteurs ni
 * les notifications d'abonné·es — jusqu'à une décision.
 *  - `submitted()` : cloche « Vidéo à relire » aux comptes `moderation.manage` (hors l'auteur·rice) et journal
 *    `post.video_submitted` ;
 *  - `approve()` : statut publié, `published_at` = date d'approbation (`reviewed_at` aussi : état `approved` de l'API),
 *    compteur du groupe, cloche « Ta vidéo a été publiée », journal `post.video_approved` ;
 *  - `reject()` : motif de la liste `feed.video_reject_reasons` obligatoire + texte libre ≤ 300 car., statut `refuse`,
 *    cloche à l'auteur·rice avec le motif, journal `post.video_rejected` (code du motif, jamais le texte ni la vidéo) ;
 *    le fichier est effacé après `feed.video_rejected_retention_days` par `videos:purge-rejected` (`purgeRejected()`).
 * La policy `review` est rejouée à chaque décision (permission `moderation.manage`, jamais sa propre vidéo) ; une vidéo
 * déjà décidée est refusée (`video_not_pending`) : deux modérateur·rices simultané·es ne décident qu'une fois.
 */
final class ReviewVideo
{
    public function __construct(private readonly MediaUploader $uploader) {}

    public function submitted(Post $post): void
    {
        Audit::log('post.video_submitted', $post, null, $post->user);

        $moderators = NotifyModerators::moderators()->reject(static fn (User $moderator): bool => $moderator->getKey() === $post->user_id);

        if ($moderators->isNotEmpty()) {
            Notification::send($moderators, new VideoPendingReview((int) $post->getKey()));
        }
    }

    /**
     * @throws ModerationRefused
     */
    public function approve(Post $post, User $by): Post
    {
        Gate::forUser($by)->authorize('review', $post);

        $approved = DB::transaction(function () use ($post, $by): Post {
            $locked = $this->lockPending($post);

            $locked->forceFill([
                'status' => PostStatus::Published,
                'published_at' => now(),
                'reviewed_by' => $by->getKey(),
                'reviewed_at' => now(),
                'review_reason' => null,
                'review_note' => null,
            ])->save();

            if ($locked->group_id !== null) {
                Group::query()->whereKey($locked->group_id)->toBase()->increment('posts_count');
            }

            return $locked;
        });

        Audit::log('post.video_approved', $approved, null, $by);
        $this->notifyAuthor($approved, new VideoReviewed(VideoReviewed::APPROVED, (int) $approved->getKey(), url: PostLinks::show($approved)));

        return $approved;
    }

    /**
     * @throws ModerationRefused
     */
    public function reject(Post $post, User $by, string $reason, ?string $note = null): Post
    {
        Gate::forUser($by)->authorize('review', $post);

        if (! in_array($reason, self::reasons(), true)) {
            throw new ModerationRefused('video_reason_invalid');
        }

        $note = $note === null ? null : Str::limit(trim((string) preg_replace('/\s+/u', ' ', $note)), self::noteMax(), '');
        $note = $note === '' ? null : $note;

        if ($reason === 'other' && $note === null) {
            throw new ModerationRefused('video_note_required');
        }

        $rejected = DB::transaction(function () use ($post, $by, $reason, $note): Post {
            $locked = $this->lockPending($post);

            $locked->forceFill([
                'status' => PostStatus::Rejected,
                'reviewed_by' => $by->getKey(),
                'reviewed_at' => now(),
                'review_reason' => $reason,
                'review_note' => $note,
            ])->save();

            return $locked;
        });

        Audit::log('post.video_rejected', $rejected, 'motif='.$reason, $by);
        $this->notifyAuthor($rejected, new VideoReviewed(
            VideoReviewed::REJECTED,
            (int) $rejected->getKey(),
            self::reasonText($reason, $note),
            PostLinks::show($rejected),
            max(1, (int) config('xomiton.feed.video_rejected_retention_days', 7)),
        ));

        return $rejected;
    }

    /**
     * Supprime du disque les fichiers des vidéos refusées depuis plus de `video_rejected_retention_days` jours ; la ligne
     * `post_media` reste (chemin vide : plus d'adresse de lecture) pour garder le quota et l'historique.
     *
     * @return int nombre de fichiers supprimés
     */
    public function purgeRejected(): int
    {
        $limit = now()->subDays(max(1, (int) config('xomiton.feed.video_rejected_retention_days', 7)));
        $purged = 0;

        PostMedia::query()
            ->where('kind', PostMedia::KIND_VIDEO)
            ->where('path', '!=', '')
            ->whereIn('post_id', Post::query()->withTrashed()->select('id')->where('status', PostStatus::Rejected)->where('reviewed_at', '<=', $limit))
            ->lazyById(100)
            ->each(function (PostMedia $media) use (&$purged): void {
                $this->uploader->delete((string) $media->path, MediaUploader::privateDisk());
                $media->forceFill(['path' => ''])->save();
                $purged++;
            });

        return $purged;
    }

    /** Supprime tout de suite le fichier d'une vidéo (retrait par l'auteur·rice d'une vidéo en attente ou refusée). */
    public function discardFiles(Post $post): void
    {
        foreach (PostMedia::query()->where('post_id', $post->getKey())->where('kind', PostMedia::KIND_VIDEO)->where('path', '!=', '')->get() as $media) {
            $this->uploader->delete((string) $media->path, MediaUploader::privateDisk());
            $media->forceFill(['path' => ''])->save();
        }
    }

    /** @return list<string> */
    public static function reasons(): array
    {
        return array_values(array_filter((array) config('xomiton.feed.video_reject_reasons', []), 'is_string'));
    }

    public static function noteMax(): int
    {
        return max(1, (int) config('xomiton.feed.video_reject_note_max', 300));
    }

    /** Motif tel que lu par l'auteur·rice : libellé, puis le texte libre de la modération. */
    public static function reasonText(string $reason, ?string $note): string
    {
        $label = (string) __('feed.video.reasons.'.$reason);

        return $note === null || $note === '' ? $label : $label.' — '.$note;
    }

    /**
     * @throws ModerationRefused
     */
    private function lockPending(Post $post): Post
    {
        $locked = Post::query()->whereKey($post->getKey())->lockForUpdate()->first();

        if ($locked === null || ! $locked->isPendingReview()) {
            throw new ModerationRefused('video_not_pending');
        }

        return $locked;
    }

    private function notifyAuthor(Post $post, VideoReviewed $notification): void
    {
        $author = $post->user;

        if ($author instanceof User && $author->isActive()) {
            $author->notify($notification);
        }
    }
}
