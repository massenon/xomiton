<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Feed\Data\PostDraft;
use App\Application\Moderation\ReportContent;
use App\Application\Shared\MediaUploader;
use App\Application\Shared\StoredMedia;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Domain\Feed\Exceptions\VideoQuotaExceeded;
use App\Enums\PostStatus;
use App\Models\Group;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Publier dans le fil (P7-02, FR-SOC-01, FR-SOC-02 ; prototype post-create.php) : filtre de mots sur chaque texte
 * (bloquant → 422 avant tout téléversement), images (jusqu'à `feed.max_images`, ordre conservé, un texte alternatif par
 * image) ré-encodées par MediaUploader (EXIF retiré, disque privé, collection `images`) ou **une vidéo** (Q96 : mp4 ou
 * webm, collection `video`, stockée telle quelle sur le disque privé, **jamais ré-encodée** — ses métadonnées, GPS compris,
 * ne sont donc pas retirées) ; publication + médias + sondage et ses réponses dans une transaction (les fichiers sont
 * effacés si elle échoue), puis signalement automatique si un terme « à surveiller » a été trouvé (la publication reste en
 * ligne). Une vidéo crée une publication **en relecture** (PostStatus::Pending, ReviewVideo) : quota par compte
 * (VideoQuota, 429), aucun compteur de groupe ni date de parution avant l'approbation. Publication de groupe (P8-02,
 * `group_id`) : `groups.posts_count` incrémenté dans la même transaction (à l'approbation pour une vidéo).
 */
final class PublishPost
{
    public function __construct(
        private readonly BadWordFilter $filter,
        private readonly MediaUploader $uploader,
        private readonly ReportContent $reports,
        private readonly VideoQuota $quota,
        private readonly ReviewVideo $review,
    ) {}

    /**
     * @throws ContentBlocked
     * @throws VideoQuotaExceeded
     */
    public function __invoke(User $author, PostDraft $draft): Post
    {
        if ($draft->video !== null) {
            $this->quota->assertAvailable($author);
        }

        $texts = ['body' => $draft->body, 'video_description' => $draft->videoDescription];

        foreach ($draft->imagesAlt as $index => $alt) {
            $texts['images_alt.'.$index] = $alt;
        }

        if ($draft->poll !== null) {
            $texts['poll.question'] = $draft->poll->question;

            foreach ($draft->poll->options as $index => $label) {
                $texts['poll.options.'.$index] = $label;
            }
        }

        $verdict = $this->filter->assertAllowed($texts, 'post');

        /** @var list<StoredMedia> $stored */
        $stored = [];
        $storedVideo = null;

        try {
            foreach ($draft->images as $image) {
                $stored[] = $this->uploader->upload($image, 'images');
            }

            if ($draft->video !== null) {
                $storedVideo = $this->uploader->upload($draft->video, 'video', maxKb: FeedMediaLimits::videoMaxKb());
            }

            /** @var Post $post */
            $post = DB::transaction(fn (): Post => $this->persist($author, $draft, $stored, $storedVideo));
        } catch (Throwable $exception) {
            foreach ([...$stored, ...($storedVideo !== null ? [$storedVideo] : [])] as $file) {
                $this->uploader->delete($file);
            }

            throw $exception;
        }

        if ($verdict->needsReview()) {
            $this->reports->automatic($post, $verdict);
        }

        if ($storedVideo !== null) {
            $this->review->submitted($post);
        }

        return $post;
    }

    /**
     * @param  list<StoredMedia>  $stored
     */
    private function persist(User $author, PostDraft $draft, array $stored, ?StoredMedia $video): Post
    {
        $post = Post::query()->create([
            'user_id' => $author->getKey(),
            'group_id' => $draft->groupId,
            'is_anonymous' => $draft->anonymous,
            'body' => trim((string) $draft->body),
            'tag' => $draft->tag,
            'visibility' => $draft->visibility,
            'status' => $video !== null ? PostStatus::Pending : PostStatus::Published,
            'published_at' => now(),
        ]);

        if ($draft->groupId !== null && $video === null) {
            Group::query()->whereKey($draft->groupId)->toBase()->increment('posts_count');
        }

        foreach ($stored as $position => $file) {
            $alt = $draft->imagesAlt[$position] ?? null;

            PostMedia::query()->create([
                'post_id' => $post->getKey(),
                'kind' => PostMedia::KIND_IMAGE,
                'path' => $file->path,
                'mime' => $file->mime,
                'alt' => $alt !== null && trim($alt) !== '' ? trim($alt) : null,
                'width' => $file->width,
                'height' => $file->height,
                'size' => $file->size,
                'position' => $position,
            ]);
        }

        if ($video !== null) {
            PostMedia::query()->create([
                'post_id' => $post->getKey(),
                'kind' => PostMedia::KIND_VIDEO,
                'path' => $video->path,
                'mime' => $video->mime,
                'alt' => trim((string) $draft->videoDescription),
                'width' => null,
                'height' => null,
                'size' => $video->size,
                'position' => 0,
            ]);
        }

        if ($draft->poll !== null) {
            $poll = Poll::query()->create([
                'post_id' => $post->getKey(),
                'question' => trim($draft->poll->question),
                'multiple' => $draft->poll->multiple,
                'results_before_vote' => $draft->poll->resultsBeforeVote,
                'ends_at' => $draft->poll->durationDays !== null ? now()->addDays($draft->poll->durationDays) : null,
            ]);

            foreach (array_values($draft->poll->options) as $position => $label) {
                PollOption::query()->create(['poll_id' => $poll->getKey(), 'label' => trim($label), 'position' => $position + 1]);
            }
        }

        return $post;
    }
}
