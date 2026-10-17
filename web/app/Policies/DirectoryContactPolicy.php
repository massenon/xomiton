<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DirectoryContact;
use App\Models\User;

/**
 * Annuaire dynamique : `directory.view` (contacts publics pour les membres), `directory.manage` pour tout le reste.
 */
final class DirectoryContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('directory.view') || $user->can('directory.manage');
    }

    public function view(User $user, DirectoryContact $contact): bool
    {
        if ($user->can('directory.manage')) {
            return true;
        }

        if ($contact->user_id !== null && (int) $contact->user_id === (int) $user->getKey()) {
            return true;
        }

        return $user->can('directory.view') && $contact->is_public && $contact->is_active;
    }

    public function create(User $user): bool
    {
        return $user->can('directory.manage');
    }

    public function update(User $user, DirectoryContact $contact): bool
    {
        return $user->can('directory.manage');
    }

    public function delete(User $user, DirectoryContact $contact): bool
    {
        return $user->can('directory.manage');
    }

    public function import(User $user): bool
    {
        return $user->can('directory.manage');
    }

    public function export(User $user): bool
    {
        return $user->can('directory.manage');
    }
}
