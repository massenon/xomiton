<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Feed\Data\PostDraft;
use App\Application\Moderation\ReportContent;
use App\Application\Shared\MediaUploader;
use App\Application\Shared\StoredMedia;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\PostStatus;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Publier dans le fil (P7-02, FR-SOC-01, FR-SOC-02 ; prototype post-create.php) : filtre de mots sur chaque texte
 * (bloquant → 422 avant tout téléversement), image ré-encodée par MediaUploader (EXIF retiré, disque privé, collection
 * `images`), publication + média + sondage et ses réponses dans une transaction (le fichier est effacé si elle échoue),
 * puis signalement automatique si un terme « à surveiller » a été trouvé (la publication reste en ligne).
 */
final class PublishPost
{
    public function __construct(
        private readonly BadWordFilter $filter,
        private readonly MediaUploader $uploader,
        private readonly ReportContent $reports,
    ) {}

    /**
     * @throws ContentBlocked
     */
    public function __invoke(User $author, PostDraft $draft): Post
    {
        $texts = ['body' => $draft->body, 'image_alt' => $draft->imageAlt];

        if ($draft->poll !== null) {
            $texts['poll.question'] = $draft->poll->question;

            foreach ($draft->poll->options as $index => $label) {
                $texts['poll.options.'.$index] = $label;
            }
        }

        $verdict = $this->filter->assertAllowed($texts);
        $stored = $draft->image !== null ? $this->uploader->upload($draft->image, 'images') : null;

        try {
            /** @var Post $post */
            $post = DB::transaction(fn (): Post => $this->persist($author, $draft, $stored));
        } catch (Throwable $exception) {
            if ($stored !== null) {
                $this->uploader->delete($stored);
            }

            throw $exception;
        }

        if ($verdict->isFlagged()) {
            $this->reports->automatic($post, $verdict);
        }

        return $post;
    }

    private function persist(User $author, PostDraft $draft, ?StoredMedia $stored): Post
    {
        $post = Post::query()->create([
            'user_id' => $author->getKey(),
            'is_anonymous' => $draft->anonymous,
            'body' => trim((string) $draft->body),
            'tag' => $draft->tag,
            'visibility' => $draft->visibility,
            'status' => PostStatus::Published,
            'published_at' => now(),
        ]);

        if ($stored !== null) {
            PostMedia::query()->create([
                'post_id' => $post->getKey(),
                'kind' => PostMedia::KIND_IMAGE,
                'path' => $stored->path,
                'alt' => $draft->imageAlt !== null && trim($draft->imageAlt) !== '' ? trim($draft->imageAlt) : null,
                'width' => $stored->width,
                'height' => $stored->height,
                'size' => $stored->size,
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
