<?php

declare(strict_types=1);

namespace App\Application\Cases\Queries;

use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Alertes routées vers moi (P9-02, `GET pair/alerts` ; prototype espace-pair.php l.20-26) : celles dont le contact
 * routé est une fiche d'annuaire liée au compte (`directory_contacts.user_id`, branche `alerts.view_routed`
 * d'AlertScope) — **jamais toutes les alertes, même pour un compte `alerts.view`** : l'espace pair est personnel,
 * l'administration des alertes est en P10. Sans `alerts.view_routed`, aucune. `state=open` (défaut) : non
 * terminées ; `state=all` : toutes. Les plus récentes d'abord.
 */
final class ListRoutedAlerts
{
    public const STATE_OPEN = 'open';

    public const STATE_ALL = 'all';

    /**
     * @return LengthAwarePaginator<int, Alert>
     */
    public function page(User $viewer, string $state, int $perPage, int $page = 1): LengthAwarePaginator
    {
        return $this->query($viewer, $state)
            ->with(['zone:id,name', 'commune:id,name'])
            ->latestFirst()
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return Builder<Alert>
     */
    public function query(User $viewer, string $state = self::STATE_OPEN): Builder
    {
        $query = Alert::query();

        if (! $viewer->can('alerts.view_routed')) {
            return $query->whereRaw('1 = 0');
        }

        $query->whereIn('routed_to', DirectoryContact::query()->select('id')->where('user_id', $viewer->getKey()));

        if ($state !== self::STATE_ALL) {
            $query->open();
        }

        return $query;
    }
}
