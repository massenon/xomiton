<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * Paramètres système (ROLE_ET_PERMISSIONS.md §3) : l'administration lit (`settings.view`) ; toute modification,
 * dont le SMTP, exige `settings.manage` (superadmin). La purge du journal relève d'AuditLogPolicy::purge.
 */
final class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view');
    }

    public function view(User $user, Setting $setting): bool
    {
        if ($setting->isSecret()) {
            return $user->can('settings.manage');
        }

        return $user->can('settings.view');
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->can('settings.manage');
    }

    /** Écran Paramètres (P13-04) : écriture des clés du catalogue. */
    public function manage(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function updateSmtp(User $user): bool
    {
        return $user->can('settings.manage');
    }

    public function testSmtp(User $user): bool
    {
        return $user->can('settings.manage');
    }

    /** Mode maintenance (P13-06) : admin et superadmin, les rôles exemptés pendant la maintenance (RG-14). */
    public function toggleMaintenance(User $user): bool
    {
        return $user->can('platform.maintenance');
    }
}
