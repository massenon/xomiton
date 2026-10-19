<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\Settings\UpdateAccountIdentity;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\UpdateIdentityRequest;
use App\Http\Resources\Me\IdentityResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET / PATCH /api/v1/me/identity (P8-09, section « Profil » de /parametres) : nom, pseudonyme, téléphone, département,
 * commune et présentation de la personne connectée (jamais d'un autre compte). Réponses `no-store` : nom et téléphone.
 */
final class IdentityController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('profile');

        return $this->respond(new IdentityResource($user), headers: self::NO_STORE);
    }

    public function update(UpdateIdentityRequest $request, UpdateAccountIdentity $update): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('profile');

        return $this->respond(new IdentityResource($update($user, $request->toIdentity())), headers: self::NO_STORE);
    }
}
