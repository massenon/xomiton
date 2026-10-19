<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Pierre tombale de synchronisation (P9-06, DATABASE_DICTIONARY.md §10 `sync_tombstones`) : enregistrement d'une
 * collection de `GET sync/pull` supprimé définitivement (`user_id` null : pour tous les appareils) ou sorti de la
 * portée d'un compte (`user_id` : dossier réattribué, alerte routée ailleurs). Écrite par
 * App\Application\Sync\Pull\TombstoneRecorder, purgée par `sync:prune` après `xomiton.sync.tombstone_days`.
 */
class SyncTombstone extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'collection',
        'subject_key',
        'user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Pierres tombales d'une collection qui concernent ce compte (pour tout le monde, ou pour lui seul).
     *
     * @param  Builder<SyncTombstone>  $query
     */
    public function scopeFor(Builder $query, string $collection, int $userId): void
    {
        $query->where('collection', $collection)
            ->where(fn (Builder $scope) => $scope->whereNull('user_id')->orWhere('user_id', $userId));
    }

    /**
     * @param  Builder<SyncTombstone>  $query
     */
    public function scopeOlderThan(Builder $query, int $days): void
    {
        $query->where('created_at', '<', now()->subDays($days));
    }
}
