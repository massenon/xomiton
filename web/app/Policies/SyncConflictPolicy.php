<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SyncConflict;
use App\Models\User;

/**
 * Conflits de synchronisation (P13-01, ROLE_ET_PERMISSIONS.md §3) : l'administration (`sync.manage`) les consulte et les
 * tranche ; les pair·es ne voient que ceux de leurs appareils, par l'API (SyncConflictResource), jamais ici.
 */
final class SyncConflictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sync.manage');
    }

    public function resolve(User $user, SyncConflict $conflict): bool
    {
        return $user->can('sync.manage');
    }
}
