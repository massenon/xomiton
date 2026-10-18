<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Application\Feed\PostReactions;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Réactions idempotentes d'une publication visible (P7-02, FR-SOC-03) : `POST / DELETE posts/{ulid}/like`,
 * `…/save`, `…/hide`. Toujours 200 avec l'état final (`liked` + `likes_count`, `saved`, `hidden`) et `changed` (faux sur
 * un rejeu) : la mise à jour optimiste du client se recale sur la réponse.
 */
final class PostReactionController extends FeedApiController
{
    public function like(Request $request, Post $post, PostReactions $reactions): JsonResponse
    {
        $this->authorizeOn($request, 'like', $post);
        $changed = $reactions->like($post, $this->viewer($request));

        return $this->data(['liked' => true, 'likes_count' => (int) $post->refresh()->likes_count, 'changed' => $changed]);
    }

    public function unlike(Request $request, Post $post, PostReactions $reactions): JsonResponse
    {
        $this->authorizeOn($request, 'like', $post);
        $changed = $reactions->unlike($post, $this->viewer($request));

        return $this->data(['liked' => false, 'likes_count' => (int) $post->refresh()->likes_count, 'changed' => $changed]);
    }

    public function save(Request $request, Post $post, PostReactions $reactions): JsonResponse
    {
        $this->authorizeOn($request, 'save', $post);

        return $this->data(['saved' => true, 'changed' => $reactions->save($post, $this->viewer($request))]);
    }

    public function unsave(Request $request, Post $post, PostReactions $reactions): JsonResponse
    {
        $this->authorizeOn($request, 'save', $post);

        return $this->data(['saved' => false, 'changed' => $reactions->unsave($post, $this->viewer($request))]);
    }

    public function hide(Request $request, Post $post, PostReactions $reactions): JsonResponse
    {
        $this->authorizeOn($request, 'hide', $post);

        return $this->data(['hidden' => true, 'changed' => $reactions->hide($post, $this->viewer($request))]);
    }

    public function unhide(Request $request, Post $post, PostReactions $reactions): JsonResponse
    {
        $this->authorizeOn($request, 'hide', $post);

        return $this->data(['hidden' => false, 'changed' => $reactions->unhide($post, $this->viewer($request))]);
    }

    private function authorizeOn(Request $request, string $ability, Post $post): void
    {
        Gate::forUser($this->viewer($request))->authorize($ability, $this->visible($request, $post));
    }
}
