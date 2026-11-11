<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoleInvitation;
use App\Models\User;

/**
 * Invitations à l'équipe : le rôle `pair` relève de `peers.invite`, les rôles de modération et d'administration de
 * `roles.assign_admin` (RG-15, super-admin seulement). Une invitation se lit et s'annule avec la même permission que
 * celle qui l'a créée.
 */
final class RoleInvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('peers.invite') || $user->can('roles.assign_admin');
    }

    public function invite(User $user, string $role): bool
    {
        return $role === 'pair' ? $user->can('peers.invite') : $user->can('roles.assign_admin');
    }

    public function view(User $user, RoleInvitation $invitation): bool
    {
        return $this->invite($user, $invitation->role);
    }

    public function revoke(User $user, RoleInvitation $invitation): bool
    {
        return $this->invite($user, $invitation->role);
    }
}
