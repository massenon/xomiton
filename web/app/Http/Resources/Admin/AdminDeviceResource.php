<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\SyncDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Appareil d'un compte vu par l'administration mobile (P15-11) : libellé, plateforme, version, derniers contacts et état
 * de l'effacement à distance. **Jamais** l'UUID de l'appareil (secret de l'application) ni de donnée du compte ; l'identifiant
 * numérique suffit à désigner l'appareil dans `POST admin/devices/{id}/wipe`.
 *
 * @mixin SyncDevice
 */
final class AdminDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SyncDevice $device */
        $device = $this->resource;
        $viewer = $request->user();

        return [
            'id' => $device->getKey(),
            'label' => $device->label,
            'platform' => $device->platform,
            'app_version' => $device->app_version,
            'revoked' => $device->isRevoked(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_sync_at' => $device->last_sync_at?->toIso8601String(),
            'wipe_pending' => $device->wipePending(),
            'wipe_requested_at' => $device->wipe_requested_at?->toIso8601String(),
            'wipe_reason' => $device->wipe_reason,
            'wiped_at' => $device->wiped_at?->toIso8601String(),
            'can' => ['wipe' => $viewer !== null && $viewer->can('wipe', $device) && $device->wipe_requested_at === null],
        ];
    }
}
