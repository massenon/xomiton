<?php

declare(strict_types=1);

namespace App\Application\Sync\Admin;

use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Révocation d'un appareil par l'administration (P13-01, `sync.manage`) : perdu ou partagé, il ne synchronise plus
 * (403 `device_revoked` côté API) et ses abonnements push sont supprimés, comme à la révocation par la personne
 * (DeviceRegistry::revoke). Idempotent ; jamais supprimé ; journal `sync.device_revoked` (libellé, jamais l'UUID).
 */
final class RevokeDevice
{
    public function __invoke(User $by, SyncDevice $device): SyncDevice
    {
        if (! $by->can('sync.manage')) {
            throw new AuthorizationException;
        }

        if (! $device->isRevoked()) {
            $device->forceFill(['is_revoked' => true])->save();
            Audit::log('sync.device_revoked', $device, 'appareil='.$device->label, $by);
        }

        PushSubscription::query()->where('sync_device_id', $device->getKey())->delete();

        return $device;
    }
}
