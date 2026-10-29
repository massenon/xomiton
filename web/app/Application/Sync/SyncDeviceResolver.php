<?php

declare(strict_types=1);

namespace App\Application\Sync;

use App\Application\Account\DeviceRegistry;
use App\Domain\Account\Exceptions\DeviceRevoked;
use App\Domain\Sync\Exceptions\DeviceRequired;
use App\Models\SyncDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Appareil d'une requête `sync/*` ou `heartbeat` (P9-06, ADR-26 : synchronisation réservée aux appareils authentifiés
 * `sync_devices`) : `X-Device-Id` d'un appareil du compte connecté ; absent ou étranger → 403 `device_required`,
 * révoqué → 403 `device_revoked`. Le dernier contact (`last_seen_at`) et la dernière synchronisation (`last_sync_at`)
 * sont tenus ici : un lot `sync/push` ne porte jamais d'élément anonyme (RG-01), l'horodatage ne relie donc aucun
 * signalement anonyme au compte.
 */
final class SyncDeviceResolver
{
    /**
     * @throws DeviceRequired
     * @throws DeviceRevoked
     */
    public function resolve(User $user, Request $request): SyncDevice
    {
        $uuid = trim((string) $request->header(DeviceRegistry::HEADER, ''));

        if (! Str::isUuid($uuid)) {
            throw new DeviceRequired;
        }

        $device = SyncDevice::query()->whereDeviceUuid(Str::lower($uuid))->where('user_id', $user->getKey())->first();

        if ($device === null) {
            throw new DeviceRequired;
        }

        if ($device->isRevoked()) {
            throw new DeviceRevoked;
        }

        return $device;
    }

    /**
     * Comme resolve(), mais un appareil révoqué par un ordre d'effacement qui attend son accusé est rendu tel quel : il
     * doit pouvoir lire l'ordre (`heartbeat`, P15-11). Tout autre appareil révoqué : 403 `device_revoked`.
     *
     * @throws DeviceRequired
     * @throws DeviceRevoked
     */
    public function resolveWithWipe(User $user, Request $request): SyncDevice
    {
        $uuid = trim((string) $request->header(DeviceRegistry::HEADER, ''));
        $device = Str::isUuid($uuid)
            ? SyncDevice::query()->whereDeviceUuid(Str::lower($uuid))->where('user_id', $user->getKey())->first()
            : null;

        if ($device instanceof SyncDevice && $device->isRevoked() && $device->wipePending()) {
            return $device;
        }

        return $this->resolve($user, $request);
    }

    /**
     * Dernier contact ; `synced` : l'appareil a aussi poussé ou tiré des données. `pending` : éléments encore dans sa
     * boîte d'envoi (annoncé par l'appareil, borné).
     */
    public function touch(SyncDevice $device, bool $synced, ?int $pending = null): void
    {
        $attributes = ['last_seen_at' => now()];

        if ($synced) {
            $attributes['last_sync_at'] = now();
        }

        if ($pending !== null) {
            $attributes['pending_count'] = max(0, min($pending, 1_000_000));
        }

        $device->forceFill($attributes)->save();
    }
}
