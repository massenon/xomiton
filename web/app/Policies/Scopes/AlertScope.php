<?php

declare(strict_types=1);

namespace App\Policies\Scopes;

use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cloisonnement des alertes (ROLE_ET_PERMISSIONS.md §5) : `alerts.view` voit tout ; `alerts.view_routed`
 * ne voit que les alertes routées vers la fiche d'annuaire liée au compte (`directory_contacts.user_id`).
 * À brancher sur `Alert::routedTo($user)`.
 */
final class AlertScope
{
    private function __construct() {}

    /**
     * @param  Builder<Alert>|null  $query
     * @return Builder<Alert>
     */
    public static function routedTo(User $user, ?Builder $query = null): Builder
    {
        $query ??= Alert::query();

        if ($user->can('alerts.view')) {
            return $query;
        }

        if ($user->can('alerts.view_routed')) {
            return $query->whereIn(
                'routed_to',
                DirectoryContact::query()->select('id')->where('user_id', $user->getKey()),
            );
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * L'alerte est-elle routée vers une fiche d'annuaire liée à ce compte ?
     */
    public static function isRoutedTo(Alert $alert, User $user): bool
    {
        if ($alert->routed_to === null) {
            return false;
        }

        return DirectoryContact::query()
            ->whereKey($alert->routed_to)
            ->where('user_id', $user->getKey())
            ->exists();
    }
}
