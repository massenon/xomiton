<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Network;

use App\Application\Community\Network\FollowMember;
use App\Application\Community\Network\NetworkSuggestions;
use App\Http\Controllers\Api\V1\Community\CommunityApiController;
use App\Http\Requests\Api\Network\ListSuggestionsRequest;
use App\Http\Requests\Api\Network\StoreFollowRequest;
use App\Http\Resources\Network\NetworkPersonResource;
use App\Models\Follow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/follows (P8-06, docs/API.md §17) : suggestions « Qui suivre » (filtres pseudonyme, zone, rôle ; pagination
 * par curseur, `meta.next_cursor`, `xomiton.network.suggestions_per_page`), suivre et ne plus suivre un·e membre
 * (idempotents).
 */
final class FollowController extends CommunityApiController
{
    public function suggestions(ListSuggestionsRequest $request, NetworkSuggestions $suggestions): JsonResponse
    {
        $page = $suggestions->page(
            $request->viewer(),
            $request->filters(),
            $this->perPage($request, (int) config('xomiton.network.suggestions_per_page', 12)),
            $request->cursor(),
        );

        return $this->data(NetworkPersonResource::collection($page->items)->resolve($request), 200, $page->meta());
    }

    public function store(StoreFollowRequest $request, FollowMember $follow): JsonResponse
    {
        return $this->data($follow->follow($request->viewer(), $request->targetId()));
    }

    public function destroy(Request $request, FollowMember $follow, int $user): JsonResponse
    {
        $viewer = $this->viewer($request);
        abort_unless($viewer->can('create', Follow::class), 403);

        return $this->data($follow->unfollow($viewer, $user));
    }
}
