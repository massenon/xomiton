<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * Garde-fous communs aux actions sur les comptes (RG-15, P12-01) : un compte qui accède à l'administration
 * ne peut être suspendu, supprimé ou changé de rôle que par un compte détenant `roles.assign_admin`.
 * Aucun nom de rôle : seule la permission `admin.access` distingue l'équipe d'administration.
 */
trait HandlesAdministrators
{
    protected function isAdministrator(User $user): bool
    {
        return $user->can('admin.access');
    }

    protected function mayActOn(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        return ! $this->isAdministrator($target) || $actor->can('roles.assign_admin');
    }
}
