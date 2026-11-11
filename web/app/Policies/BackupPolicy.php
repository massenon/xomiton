<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Backup;
use App\Models\User;

/**
 * Sauvegardes (ROLE_ET_PERMISSIONS.md §3) : admin crée, prévisualise et télécharge ; restauration et suppression
 * exigent `backups.restore` / `backups.delete` (superadmin).
 */
final class BackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('backups.create') || $user->can('backups.download');
    }

    public function view(User $user, Backup $backup): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can('backups.create');
    }

    public function download(User $user, Backup $backup): bool
    {
        return $user->can('backups.download');
    }

    public function restore(User $user, Backup $backup): bool
    {
        return $user->can('backups.restore');
    }

    public function delete(User $user, Backup $backup): bool
    {
        return $user->can('backups.delete');
    }
}
