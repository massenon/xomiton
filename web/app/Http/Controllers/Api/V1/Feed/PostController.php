<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Feed\ManagePost;
use App\Application\Feed\PublishPost;
use App\Http\Requests\Api\Feed\StorePostRequest;
use App\Http\Requests\Api\Feed\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * /api/v1/posts (P7-02, docs/API.md §8) : publier (201 + Location, publication ou sondage), lire (404 si invisible),
 * modifier (auteur·rice), supprimer (auteur·rice ou `posts.moderate`, 204).
 */
final class PostController extends FeedApiController
{
    public function store(StorePostRequest $request, PublishPost $publish): JsonResponse
    {
        $post = $publish($this->viewer($request), $request->toDraft());

        return $this->postResponse($request, $post, 201);
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
