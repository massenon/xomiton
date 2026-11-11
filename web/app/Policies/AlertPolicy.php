<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;
use App\Policies\Scopes\AlertScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alertes et signalements (ROLE_ET_PERMISSIONS.md §4 l.72, P2-07) : `alerts.view` voit tout ; un pair
 * (`alerts.view_routed`) ne voit, prend en charge (`alerts.take`) et clôt (`alerts.close`) que les alertes routées
 * vers sa fiche d'annuaire ; réattribution, notes, escalade et export relèvent de `alerts.manage`.
 */
final class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('alerts.view') || $user->can('alerts.view_routed');
    }

    public function view(User $user, Alert $alert): bool
    {
        if ($user->can('alerts.view')) {
            return true;
        }

        return $user->can('alerts.view_routed') && AlertScope::isRoutedTo($alert, $user);
    }

    public function take(User $user, Alert $alert): bool
    {
        return $user->can('alerts.take') && $this->view($user, $alert);
    }

    public function close(User $user, Alert $alert): bool
    {
        return $user->can('alerts.close') && $this->view($user, $alert);
    }

    public function reassign(User $user, Alert $alert): bool
    {
        return $user->can('alerts.manage');
    }

    public function note(User $user, Alert $alert): bool
    {
        return $user->can('alerts.manage');
    }

    public function escalate(User $user, Alert $alert): bool
    {
        return $user->can('alerts.manage');
    }

    public function export(User $user): bool
    {
        return $user->can('alerts.manage');
    }

    public function delete(User $user, Alert $alert): bool
    {
        return $user->can('alerts.manage');
    }

    /**
     * Requête des alertes visibles, identique à `view()` ligne par ligne.
     *
     * @return Builder<Alert>
     */
    public function scope(User $user): Builder
    {
        return AlertScope::routedTo($user);
    }
}
