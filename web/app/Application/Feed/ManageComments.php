<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Moderation\ReportContent;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Enums\PostStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Commentaires d'une publication (P7-02 ; prototype post-details.php) : filtre de mots côté serveur avant publication
 * (bloquant → 422 neutre, à surveiller → signalement automatique), réponse à un commentaire de la même publication
 * (`parent_id`), compteur `comments_count` tenu dans la transaction, cloche regroupée de l'auteur·rice de la publication
 * et de celle ou celui du commentaire parent. La suppression recalcule le compteur.
 */
final class ManageComments
{
    public function __construct(
        private readonly BadWordFilter $filter,
        private readonly FeedCounters $counters,
        private readonly ReactionNotifier $notifier,
        private readonly ReportContent $reports,
    ) {}

    /**
     * @throws ContentBlocked
     */
    public function add(Post $post, User $author, string $body, ?Comment $parent = null, bool $anonymous = false): Comment
    {
        $verdict = $this->filter->assertAllowed(['body' => $body]);

        /** @var Comment $comment */
        $comment = DB::transaction(function () use ($post, $author, $body, $parent, $anonymous): Comment {
            $comment = Comment::query()->create([
                'post_id' => $post->getKey(),
                'user_id' => $author->getKey(),
                'parent_id' => $parent?->getKey(),
                'body' => trim($body),
                'is_anonymous' => $anonymous,
                'status' => PostStatus::Published,
            ]);

            $this->counters->increment($post, 'comments_count');

            return $comment;
        });

        if ($verdict->isFlagged()) {
            $this->reports->automatic($comment, $verdict);
        }

        $comment->setRelation('post', $post);
        $this->notifier->commented($post, $comment, $author);

        return $comment;
    }

    public function delete(Comment $comment, User $actor): void
    {
        DB::transaction(function () use ($comment): void {
            $comment->forceFill(['status' => PostStatus::Deleted])->save();
            $comment->delete();

            if ($comment->post !== null) {
                $this->counters->recountComments($comment->post);
            }
        });

        if ($comment->user_id !== $actor->getKey()) {
            Audit::log('comment.deleted_by_moderation', $comment, null, $actor);
        }
    }
}
