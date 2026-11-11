<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Application\Community\Groups\GroupDirectory;
use App\Application\Community\Groups\GroupMembersQuery;
use App\Application\Community\Groups\ManageGroupMembership;
use App\Enums\MembershipStatus;
use App\Http\Requests\Api\Community\ListGroupMembersRequest;
use App\Http\Requests\Api\Community\UpdateGroupMemberRequest;
use App\Http\Resources\Community\GroupMemberResource;
use App\Http\Resources\Community\GroupResource;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Adhésions (P8-02, docs/API.md §13) : rejoindre (public → membre ; privé → demande notifiée à l'équipe), quitter ou
 * annuler sa demande, liste des membres (profils visibles seulement, équipe : toutes et demandes), accepter ou refuser
 * une demande, retirer une membre, changer un rôle. Réponses des gestes : la fiche du groupe à jour (GroupResource).
 */
final class GroupMembershipController extends CommunityApiController
{
    public function __construct(
        private readonly GroupDirectory $groups,
        private readonly ManageGroupMembership $manage,
    ) {}

    public function join(Request $request, Group $group): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->discoverable($request, $group);

        // Déjà membre : rejeu sans effet (idempotent) ; sinon GroupPolicy::join (groupe actif, personne non exclue).
        if ($this->groups->membership($viewer, $group)?->isActive() !== true) {
            Gate::forUser($viewer)->authorize('join', $group);
            $this->manage->join($viewer, $group);
        }

        return $this->groupResponse($request, $group);
    }

    public function leave(Request $request, Group $group): JsonResponse
    {
        $viewer = $this->viewer($request);

        // Groupe désactivé : on peut toujours le quitter ou annuler sa demande (l'adhésion et la discussion se défont).
        if ($this->groups->membership($viewer, $group) === null) {
            $this->discoverable($request, $group);
        }

        $this->manage->leave($viewer, $group);

        return $this->groupResponse($request, $group->refresh());
    }

    public function index(ListGroupMembersRequest $request, GroupMembersQuery $members, Group $group): JsonResponse
    {
        $viewer = $this->viewer($request);
        $this->readable($request, $group);
        $team = $viewer->can('moderate', $group);
        $status = $request->status();

        if ($status === MembershipStatus::Requested && ! $team) {
            abort(403);
        }

        $paginator = $members->for($group, $viewer, $team, $status)
            ->paginate($this->perPage($request, (int) config('xomiton.community.members_per_page', 24)));

        /** @var list<GroupMember> $rows */
        $rows = $paginator->items();

        return $this->page($paginator, GroupMemberResource::list($rows, $group, $viewer, $this->groups->membership($viewer, $group)), [
            'status' => $request->validated('status') ?? ListGroupMembersRequest::STATUS_ACTIVE,
            'hidden_count' => $team || $status === MembershipStatus::Requested ? 0 : $members->hiddenCount($group, $viewer),
            'pending_requests' => $team ? $this->groups->pendingRequests($group) : null,
        ]);
    }

    public function accept(Request $request, Group $group, User $user): JsonResponse
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('moderate', $this->readable($request, $group));

        $this->manage->accept($viewer, $group, $user);

        return $this->groupResponse($request, $group->refresh());
    }

    public function destroy(Request $request, Group $group, User $user): JsonResponse
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('moderate', $this->readable($request, $group));

        if ($user->is($viewer)) {
            throw new NotFoundHttpException;
        }

        $this->manage->remove($viewer, $group, $user);

        return $this->groupResponse($request, $group->refresh());
    }

    public function update(UpdateGroupMemberRequest $request, Group $group, User $user): JsonResponse
    {
        $viewer = $this->viewer($request);
        Gate::forUser($viewer)->authorize('changeRole', $this->readable($request, $group));

        if ($user->is($viewer)) {
            throw new NotFoundHttpException;
        }

        $member = $this->manage->changeRole($viewer, $group, $user, $request->role())->load('user.profile');

        return $this->respond(GroupMemberResource::list([$member], $group, $viewer, $this->groups->membership($viewer, $group))[0]);
    }

    private function groupResponse(Request $request, Group $group): JsonResponse
    {
        $viewer = $this->viewer($request);
        $group->loadMissing('zone');

        return $this->respond(
            GroupResource::make($group)
                ->withMembership($this->groups->membership($viewer, $group))
                ->withPendingRequests($viewer->can('moderate', $group) ? $this->groups->pendingRequests($group) : null),
        );
    }
}
