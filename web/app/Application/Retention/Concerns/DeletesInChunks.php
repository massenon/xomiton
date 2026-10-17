<?php

declare(strict_types=1);

namespace App\Application\Retention\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Suppression par lots d'identifiants (hébergement mutualisé : pas de DELETE massif qui verrouille la table
 * ou dépasse les 30 s). Idempotent : une ligne supprimée ne correspond plus au critère au passage suivant.
 */
trait DeletesInChunks
{
    /** Taille des lots de suppression. */
    protected const CHUNK_SIZE = 500;

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return int Nombre de lignes supprimées.
     */
    protected function deleteInChunks(Builder $query, int $chunkSize = self::CHUNK_SIZE): int
    {
        $model = $query->getModel();
        $key = $model->getKeyName();
        $deleted = 0;

        $query->clone()
            ->select([$model->getQualifiedKeyName()])
            ->chunkById($chunkSize, function (Collection $rows) use (&$deleted, $model, $key): void {
                $deleted += $model->newQuery()->whereIn($key, $rows->pluck($key)->all())->delete();
            }, $key);

        return $deleted;
    }
}
