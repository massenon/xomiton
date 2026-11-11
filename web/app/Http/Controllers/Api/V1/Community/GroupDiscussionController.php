<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Application\Community\Groups\GroupDiscussion;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * POST /api/v1/groups/{ulid}/conversation (P8-02, bouton « Discussion du groupe ») : ouvre la conversation de groupe
 * rattachée (créée au premier appel, 201 ; ensuite 200) et y fait entrer la personne ; rend `{id, url}` — `url` ouvre
 * la conversation dans `/messages?c=<ulid>` (P8-01). Membres actives seulement (403).
 */
final class GroupDiscussionController extends CommunityApiController
{
    public function store(Request $request, GroupDiscussion $discussion, Group $group): JsonResponse
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('discuss', $this->discoverable($request, $group));

        [$conversation, $created] = $discussion->open($viewer, $group);
        $url = (Route::has('messages') ? route('messages') : url('/messages')).'?c='.$conversation->ulid;

        return $this->data(['id' => $conversation->ulid, 'url' => $url], $created ? 201 : 200);
    }
}
