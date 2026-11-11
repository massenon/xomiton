<?php

declare(strict_types=1);

namespace App\Policies\Scopes;

use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cloisonnement des dossiers (ROLE_ET_PERMISSIONS.md §5) : `cases.view_all` voit tout, sinon seulement
 * les dossiers collectés par le compte (`cases.collected_by`). À brancher sur `CaseRecord::visibleTo($user)`.
 */
final class CaseRecordScope
{
    private function __construct() {}

    /**
     * @param  Builder<CaseRecord>|null  $query
     * @return Builder<CaseRecord>
     */
    public static function visibleTo(User $user, ?Builder $query = null): Builder
    {
        $query ??= CaseRecord::query();

        if ($user->can('cases.view_all')) {
            return $query;
        }

        if ($user->can('cases.create') || $user->can('cases.update_own')) {
            return $query->where('collected_by', $user->getKey());
        }

        return $query->whereRaw('1 = 0');
    }
}
