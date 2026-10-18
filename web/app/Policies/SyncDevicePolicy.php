<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SyncDevice;
use App\Models\User;

/**
 * Appareils de l'application (P6-09) : la personne voit, met à jour et révoque ses propres appareils ; un appareil
 * révoqué ne se met plus à jour (403 `device_revoked` côté API). La gestion par l'administration (P13, `sync.manage`)
 * viendra avec son écran.
 */
final class SyncDevicePolicy
{
    public function view(User $user, SyncDevice $device): bool
    {
        return $this->owns($user, $device);
    }

    public function update(User $user, SyncDevice $device): bool
    {
        return $this->owns($user, $device) && ! $device->isRevoked();
    }

    public function delete(User $user, SyncDevice $device): bool
    {
        return $this->owns($user, $device);
    }

    private function owns(User $user, SyncDevice $device): bool
    {
        return (int) $device->user_id === (int) $user->getKey();
    }
}
