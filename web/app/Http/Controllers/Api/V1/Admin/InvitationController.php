<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Admin\Roles\TeamInvitations;
use App\Http\Resources\Admin\AdminInvitationResource;
use App\Models\RoleInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Invitations à l'équipe (`admin/invitations`, P15-16, décisions client du 2026-10-29) : lien / code signé à usage unique
 * qui mène à la même inscription que tout le monde (case « 15 ans » comprise) ; le rôle suit à la validation du compte.
 * Rôle `pair` : `peers.invite` ; `moderateur`, `admin`, `superadmin` : `roles.assign_admin` (super-admin). Création et
 * annulation exigent la confirmation `step-up` ; le code et le lien ne sont rendus qu'à la création.
 */
final class InvitationController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        Gate::forUser($user)->authorize('viewAny', RoleInvitation::class);

        $page = RoleInvitation::query()
            ->when(! $user->can('roles.assign_admin'), fn ($query) => $query->where('role', 'pair'))
            ->when($request->query('status') !== null, fn ($query) => $query->where('status', (string) $request->query('status')))
            ->with(['inviter:id,pseudo', 'user:id,pseudo', 'zone:id,name'])
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->page($page, $page->getCollection()->map(static fn (RoleInvitation $i): array => AdminInvitationResource::make($i)->resolve($request))->values()->all());
    }

    public function store(Request $request, TeamInvitations $invitations): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(TeamInvitations::ROLES)],
            'email' => ['sometimes', 'nullable', 'email:rfc', 'max:190'],
            'note' => ['sometimes', 'nullable', 'string', 'max:190'],
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
            'valid_days' => ['sometimes', 'nullable', 'integer', 'min:'.TeamInvitations::minDays(), 'max:'.TeamInvitations::maxDays()],
        ]);
        $admin = $this->admin($request);
        // D-MOB-16 : une invitation à un rôle d'administration ne se crée QUE depuis le web (super-admin).
        abort_if((string) $validated['role'] !== 'pair', 403, (string) __('admin.users.mobile_web_only'));
        Gate::forUser($admin)->authorize('invite', [RoleInvitation::class, (string) $validated['role']]);
        $this->confirm($request);

        $created = $invitations->create($admin, (string) $validated['role'], $validated['email'] ?? null, $validated['note'] ?? null, isset($validated['zone_id']) ? (int) $validated['zone_id'] : null, isset($validated['valid_days']) ? (int) $validated['valid_days'] : null);
        $created['invitation']->load(['inviter:id,pseudo', 'zone:id,name']);

        return $this->respond(new AdminInvitationResource($created['invitation'], ['code' => $created['code'], 'link' => $created['link']]), 201);
    }

    public function destroy(Request $request, RoleInvitation $invitation, TeamInvitations $invitations): Response
    {
        $this->confirm($request);
        $invitations->revoke($invitation, $this->admin($request));

        return $this->noContent();
    }
}
