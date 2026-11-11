<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\UpdateSecuritySettings;
use App\Application\Account\UserSettings;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\UpdateSecurityRequest;
use App\Http\Resources\Me\SecurityResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET / PATCH /api/v1/me/security (P6-06, prototype `simulateur.html` l.1260) : mode discret, aperçus masqués,
 * verrouillage par code (jamais de code transmis, Q37 b), titre d'onglet neutre.
 */
final class SecurityController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('profile');
        UserSettings::ensure($user);

        return $this->respond(new SecurityResource($user), headers: self::NO_STORE);
    }

    public function update(UpdateSecurityRequest $request, UpdateSecuritySettings $update): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['profile', 'settings']);

        return $this->respond(new SecurityResource($update($user, $request->toSecurity())));
    }
}
