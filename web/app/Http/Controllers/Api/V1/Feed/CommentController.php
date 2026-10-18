<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Feed\ManageComments;
use App\Http\Requests\Api\Feed\ListFeedRequest;
use App\Http\Requests\Api\Feed\StoreCommentRequest;
use App\Http\Resources\Feed\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Commentaires (P7-02 ; simulateur `post`) : `GET posts/{ulid}/comments` (publiés, du plus ancien au plus récent,
 * pagination par curseur, `meta.comments_count`), `POST posts/{ulid}/comments` (201, filtre de mots), `DELETE
 * comments/{id}` (auteur·rice ou `posts.moderate`, 204 ; commentaire invisible → 404).
 */
final class CommentController extends FeedApiController
{
    public function index(ListFeedRequest $request, Post $post): JsonResponse
    {
        $post = $this->visible($request, $post);
        $query = $this->feed->comments($post, $this->viewer($request));

        return $this->paginated(
            $query->cursorPaginate($this->perPage($request, (int) config('xomiton.feed.comments_per_page', 20))),
            CommentResource::class,
            ['comments_count' => (int) $post->comments_count],
        );
    }

    public function store(StoreCommentRequest $request, Post $post, ManageComments $comments): JsonResponse
    {
        $comment = $comments->add(
            $request->visiblePost(),
            $this->viewer($request),
            (string) $request->validated('body'),
            $request->parent(),
            $request->boolean('is_anonymous'),
        );
        $comment->load(['user.profile', 'user.roles']);

        return $this->created(CommentResource::make($comment), null, ['comments_count' => (int) $post->refresh()->comments_count]);
    }

    public function destroy(Request $request, Comment $comment, ManageComments $comments): Response
    {
        $viewer = $this->viewer($request);

        if (! $viewer->can('view', $comment)) {
            throw new NotFoundHttpException;
        }

        Gate::forUser($viewer)->authorize('delete', $comment);
        $comments->delete($comment, $viewer);

        return $this->noContent();
    }
}
