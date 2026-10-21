<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AppVersion;
use App\Models\User;

/**
 * Versions de la plateforme (P13-02, ROLE_ET_PERMISSIONS.md §3) : l'administration lit (`updates.view`) ; publier,
 * planifier, déployer, annuler, régler la version minimale mobile et lancer l'optimisation exigent `updates.manage`
 * (superadmin).
 */
final class AppVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('updates.view');
    }

    public function create(User $user): bool
    {
        return $user->can('updates.manage');
    }

    public function update(User $user, AppVersion $version): bool
    {
        return $user->can('updates.manage');
    }
}
