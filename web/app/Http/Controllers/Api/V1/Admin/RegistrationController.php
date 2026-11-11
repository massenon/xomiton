<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Admin\Users\ReviewRegistration;
use App\Enums\MemberType;
use App\Http\Resources\Admin\AdminAccountResource;
use App\Models\RoleInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * File des inscriptions et demandes de pair en attente (`admin/registrations`, P15-16 ; `users.validate_peers`) — mêmes
 * cas d'usage que Administration › Inscriptions (ReviewRegistration) : liste (les plus anciennes d'abord, `kind` =
 * `peer_request` pour une demande de rôle pair, `registration` sinon ; `invited` = pair·e invité·e ; filtre `?kind=`), valider (rôle donné, compte actif, notification),
 * refuser avec un **motif** écrit pour la personne (confirmation `step-up` ; une demande de pair refusée laisse un compte
 * membre actif, une inscription refusée supprime le compte). Journal `source=mobile`.
 */
final class RegistrationController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        abort_unless($user->can('users.validate_peers'), 403);

        $kind = $request->validate(['kind' => ['sometimes', 'string', 'in:registration,peer_request,invited']])['kind'] ?? null;
        $invited = RoleInvitation::query()->waitingValidation()->where('role', 'pair')->select('user_id');
        $peerRequest = static fn ($query) => $query
            ->whereHas('profile', static fn ($profile) => $profile->where('member_type', MemberType::Peer->value))
            ->orWhereIn('id', $invited);

        $query = User::query()->pending()->with(['profile', 'zone', 'roles']);

        match ($kind) {
            'peer_request' => $query->where($peerRequest),
            'registration' => $query->whereNot($peerRequest),
            'invited' => $query->whereIn('id', $invited),
            default => null,
        };

        $page = $query->orderBy('created_at')->orderBy('id')->paginate($this->perPage($request));
        $invitedIds = RoleInvitation::query()->waitingValidation()->where('role', 'pair')->whereIn('user_id', $page->getCollection()->modelKeys())->pluck('user_id')->all();

        return $this->page(
            $page,
            $page->getCollection()->map(static fn (User $account): array => AdminAccountResource::make($account)->resolve($request) + [
                'kind' => ReviewRegistration::isPeerRequest($account) ? 'peer_request' : 'registration',
                'invited' => in_array($account->getKey(), $invitedIds, true),
            ])->values()->all(),
            ['pending' => User::query()->pending()->count(), 'kind' => $kind],
        );
    }

    public function approve(Request $request, User $user, ReviewRegistration $review): JsonResponse
    {
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('validatePeer', $user);

        $review->approve($user, $admin);

        return $this->data(AdminAccountResource::make($user->fresh(['profile', 'zone', 'roles']))->resolve($request));
    }

    public function reject(Request $request, User $user, ReviewRegistration $review): JsonResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('validatePeer', $user);
        $this->confirm($request);

        $review->reject($user, $admin, (string) $validated['reason']);

        return $this->data(['id' => (int) $user->getKey(), 'rejected' => true, 'account_kept' => ! $user->fresh()?->trashed() && $user->fresh() !== null]);
    }
}
