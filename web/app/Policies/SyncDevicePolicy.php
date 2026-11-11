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
    /** Écran d'administration Hors connexion & synchro (P13-01) : `sync.manage` (admin, superadmin). */
    public function viewAny(User $user): bool
    {
        return $user->can('sync.manage');
    }

    /** Révocation d'un appareil par l'administration (P13-01). */
    public function revoke(User $user, SyncDevice $device): bool
    {
        return $user->can('sync.manage');
    }

    /** Effacement à distance (P15-11) : `device.wipe` (admin et superadmin). */
    public function wipe(User $user, SyncDevice $device): bool
    {
        return $user->can('device.wipe');
    }

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
