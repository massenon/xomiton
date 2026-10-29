<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Admin\Roles\ManagePeerRole;
use App\Application\Admin\Users\AdminRoles;
use App\Application\Admin\Users\ChangeUserRole;
use App\Enums\UserStatus;
use App\Http\Resources\Admin\AdminAccountResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Recherche d'un membre et attribution de rôles (`admin/members`, P15-16, décisions client du 2026-10-29) : trouver un
 * compte par pseudonyme (ou e-mail pour `users.manage`), lui **donner ou retirer le rôle pair** (ManagePeerRole,
 * `peers.assign` : il reste membre) ou passer membre ↔ pair (ChangeUserRole ; les rôles d'administration ne s'attribuent QUE depuis le web, D-MOB-16). Chaque changement exige la confirmation `step-up`, est
 * journalisé (`roles.*`, `users.role_changed`, `source=mobile`) et notifie la personne.
 */
final class MemberController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        Gate::forUser($user)->authorize('viewAny', User::class);

        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:60'],
            'role' => ['sometimes', 'string', Rule::in(AdminRoles::ALL)],
            'status' => ['sometimes', 'string', Rule::in(array_map(static fn (UserStatus $s): string => $s->value, UserStatus::cases()))],
        ]);

        $term = '%'.addcslashes(trim((string) $filters['q']), '%_\\').'%';
        $query = User::query()->notAnonymized()->with(['profile', 'zone', 'roles'])
            ->where(function ($where) use ($term, $user): void {
                $where->where('pseudo', 'like', $term)->orWhere('name', 'like', $term);

                if ($user->can('users.manage')) {
                    $where->orWhere('email', 'like', $term);
                }
            });

        if (isset($filters['role'])) {
            $query->role($filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderBy('pseudo')->orderBy('id')->paginate($this->perPage($request));

        return $this->page($page, $page->getCollection()->map(static fn (User $account): array => AdminAccountResource::make($account)->resolve($request))->values()->all());
    }

    public function show(Request $request, User $user): JsonResponse
    {
        Gate::forUser($this->admin($request))->authorize('view', $user);

        return $this->data(AdminAccountResource::make($user->load(['profile', 'zone', 'roles']))->resolve($request));
    }

    /** Donner le rôle pair à un membre existant (il reste membre). */
    public function grantPeer(Request $request, User $user, ManagePeerRole $peers): JsonResponse
    {
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('assignPeerRole', $user);
        $this->confirm($request);

        $changed = $peers->grant($user, $admin);

        return $this->data(AdminAccountResource::make($user->fresh(['profile', 'zone', 'roles']))->resolve($request), $changed ? 201 : 200, ['changed' => $changed]);
    }

    public function revokePeer(Request $request, User $user, ManagePeerRole $peers): JsonResponse
    {
        $validated = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:200']]);
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('revokePeerRole', $user);
        $this->confirm($request);

        $changed = $peers->revoke($user, $admin, $validated['reason'] ?? null);

        return $this->data(AdminAccountResource::make($user->fresh(['profile', 'zone', 'roles']))->resolve($request), 200, ['changed' => $changed]);
    }

    /** Changer le niveau de rôle membre ↔ pair seulement : jamais un rôle d'administration depuis le mobile (D-MOB-16). */
    public function changeRole(Request $request, User $user, ChangeUserRole $change): JsonResponse
    {
        $validated = $request->validate(['role' => ['required', 'string', Rule::in(AdminRoles::ALL)]]);
        $admin = $this->admin($request);

        // D-MOB-16 (tranché 2026-10-29) : les rôles d'administration (modérateur·rice, admin, super-admin) ne s'attribuent
        // ni ne se retirent QUE depuis le web (super-admin) ; l'API mobile ne gère que membre ↔ pair.
        abort_if(
            AdminRoles::isAdministration((string) $validated['role']) || AdminRoles::isAdministration(AdminRoles::of($user)),
            403,
            (string) __('admin.users.mobile_web_only'),
        );

        $this->confirm($request);

        $change($user, (string) $validated['role'], $admin);

        return $this->data(AdminAccountResource::make($user->fresh(['profile', 'zone', 'roles']))->resolve($request));
    }
}
