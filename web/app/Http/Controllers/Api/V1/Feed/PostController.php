<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Feed\ManagePost;
use App\Application\Feed\PublishPost;
use App\Enums\PostStatus;
use App\Http\Requests\Api\Feed\StorePostRequest;
use App\Http\Requests\Api\Feed\UpdatePostRequest;
use App\Http\Resources\Feed\PostResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * /api/v1/posts (P7-02, docs/API.md §8) : publier (201 + Location, publication ou sondage ; **202** + Location pour une
 * vidéo, qui part en relecture, Q96), lire (404 si invisible), modifier (auteur·rice), supprimer ou retirer sa vidéo en
 * attente (auteur·rice ou `posts.moderate`, 204), lister ses vidéos en attente ou refusées (`posts/review`).
 */
final class PostController extends FeedApiController
{
    public function store(StorePostRequest $request, PublishPost $publish): JsonResponse
    {
        $post = $publish($this->viewer($request), $request->toDraft());

        return $this->postResponse($request, $post, $post->isPendingReview() ? 202 : 201);
    }

    /** Mes vidéos en attente de relecture ou refusées (état `review_state`), les plus récentes d'abord. */
    public function review(Request $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $posts = $this->feed->withViewerState(
            Post::query()->where('user_id', $viewer->getKey())->whereIn('status', [PostStatus::Pending, PostStatus::Rejected]),
            $viewer,
        )->orderByDesc('id')->limit(50)->get();

        return $this->respond(PostResource::collection($posts)->resolve($request));
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        return $this->postResponse($request, $this->visible($request, $post));
    }

    public function update(UpdatePostRequest $request, Post $post, ManagePost $manage): JsonResponse
    {
        return $this->postResponse($request, $manage->update($post, $request->changes()));
    }

    public function destroy(Request $request, Post $post, ManagePost $manage): Response
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('delete', $this->visible($request, $post));

        $manage->delete($post, $viewer);

        return $this->noContent();
    }
}
