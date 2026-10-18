<?php

declare(strict_types=1);

namespace App\Application\Alerts\Queries;

use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * « Mes signalements » (P5-06 `GET signalements/mine`, page membre P8-12, mobile P15-04) : alertes **non anonymes** du
 * compte, la plus récente d'abord. Un signalement anonyme n'est jamais rattaché au compte (RG-01 / RG-02 : `user_id`
 * vide) et ne peut donc pas être retrouvé ici, seulement par son code. Historique des statuts chargé (`history[]`
 * d'AlertResource, du plus ancien au plus récent). Lecture seule (ADR-29).
 */
final class ListAlertsOfUser
{
    /**
     * @return LengthAwarePaginator<int, Alert>
     */
    public function __invoke(User $user, ?AlertType $type = null, int $perPage = 20): LengthAwarePaginator
    {
        return Alert::query()
            ->where('user_id', $user->getKey())
            ->where('is_anonymous', false)
            ->when($type !== null, fn (Builder $query) => $query->where('type', $type))
            ->with([
                'routedContact',
                'statusChanges' => fn (HasMany $changes) => $changes->orderBy('created_at')->orderBy('id'),
            ])
            ->latestFirst()
            ->paginate($perPage);
    }
}
