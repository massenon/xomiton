<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Sync\Admin\RequestDeviceWipe;
use App\Http\Resources\Admin\AdminDeviceResource;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Appareils d'un compte et effacement à distance (`admin/members/{id}/devices`, `admin/devices/{id}/wipe`, P15-11 ;
 * permission `device.wipe`, admin et superadmin). L'ordre d'effacement marque l'appareil et le révoque (RequestDeviceWipe,
 * le même cas d'usage que l'écran Hors connexion & synchro) ; l'appareil le lit au prochain `GET me` / `heartbeat`, vide
 * ses données et accuse. Confirmation `step-up`, journal `device.wipe_requested` (`source=mobile`).
 */
final class DeviceController extends AdminApiController
{
    public function index(Request $request, User $user): JsonResponse
    {
        $admin = $this->admin($request);
        abort_unless($admin->can('device.wipe'), 403);

        $devices = $user->syncDevices()->orderByDesc('last_seen_at')->orderByDesc('id')->limit(20)->get();

        return $this->data(
            $devices->map(static fn (SyncDevice $device): array => AdminDeviceResource::make($device)->resolve($request))->values()->all(),
            200,
            ['total' => $devices->count()],
        );
    }

    public function wipe(Request $request, SyncDevice $device, RequestDeviceWipe $wipe): JsonResponse
    {
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('wipe', $device);
        $this->confirm($request);

        $already = $device->wipe_requested_at !== null;
        $wipe($admin, $device);

        return $this->data(AdminDeviceResource::make($device->refresh())->resolve($request), $already ? 200 : 202, ['already_requested' => $already]);
    }
}
