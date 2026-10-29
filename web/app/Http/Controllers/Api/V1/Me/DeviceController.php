<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\DeviceRegistry;
use App\Application\Sync\Admin\RequestDeviceWipe;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\StoreDeviceRequest;
use App\Http\Requests\Api\Me\WipeDeviceRequest;
use App\Http\Resources\Me\DeviceResource;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * /api/v1/me/devices (P6-09) : appareils de l'application (`sync_devices`) — liste, enregistrement idempotent
 * (201 à la création, 200 ensuite ; 403 `device_revoked`, 422 `device_taken`), révocation (204). L'UUID enregistré est
 * ensuite envoyé dans l'en-tête `X-Device-Id` (SOS, signalements, `sync/*`).
 */
final class DeviceController extends ApiController
{
    public function __construct(private readonly DeviceRegistry $devices) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $rows = $user->syncDevices()->orderByDesc('last_seen_at')->orderByDesc('id')->get();

        return $this->respond(DeviceResource::collection($rows), meta: ['total' => $rows->count()]);
    }

    public function store(StoreDeviceRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$device, $created] = $this->devices->register($user, $request->toData());

        $resource = new DeviceResource($device);

        return $created ? $this->created($resource, route('api.me.devices.index')) : $this->respond($resource);
    }

    /**
     * `POST me/devices/{uuid}/wipe` (P15-11) : la personne efface à distance un de ses autres appareils (perdu, volé) ; mot de
     * passe ressaisi. Idempotent (un ordre existant est rendu tel quel) ; 404 si l'appareil n'est pas à elle ; 422 si c'est
     * l'appareil de la requête (`X-Device-Id`) : elle se déconnecterait elle-même.
     */
    public function wipe(WipeDeviceRequest $request, string $uuid, RequestDeviceWipe $wipe): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->devices->owned($user, $uuid);

        if (! $device instanceof SyncDevice) {
            throw new NotFoundHttpException;
        }

        if (Str::lower(trim((string) $request->header(DeviceRegistry::HEADER, ''))) === $device->device_uuid) {
            throw ValidationException::withMessages(['device_uuid' => [(string) __('account.devices.current')]]);
        }

        $already = $device->wipe_requested_at !== null;
        $wipe->byOwner($user, $device);

        return $this->respond(new DeviceResource($device->refresh()), $already ? 200 : 202, ['already_requested' => $already], headers: ['Cache-Control' => 'no-store, private']);
    }

    /**
     * `POST me/devices/{uuid}/wiped` (P15-11) : l'appareil accuse l'effacement de ses données locales. Idempotent ;
     * accepté pour un appareil révoqué par l'ordre d'effacement (c'est son seul rôle) ; 404 si l'appareil n'est pas à moi.
     */
    public function wiped(Request $request, string $uuid): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->devices->acknowledgeWipe($user, $uuid);

        if (! $device instanceof SyncDevice) {
            throw new NotFoundHttpException;
        }

        return $this->respond(new DeviceResource($device), headers: ['Cache-Control' => 'no-store, private']);
    }

    public function destroy(Request $request, string $uuid): Response
    {
        /** @var User $user */
        $user = $request->user();
        $device = $this->devices->owned($user, $uuid);

        if (! $device instanceof SyncDevice) {
            throw new NotFoundHttpException;
        }

        Gate::forUser($user)->authorize('delete', $device);
        $this->devices->revoke($user, $uuid);

        return $this->noContent();
    }
}
