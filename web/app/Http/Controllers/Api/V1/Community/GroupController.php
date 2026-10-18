<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Application\Community\Groups\CreateGroup;
use App\Application\Community\Groups\GroupDirectory;
use App\Application\Community\Groups\GroupLinks;
use App\Http\Requests\Api\Community\ListGroupsRequest;
use App\Http\Requests\Api\Community\StoreGroupRequest;
use App\Http\Resources\Community\GroupResource;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/groups (P8-02, docs/API.md §13) : listes « Découvrir » et « Mes groupes » (recherche, pagination par page,
 * `xomiton.community.groups_per_page`), création (201 + Location, la personne devient propriétaire et admin), fiche
 * (réduite pour un groupe privé dont on n'est pas membre ; 404 pour un groupe inactif ou supprimé).
 */
final class GroupController extends CommunityApiController
{
    public function __construct(private readonly GroupDirectory $groups) {}

    public function index(ListGroupsRequest $request): JsonResponse
    {
        $viewer = $this->viewer($request);
        $paginator = $this->groups->paginate($viewer, $request->tab(), $request->term(), $this->perPage($request, (int) config('xomiton.community.groups_per_page', 6)));
        $memberships = $this->groups->memberships($viewer, $paginator->items());

        $items = array_map(
            static fn (Group $group): array => GroupResource::make($group)->withMembership($memberships[(int) $group->getKey()] ?? null)->resolve($request),
            $paginator->items(),
        );

        return $this->page($paginator, $items, ['tab' => $request->tab()]);
    }

    public function store(StoreGroupRequest $request, CreateGroup $create): JsonResponse
    {
        $viewer = $this->viewer($request);
        $group = $create($viewer, $request->toDraft())->load('zone');

        return $this->created(
            GroupResource::make($group)->withMembership($this->groups->membership($viewer, $group))->withPendingRequests(0),
            GroupLinks::show($group),
        );
    }

    public function show(Request $request, Group $group): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->discoverable($request, $group)->loadMissing('zone');

        return $this->respond(
            GroupResource::make($group)
                ->withMembership($this->groups->membership($viewer, $group))
                ->withPendingRequests($viewer->can('moderate', $group) ? $this->groups->pendingRequests($group) : null),
        );
    }
}
