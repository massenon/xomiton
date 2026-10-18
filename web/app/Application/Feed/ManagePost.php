<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Moderation\ReportContent;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Group;
use App\Models\Post;
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
    ) {}

    /**
     * @param  array{body?: string|null, tag?: PostTag, visibility?: PostVisibility}  $changes
     *
     * @throws ContentBlocked
     */
    public function update(Post $post, array $changes): Post
    {
        $verdict = $this->filter->assertAllowed(['body' => $changes['body'] ?? null]);

        if (array_key_exists('body', $changes)) {
            $post->body = trim((string) $changes['body']);
        }

        if (isset($changes['tag']) && ! $post->isPoll() && $changes['tag'] !== PostTag::Poll) {
            $post->tag = $changes['tag'];
        }

        if (isset($changes['visibility'])) {
            $post->visibility = $changes['visibility'];
        }

        // « Modifiée » (PostResource `edited`) : seulement si le texte, l'étiquette ou la visibilité changent vraiment.
        if ($post->isDirty(['body', 'tag', 'visibility'])) {
            $post->edited_at = now();
        }

        $post->save();

        if ($verdict->isFlagged()) {
            $this->reports->automatic($post, $verdict);
        }

        return $post;
    }

    public function delete(Post $post, User $actor): void
    {
        DB::transaction(static function () use ($post): void {
            $post->forceFill(['status' => PostStatus::Deleted])->save();
            $post->delete();

            // Publication de groupe (P8-02) : `groups.posts_count` compte les publications **non supprimées** (une
            // publication masquée par la modération y reste, le masquage étant réversible), jamais sous zéro.
            if ($post->group_id !== null) {
                Group::query()->whereKey($post->group_id)->where('posts_count', '>=', 1)->toBase()->decrement('posts_count');
            }
        });

        if ($post->user_id !== $actor->getKey()) {
            Audit::log('post.deleted_by_moderation', $post, null, $actor);
        }
    }
}
