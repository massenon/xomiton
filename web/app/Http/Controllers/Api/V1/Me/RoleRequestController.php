<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\RoleRequests\RequestPeerRole;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Me\RoleRequestResource;
use App\Models\RoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Demande du rôle pair depuis le profil (`me/role-requests`, D-MOB-15, docs/API.md §23). `POST` : motif court facultatif,
 * `Idempotency-Key` obligatoire, limiteur `role_request`, une demande en cours à la fois, e-mail confirmé ; crée seulement
 * une demande `pending` (JAMAIS le rôle : il ne vient que de la décision d'un compte habilité). `GET` : mes demandes, les
 * plus récentes d'abord. Le corps n'accepte aucun champ de rôle : on ne demande que le rôle pair.
 */
final class RoleRequestController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $items = RoleRequest::query()->where('user_id', $user->getKey())->orderByDesc('id')->limit(20)->get();

        return $this->data(
            $items->map(static fn (RoleRequest $item): array => RoleRequestResource::make($item)->resolve($request))->values()->all(),
            200,
            ['can_request' => ! $user->hasRole('pair') && ! $items->contains(fn (RoleRequest $item): bool => $item->isPending())],
        );
    }

    public function store(Request $request, RequestPeerRole $requestRole): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:'.RoleRequest::REASON_MAX]]);

        $created = $requestRole($user, $validated['reason'] ?? null);

        return $this->respond(new RoleRequestResource($created), 201);
    }
}
