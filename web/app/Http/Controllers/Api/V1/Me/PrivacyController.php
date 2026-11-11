<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\Settings\UpdatePrivacySettings;
use App\Application\Account\UserSettings;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\UpdatePrivacyRequest;
use App\Http\Resources\Me\PrivacyResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET / PATCH /api/v1/me/privacy (P8-09, prototype settings.php « Confidentialité ») : visibilité du profil, zone
 * affichée, présence dans « Qui suivre », partage de la position lors d'un SOS.
 */
final class PrivacyController extends ApiController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('profile');
        UserSettings::ensure($user);

        return $this->respond(new PrivacyResource($user), headers: self::NO_STORE);
    }

    public function update(UpdatePrivacyRequest $request, UpdatePrivacySettings $update): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['profile', 'settings']);

        return $this->respond(new PrivacyResource($update($user, $request->toPrivacy())), headers: self::NO_STORE);
    }
}
