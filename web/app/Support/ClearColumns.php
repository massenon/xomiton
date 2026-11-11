<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * ADR-33 : aucune recherche ni aucun tri sur une colonne chiffrée. Une colonne l'est si le modèle la caste en
 * `encrypted*` ou si `config('xomiton.encrypted_columns')` la liste pour sa table (colonne qualifiée `table.colonne`).
 * Même contrôle que WithDataTable, pour les recherches hors tableau de données (recherche globale d'administration).
 */
final class ClearColumns
{
    private function __construct() {}

    /**
     * @param  list<string>  $columns
     *
     * @throws LogicException une colonne est chiffrée
     */
    public static function assert(Model $model, array $columns, string $usage = 'recherche'): void
    {
        $casts = $model->getCasts();
        $encrypted = (array) config('xomiton.encrypted_columns', []);

        foreach ($columns as $column) {
            $qualified = str_contains($column, '.');
            $table = $qualified ? substr($column, 0, (int) strrpos($column, '.')) : $model->getTable();
            $name = $qualified ? substr($column, (int) strrpos($column, '.') + 1) : $column;
            $cast = $table === $model->getTable() ? ($casts[$name] ?? null) : null;

            if ((is_string($cast) && str_starts_with($cast, 'encrypted')) || in_array($name, (array) ($encrypted[$table] ?? []), true)) {
                throw new LogicException(sprintf('Colonne chiffrée « %s.%s » interdite en %s (ADR-33).', $table, $name, $usage));
            }
        }
    }
}
