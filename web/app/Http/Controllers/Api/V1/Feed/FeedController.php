<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Requests\Api\Feed\ListFeedRequest;
use App\Http\Resources\Feed\PostResource;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/v1/feed (`?tag=&cursor=&per_page=`, 20 par page, simulateur `fil`) et GET feed/saved (P7-06 « Enregistrées »)
 * : publications visibles par la personne connectée (App\Application\Feed\Queries\FeedQuery), plus récentes d'abord,
 * pagination par curseur (`meta.next_cursor`, docs/API.md §1.1).
 */
final class FeedController extends FeedApiController
{
    public function index(ListFeedRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $query = $this->feed->withViewerState($this->feed->feed($viewer, $request->tag()), $viewer);

        return $this->paginated(
            $query->cursorPaginate($this->perPage($request, (int) config('xomiton.feed.per_page', 20))),
            PostResource::class,
            ['tag' => $request->tag()?->value],
        );
    }

    public function saved(ListFeedRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $query = $this->feed->withViewerState($this->feed->saved($viewer), $viewer);

        return $this->paginated(
            $query->cursorPaginate($this->perPage($request, (int) config('xomiton.feed.per_page', 20))),
            PostResource::class,
        );
    }
}
