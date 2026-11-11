<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Journal d'audit (P2-09) : lecture et export avec `audit_logs.view`, purge planifiée avec `audit_logs.purge` ;
 * aucune création manuelle, modification ni suppression unitaire, pour personne (le modèle est immuable).
 */
final class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_logs.view');
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->can('audit_logs.view');
    }

    public function export(User $user): bool
    {
        return $user->can('audit_logs.view');
    }

    public function purge(User $user): bool
    {
        return $user->can('audit_logs.purge');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
