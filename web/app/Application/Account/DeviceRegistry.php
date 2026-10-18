<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Domain\Account\Exceptions\DeviceRevoked;
use App\Domain\Account\Exceptions\DeviceTaken;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Appareils de l'application (`sync_devices`, P6-09) : enregistrement idempotent (POST me/devices : créé une fois puis
 * mis à jour — libellé, plateforme, version, `last_seen_at`), révocation (DELETE me/devices/{uuid} : `is_revoked`,
 * jamais supprimé car `alerts.device_id`, `cases.device_id` et `sync_outbox_receipts` y renvoient ; ses abonnements
 * push `push_subscriptions.sync_device_id` sont supprimés) et résolution de l'appareil d'une requête par l'en-tête
 * `X-Device-Id` (SOS et signalements non anonymes, abonnements push, `sync/*` en P9).
 * Journal : `device.registered` / `device.revoked` avec le libellé, jamais l'UUID complet.
 */
final class DeviceRegistry
{
    /** En-tête portant le `device_uuid` de l'application sur les requêtes authentifiées. */
    public const HEADER = 'X-Device-Id';

    /**
     * @return array{0: SyncDevice, 1: bool} l'appareil et vrai s'il vient d'être créé
     *
     * @throws DeviceTaken
     * @throws DeviceRevoked
     */
    public function register(User $user, DeviceData $data): array
    {
        $uuid = Str::lower($data->uuid);
        $attributes = [
            'label' => Str::limit(trim($data->label), 80, ''),
            'platform' => $data->platform === null ? null : Str::limit(trim($data->platform), 40, ''),
            'app_version' => $data->appVersion === null ? null : Str::limit(trim($data->appVersion), 20, ''),
            'last_seen_at' => now(),
        ];

        $existing = SyncDevice::query()->whereDeviceUuid($uuid)->first();

        if ($existing !== null) {
            return [$this->refresh($user, $existing, $attributes), false];
        }

        try {
            $device = DB::transaction(fn (): SyncDevice => SyncDevice::query()->create(['user_id' => $user->getKey(), 'device_uuid' => $uuid, 'is_revoked' => false, 'pending_count' => 0, 'conflicts_count' => 0] + $attributes));
        } catch (UniqueConstraintViolationException) {
            // Deux enregistrements simultanés du même appareil : le second met à jour le premier.
            $device = SyncDevice::query()->whereDeviceUuid($uuid)->firstOrFail();

            return [$this->refresh($user, $device, $attributes), false];
        }

        Audit::log('device.registered', $device, 'appareil='.$device->label, $user);

        return [$device, true];
    }

    public function revoke(User $user, string $uuid): ?SyncDevice
    {
        $device = $this->owned($user, $uuid);

        if ($device === null) {
            return null;
        }

        if (! $device->isRevoked()) {
            $device->forceFill(['is_revoked' => true])->save();
            Audit::log('device.revoked', $device, 'appareil='.$device->label, $user);
        }

        // P6-09 : un appareil révoqué (perdu, partagé) n'est plus notifié, même si son jeton FCM n'a pas été retiré.
        PushSubscription::query()->where('sync_device_id', $device->getKey())->delete();

        return $device;
    }

    public function owned(User $user, string $uuid): ?SyncDevice
    {
        return SyncDevice::query()->whereDeviceUuid(Str::lower($uuid))->where('user_id', $user->getKey())->first();
    }

    /**
     * Identifiant de l'appareil actif annoncé par `X-Device-Id` pour ce compte ; null sinon (en-tête absent ou
     * invalide, appareil inconnu, révoqué ou d'un autre compte) : l'alerte part quand même, sans appareil.
     * **Lecture seule** : `last_seen_at` n'est tenu que par POST me/devices — un horodatage écrit ici à la seconde d'un
     * signalement anonyme relierait ce signalement au compte (RG-01).
     */
    public function resolve(?User $user, Request $request): ?int
    {
        $uuid = trim((string) $request->header(self::HEADER, ''));

        if ($user === null || ! Str::isUuid($uuid)) {
            return null;
        }

        $id = SyncDevice::query()->whereDeviceUuid(Str::lower($uuid))->where('user_id', $user->getKey())->active()->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws DeviceTaken
     * @throws DeviceRevoked
     */
    private function refresh(User $user, SyncDevice $device, array $attributes): SyncDevice
    {
        if ((int) $device->user_id !== (int) $user->getKey()) {
            throw new DeviceTaken;
        }

        if ($device->isRevoked()) {
            throw new DeviceRevoked;
        }

        $device->forceFill($attributes)->save();

        return $device;
    }
}
