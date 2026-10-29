<?php

declare(strict_types=1);

namespace App\Http\Resources\Me;

use App\Models\SyncDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appareil de l'application (`sync_devices`, P6-09) vu par son propriétaire.
 *
 * @mixin SyncDevice
 */
final class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SyncDevice $device */
        $device = $this->resource;

        return [
            'device_uuid' => $device->device_uuid,
            'label' => $device->label,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'revoked' => $device->isRevoked(),
            'wipe' => self::wipe($device),
            'wipe_requested_at' => $device->wipe_requested_at?->toIso8601String(),
            'wiped_at' => $device->wiped_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'created_at' => $device->created_at?->toIso8601String(),
        ];
    }

    /**
     * Ordre d'effacement en attente d'accusé (P15-11) : `{requested_at, reason}`, sinon null (aucun ordre, ou déjà accusé).
     *
     * @return array{requested_at: string|null, reason: string|null}|null
     */
    public static function wipe(SyncDevice $device): ?array
    {
        if (! $device->wipePending()) {
            return null;
        }

        return ['requested_at' => $device->wipe_requested_at?->toIso8601String(), 'reason' => $device->wipe_reason];
    }
}
