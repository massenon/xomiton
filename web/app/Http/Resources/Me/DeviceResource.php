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
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'created_at' => $device->created_at?->toIso8601String(),
        ];
    }
}
