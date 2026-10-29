<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Account\DeviceRegistry;
use App\Application\Account\Profile\UpdateMemberProfile;
use App\Application\Account\UserSettings;
use App\Application\Auth\ConnectedDevices;
use App\Application\Auth\UpdateUserSettings;
use App\Http\Requests\Api\Auth\UpdateProfileRequest;
use App\Http\Requests\Api\Auth\UpdateSettingsRequest;
use App\Http\Resources\Me\DeviceResource;
use App\Http\Resources\SessionResource;
use App\Http\Resources\UserResource;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/me : mon compte (UserResource), mon profil (phases 2-3, profil progressif ; lecture P6-06 ; écriture
 * App\Application\Account\Profile\UpdateMemberProfile, champs sensibles sous consentement P8-08), mes réglages
 * (thème, langue, sons, préférences de notification P3-02 ; lecture P6-06) et mes appareils connectés
 * (App\Application\Auth\ConnectedDevices). Sécurité, suppression, appareils de synchronisation, abonnements push,
 * newsletter et export : contrôleurs App\Http\Controllers\Api\V1\Me\*.
 * Rien ici ne touche un autre compte.
 */
final class MeController extends ApiController
{
    public function __construct(
        private readonly UpdateMemberProfile $updateProfile,
        private readonly ConnectedDevices $devices,
        private readonly UpdateUserSettings $updateSettings,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data($this->selfPayload($user, $request) + ['device' => $this->devicePayload($user, $request)])->withHeaders(self::NO_STORE);
    }

    /**
     * Appareil de la requête (`X-Device-Id`, P15-11) : `{device_uuid, revoked, wipe}` — `wipe` = ordre d'effacement à
     * distance en attente d'accusé (`{requested_at, reason}`), null sinon. Null sans en-tête valable ou pour un appareil
     * qui n'est pas au compte.
     *
     * @return array{device_uuid: string, revoked: bool, wipe: array{requested_at: string|null, reason: string|null}|null}|null
     */
    private function devicePayload(User $user, Request $request): ?array
    {
        $uuid = trim((string) $request->header(DeviceRegistry::HEADER, ''));

        if (! Str::isUuid($uuid)) {
            return null;
        }

        $device = SyncDevice::query()->whereDeviceUuid(Str::lower($uuid))->where('user_id', $user->getKey())->first();

        return $device === null ? null : ['device_uuid' => $device->device_uuid, 'revoked' => $device->isRevoked(), 'wipe' => DeviceResource::wipe($device)];
    }

    /**
     * GET me/profile (P6-06) : le profil de la vue « moi » (UserResource), centres d'intérêt et statut allié·e compris.
     */
    public function profile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return $this->data((array) ($this->selfPayload($user, $request)['profile'] ?? []))->withHeaders(self::NO_STORE);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        ($this->updateProfile)($user, $request->toProfileData(), $request->toSensitiveData());

        return $this->data($this->selfPayload($user->fresh() ?? $user, $request))->withHeaders(self::NO_STORE);
    }

    /**
     * Vue « moi » avec genre, orientation et profil progressif (P8-08) : `GET me`, `GET · PATCH me/profile` seulement,
     * toujours no-store ; les autres réponses (connexion, OTP, réglages) s'en tiennent à userPayload().
     *
     * @return array<string, mixed>
     */
    private function selfPayload(User $user, Request $request): array
    {
        $user->loadMissing(['profile', 'settings', 'zone', 'commune', 'roles']);

        return UserResource::make($user)->asSelf()->withSensitiveProfile()->resolve($request);
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

    /**
     * DELETE me/sessions (P8-09, « Déconnecter tous les autres appareils ») : tout sauf l'accès courant.
     */
    public function destroyOtherSessions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $current = $user->currentAccessToken();

        $revoked = $this->devices->revokeOthers(
            $user,
            $current instanceof PersonalAccessToken ? (int) $current->getKey() : null,
            $request->hasSession() ? $request->session()->getId() : null,
        );

        return $this->data(['revoked' => $revoked]);
    }
}
