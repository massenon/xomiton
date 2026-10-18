<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Account\UserSettings;
use App\Application\Auth\CompleteRegistrationProfile;
use App\Application\Auth\ConnectedDevices;
use App\Application\Auth\UpdateUserSettings;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Auth\UpdateSettingsRequest;
use App\Http\Resources\SessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/me : mon compte (UserResource), mon profil (phases 2-3, profil progressif ; lecture P6-06), mes réglages
 * (thème, langue, sons, préférences de notification P3-02 ; lecture P6-06) et mes appareils connectés
 * (App\Application\Auth\ConnectedDevices). Sécurité, suppression, appareils de synchronisation, abonnements push,
 * newsletter et export : contrôleurs App\Http\Controllers\Api\V1\Me\*.
 * Rien ici ne touche un autre compte.
 */
final class MeController extends ApiController
{
    public function __construct(
        private readonly CompleteRegistrationProfile $completeProfile,
        private readonly ConnectedDevices $devices,
        private readonly UpdateUserSettings $updateSettings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data($this->userPayload($user, $request))->withHeaders(self::NO_STORE);
    }

    /**
     * GET me/profile (P6-06) : le profil de la vue « moi » (UserResource), centres d'intérêt et statut allié·e compris.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data((array) ($this->userPayload($user, $request)['profile'] ?? []))->withHeaders(self::NO_STORE);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        ($this->completeProfile)($user, $request->toProfileData());

        return $this->data($this->userPayload($user->fresh() ?? $user, $request));
    }

    /**
     * GET me/settings (P6-06) : thème, langue, sons, préférences de notification (vue « moi » de UserResource), plus
     * les champs d'écriture du prototype mobile `locale`, `sounds`, `notifications` (UserSettings::writeAliases).
     */
    public function settings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $settings = UserSettings::ensure($user);

        return $this->data((array) ($this->userPayload($user, $request)['settings'] ?? []) + UserSettings::writeAliases($settings));
    }

    /**
     * Réglages et préférences de notification par catégorie (sécurité du compte et SOS restent actifs quoi qu'il arrive).
     */
    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        ($this->updateSettings)($user, $request->toSettings());

        return $this->data($this->userPayload($user->fresh() ?? $user, $request));
    }

    public function sessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $user->currentAccessToken();

        $rows = $this->devices->list(
            $user,
            $current instanceof PersonalAccessToken ? (int) $current->getKey() : null,
            $request->hasSession() ? $request->session()->getId() : null,
        );

        return $this->data(SessionResource::collection($rows)->resolve($request), 200, ['total' => $rows->count()]);
    }

    /**
     * Révoque un jeton (`token-<id>`) ou une session web (`web-<empreinte>`) de la personne connectée seulement.
     */
    public function destroySession(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->devices->revoke($user, $id)) {
            throw new NotFoundHttpException;
        }

        return $this->data(['revoked' => true, 'id' => $id]);
    }
}
