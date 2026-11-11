<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Account\RoleRequests\ReviewRoleRequest;
use App\Http\Resources\Admin\AdminRoleRequestResource;
use App\Models\RoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * File des demandes du rôle pair (`admin/role-requests`, D-MOB-15 ; `peers.assign`) : liste (en attente par défaut, les plus
 * anciennes d'abord ; `status=all|approved|rejected`), approuver (rôle donné via ManagePeerRole, jamais à soi-même) et refuser
 * avec un **motif** écrit pour la personne. Les deux décisions exigent la confirmation `step-up`, comme toute attribution de
 * rôle. Mêmes cas d'usage que la file de l'administration web ; journal `source=mobile`.
 */
final class RoleRequestController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        abort_unless($user->can('peers.assign'), 403);

        $status = (string) $request->query('status', RoleRequest::STATUS_PENDING);
        abort_unless(in_array($status, ['all', RoleRequest::STATUS_PENDING, RoleRequest::STATUS_APPROVED, RoleRequest::STATUS_REJECTED], true), 422);

        $page = RoleRequest::query()->with(['user.zone'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy($status === RoleRequest::STATUS_PENDING ? 'id' : 'decided_at', $status === RoleRequest::STATUS_PENDING ? 'asc' : 'desc')
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->page(
            $page,
            $page->getCollection()->map(static fn (RoleRequest $item): array => AdminRoleRequestResource::make($item)->resolve($request))->values()->all(),
            ['pending' => RoleRequest::query()->pending()->count()],
        );
    }

    public function approve(Request $request, RoleRequest $roleRequest, ReviewRoleRequest $review): JsonResponse
    {
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('assignPeerRole', $roleRequest->user);
        $this->confirm($request);

        $decided = $review->approve($roleRequest, $admin);

        return $this->respond(new AdminRoleRequestResource($decided->load('user.zone')));
    }

    public function reject(Request $request, RoleRequest $roleRequest, ReviewRoleRequest $review): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:'.RoleRequest::REASON_MAX]]);
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('assignPeerRole', $roleRequest->user);
        $this->confirm($request);

        $decided = $review->reject($roleRequest, $admin, (string) $validated['reason']);

        return $this->respond(new AdminRoleRequestResource($decided->load('user.zone')));
    }
}
