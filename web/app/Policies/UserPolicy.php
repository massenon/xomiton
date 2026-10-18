<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\HandlesAdministrators;

/**
 * Comptes (ROLE_ET_PERMISSIONS.md §3 « Utilisateurs », RG-15) : `users.view` liste, `users.suspend` (modération),
 * `users.manage` (administration), `users.validate_peers` (RG-03), `roles.assign_admin` réservé au superadmin.
 */
final class UserPolicy
{
    use HandlesAdministrators;

    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->is($target) || $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return true;
        }

        return $user->can('users.manage') && $this->mayActOn($user, $target);
    }

    public function suspend(User $user, User $target): bool
    {
        return $user->can('users.suspend') && $this->mayActOn($user, $target);
    }

    public function reactivate(User $user, User $target): bool
    {
        return $this->suspend($user, $target);
    }

    public function validatePeer(User $user, User $target): bool
    {
        return $user->can('users.validate_peers') && ! $user->is($target);
    }

    /**
     * Attribution d'un rôle sans accès à l'administration (membre, pair).
     */
    public function assignRole(User $user, User $target): bool
    {
        return $user->can('users.manage') && $this->mayActOn($user, $target);
    }

    /**
     * RG-15 : seuls les comptes détenant `roles.assign_admin` attribuent ou retirent admin / superadmin.
     */
    public function assignAdminRole(User $user, User $target): bool
    {
        return $user->can('roles.assign_admin') && ! $user->is($target);
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->can('users.manage') && $this->mayActOn($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.manage') && $this->mayActOn($user, $target);
    }

    /**
     * Suppression de son propre compte (DELETE me, P6-06) : un compte de l'équipe d'administration (`admin.access`) ne
     * se supprime pas lui-même — son rôle doit d'abord lui être retiré (RG-15 : jamais d'administration sans superadmin,
     * plages de garde et alertes routées à reprendre). Le superadmin passe aussi par ici (Gate::before s'abstient sur
     * son propre compte).
     */
    public function deleteOwnAccount(User $user, User $target): bool
    {
        return $user->is($target) && ! $this->isAdministrator($user);
    }

    public function export(User $user): bool
    {
        return $user->can('users.manage');
    }
}
