<?php

declare(strict_types=1);

namespace App\Policies\Scopes;

use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cloisonnement des alertes (ROLE_ET_PERMISSIONS.md §5) : `alerts.view` voit tout ; `alerts.view_routed`
 * (pair·es) ne voit que les alertes routées vers la fiche d'annuaire liée au compte (`directory_contacts.user_id`) **et
 * celles dont l'équipe l'a prévenu·e** (décisions client du 2026-10-29 : SOS et alertes préviennent les admins et les
 * pair·es, qui doivent pouvoir les ouvrir, accuser réception et les prendre en charge). Une alerte jamais notifiée à un·e
 * pair·e reste fermée. À brancher sur `Alert::routedTo($user)`.
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
            return $query->where(function (Builder $visible) use ($user): void {
                $visible->whereIn('routed_to', DirectoryContact::query()->select('id')->where('user_id', $user->getKey()))
                    ->orWhereIn('id', self::notifiedAlertIds($user));
            });
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * L'alerte est-elle routée vers une fiche d'annuaire liée à ce compte, ou lui a-t-elle été notifiée par l'équipe ?
     */
    public static function isRoutedTo(Alert $alert, User $user): bool
    {
        if ($alert->routed_to !== null && DirectoryContact::query()->whereKey($alert->routed_to)->where('user_id', $user->getKey())->exists()) {
            return true;
        }

        return self::notifiedAlertIds($user)->where('alert_id', $alert->getKey())->exists();
    }

    /**
     * Alertes pour lesquelles le compte a reçu une notification d'équipe (SOS, alerte routée, rappel, escalade) : jamais
     * celles où il n'a été prévenu qu'en tant qu'auteur·e (accusé, changement de statut).
     *
     * @return Builder<AlertNotification>
     */
    public static function notifiedAlertIds(User $user): Builder
    {
        return AlertNotification::query()
            ->select('alert_id')
            ->where('user_id', $user->getKey())
            ->whereIn('purpose', array_map(static fn (NotificationPurpose $purpose): string => $purpose->value, NotificationPurpose::retryable()));
    }
}
