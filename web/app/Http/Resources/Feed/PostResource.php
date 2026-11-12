<?php

declare(strict_types=1);

namespace App\Http\Resources\Feed;

use App\Application\Feed\PostLinks;
use App\Application\Feed\ReviewVideo;
use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMedia;
use App\Models\PostSave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Publication du fil telle qu'elle sort de l'API (P7-02, docs/API.md §8) : identifiant public ULID (ADR-27), auteur·rice
 * (AuthorPayload, RG-02), corps, étiquette (libellé et couleur du prototype, PostTag::badge()), visibilité, compteurs,
 * état propre à la personne qui lit (`liked`, `saved`, `is_mine`), médias (lien signé pour le disque privé), sondage
 * (PollResource), liens de détail et de partage neutre (`/p/{ulid}`, Q31 b), permissions utiles à l'interface (`can`).
 * Le statut et le motif de masquage ne sont jamais renvoyés ; l'auteur·rice d'une publication masquée voit seulement
 * `moderated: true`.
 *
 * `media[]` (ordre conservé) : `kind` (image | video), `url` (lien signé ; pour une vidéo, lien court propre à la personne
 * qui lit, plages Range), `alt` (texte alternatif d'une image ; **description** d'une vidéo), `width`, `height`, `position`
 * (0 = premier), `mime`, `size` (octets). Vidéo (Q96) : `review_state` = `pending` (en attente de relecture : visible de
 * l'auteur·rice et de la modération seulement), `approved`, `rejected` (avec `review_reason`, pour l'auteur·rice
 * seulement), sinon null ; `published_at` reste nul tant que la vidéo n'est pas approuvée.
 *
 * @mixin Post
 */
final class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;
        $isMine = $viewer !== null && $post->user_id !== null && $post->user_id === $viewer->getKey();
        $links = app(PostLinks::class);

        return [
            'id' => $post->ulid,
            'author' => AuthorPayload::for($post->user, (bool) $post->is_anonymous),
            'is_mine' => $isMine,
            'is_anonymous' => (bool) $post->is_anonymous,
            'body' => $post->body,
            'tag' => $post->tag->value,
            'tag_label' => $post->tag->label(),
            'tag_color' => $post->tag->badge(),
            'visibility' => $post->visibility->value,
            'visibility_label' => $post->visibility->label(),
            'moderated' => $isMine && $post->currentStatus() === PostStatus::Hidden,
            'review_state' => $post->reviewState(),
            'review_label' => $post->reviewState() !== null ? (string) __('feed.video.states.'.$post->reviewState()) : null,
            'review_reason' => $isMine && $post->currentStatus() === PostStatus::Rejected && $post->review_reason !== null
                ? ReviewVideo::reasonText((string) $post->review_reason, $post->review_note)
                : null,
            'likes_count' => (int) $post->likes_count,
            'comments_count' => (int) $post->comments_count,
            'liked' => $viewer !== null && $this->flag($post, 'liked_by_viewer', PostLike::class, $viewer),
            'saved' => $viewer !== null && $this->flag($post, 'saved_by_viewer', PostSave::class, $viewer),
            'media' => ($post->relationLoaded('media') ? $post->media : $post->media()->get())
                ->map(static fn (PostMedia $media): array => [
                    'kind' => $media->kind,
                    'url' => $media->isVideo() ? $links->video($post, $media, $viewer) : $links->media($media),
                    'alt' => $media->alt,
                    'width' => $media->width,
                    'height' => $media->height,
                    'position' => (int) $media->position,
                    'mime' => $media->mime,
                    'size' => $media->size,
                ])
                ->filter(static fn (array $media): bool => $media['url'] !== null)
                ->values()->all(),
            'poll' => $post->isPoll() && $post->poll !== null ? PollResource::make($post->poll)->resolve($request) : null,
            'url' => PostLinks::show($post),
            'share_url' => PostLinks::share($post),
            'published_at' => $post->isUnderReview() ? null : $post->published_at?->toIso8601String(),
            'edited' => $post->edited_at !== null,
            // Calculées sans relancer PostPolicy::view (la publication est déjà visible : feed, détail autorisé), pour
            // éviter deux requêtes par publication et par droit dans une page du fil ; la policy reste juge à l'écriture.
            'can' => [
                'update' => $isMine && ! $post->isUnderReview() && $viewer->can('posts.create'),
                'delete' => $isMine || ($viewer?->can('posts.moderate') ?? false),
                'report' => $viewer !== null && ! $isMine && ! $post->isUnderReview(),
                'comment' => $viewer !== null && $post->isPublished() && $viewer->can('comments.create'),
            ],
        ];
    }

    /**
     * @param  class-string<PostLike|PostSave>  $model
     */
    private function flag(Post $post, string $attribute, string $model, User $viewer): bool
    {
        if (array_key_exists($attribute, $post->getAttributes())) {
            return (bool) $post->getAttribute($attribute);
        }

        return $model::query()->where('post_id', $post->getKey())->where('user_id', $viewer->getKey())->exists();
    }
}
