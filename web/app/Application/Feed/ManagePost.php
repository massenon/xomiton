<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Moderation\ReportContent;
use App\Application\Shared\MediaUploader;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Modifier ou supprimer une publication (P7-02, PATCH / DELETE posts/{ulid}) : l'auteur·rice change le texte, l'étiquette
 * (jamais vers ou depuis « sondage ») et la visibilité, avec le même filtre de mots qu'à la publication (`edited_at`
 * posé si quelque chose change : seule source de la mention « modifiée ») ; la suppression
 * passe la publication en `supprime` puis la supprime logiquement (soft delete : la modération peut la retrouver), et
 * une suppression par la modération est journalisée `post.deleted_by_moderation`.
 */
final class ManagePost
{
    public function __construct(
        private readonly BadWordFilter $filter,
        private readonly ReportContent $reports,
        private readonly MediaUploader $uploader,
        private readonly ReviewVideo $review,
    ) {}

    /**
     * `remove_media` : positions (0 = première) des **images** à retirer ; les autres gardent leur ordre et sont
     * renumérotées, les fichiers sont supprimés du disque (Q96).
     *
     * @param  array{body?: string|null, tag?: PostTag, visibility?: PostVisibility, remove_media?: list<int>}  $changes
     *
     * @throws ContentBlocked
     */
    public function update(Post $post, array $changes): Post
    {
        $verdict = $this->filter->assertAllowed(['body' => $changes['body'] ?? null], 'post');

        if (array_key_exists('body', $changes)) {
            $post->body = trim((string) $changes['body']);
        }

        if (isset($changes['tag']) && ! $post->isPoll() && $changes['tag'] !== PostTag::Poll) {
            $post->tag = $changes['tag'];
        }

        if (isset($changes['visibility'])) {
            $post->visibility = $changes['visibility'];
        }

        $removed = $this->removeImages($post, $changes['remove_media'] ?? []);

        // « Modifiée » (PostResource `edited`) : seulement si le texte, l'étiquette, la visibilité ou les images changent vraiment.
        if ($removed || $post->isDirty(['body', 'tag', 'visibility'])) {
            $post->edited_at = now();
        }

        $post->save();

        if ($verdict->needsReview()) {
            $this->reports->automatic($post, $verdict);
        }

        return $post;
    }

    public function delete(Post $post, User $actor): void
    {
        // Vidéo en attente ou refusée (Q96) : jamais comptée dans son groupe, son fichier est supprimé tout de suite.
        $underReview = $post->isUnderReview();

        DB::transaction(static function () use ($post, $underReview): void {
            $post->forceFill(['status' => PostStatus::Deleted])->save();
            $post->delete();

            // Publication de groupe (P8-02) : `groups.posts_count` compte les publications **non supprimées** (une
            // publication masquée par la modération y reste, le masquage étant réversible), jamais sous zéro.
            if ($post->group_id !== null && ! $underReview) {
                Group::query()->whereKey($post->group_id)->where('posts_count', '>=', 1)->toBase()->decrement('posts_count');
            }
        });

        if ($underReview) {
            $this->review->discardFiles($post);
            Audit::log('post.video_withdrawn', $post, null, $actor);
        }

        if ($post->user_id !== $actor->getKey()) {
            Audit::log('post.deleted_by_moderation', $post, null, $actor);
        }
    }

    /**
     * Retire des images par position, puis renumérote les médias restants (0, 1, 2…).
     *
     * @param  list<int>  $positions
     */
    private function removeImages(Post $post, array $positions): bool
    {
        if ($positions === []) {
            return false;
        }

        $removed = false;

        foreach (PostMedia::query()->where('post_id', $post->getKey())->orderBy('position')->orderBy('id')->get() as $media) {
            if ($media->isImage() && in_array((int) $media->position, $positions, true)) {
                $this->uploader->delete((string) $media->path, MediaUploader::privateDisk());
                $media->delete();
                $removed = true;
            }
        }

        if ($removed) {
            foreach (PostMedia::query()->where('post_id', $post->getKey())->orderBy('position')->orderBy('id')->get()->values() as $index => $media) {
                if ((int) $media->position !== $index) {
                    $media->forceFill(['position' => $index])->save();
                }
            }

            $post->unsetRelation('media');
        }

        return $removed;
    }
}
