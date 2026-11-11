<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use Illuminate\Support\Facades\Schema;

/**
 * Tables de la base dans l'ordre des clés étrangères (parents avant enfants : lots 1 à 10 du schéma, calculé depuis la
 * base plutôt que figé). Les cycles et auto-références sont rangés en fin, par ordre alphabétique : la restauration
 * lève de toute façon les contrôles de clés étrangères quand le pilote le permet.
 */
final class TableOrder
{
    /**
     * @param  list<string>  $exclude  tables à ne pas lister
     * @return list<string>
     */
    public function forward(array $exclude = []): array
    {
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $name = (string) $table['name'];

            if (! in_array($name, $exclude, true) && ! str_starts_with($name, 'sqlite_')) {
                $tables[] = $name;
            }
        }

        sort($tables);

        $parents = [];

        foreach ($tables as $table) {
            $parents[$table] = [];

            foreach (Schema::getForeignKeys($table) as $foreign) {
                $parent = (string) $foreign['foreign_table'];

                if ($parent !== $table && in_array($parent, $tables, true)) {
                    $parents[$table][$parent] = true;
                }
            }
        }

        $ordered = [];
        $remaining = $tables;

        while ($remaining !== []) {
            $ready = array_values(array_filter($remaining, static function (string $table) use ($parents, $ordered): bool {
                return array_diff_key($parents[$table], array_flip($ordered)) === [];
            }));

            if ($ready === []) {
                // Cycle : le reste suit par ordre alphabétique.
                $ordered = [...$ordered, ...$remaining];

                break;
            }

            $ordered = [...$ordered, ...$ready];
            $remaining = array_values(array_diff($remaining, $ready));
        }

        return $ordered;
    }

    /**
     * @param  list<string>  $exclude
     * @return list<string>
     */
    public function reverse(array $exclude = []): array
    {
        return array_reverse($this->forward($exclude));
    }
}
