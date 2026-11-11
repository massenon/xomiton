<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Masquage des petits effectifs (dashboard-admin.md « KPI », P10-04 ; repris par les indicateurs P10-06 et les
 * exports) : un effectif de 1 à 4 personnes n'est jamais affiché, ni dans un KPI ni dans un graphique, pour qu'une
 * cellule ne permette pas de reconnaître quelqu'un. Zéro reste affiché (aucune personne à protéger) ; 5 et plus aussi.
 * Aucun total n'est publié à côté d'un graphique, et un groupe de cellules qui se somment (série d'un graphique, KPI
 * ouverts / en cours / clos) passe par `maskGroup()` : une cellule masquée seule se retrouverait par différence avec un
 * total connu par ailleurs (un autre graphique, la somme des KPI), donc la plus petite cellule restante est masquée
 * avec elle (suppression complémentaire) ; seule la somme des deux reste déductible, jamais l'une d'elles.
 */
final class SmallCellMask
{
    /** Seuil : un effectif strictement inférieur est masqué. */
    public const MIN = 5;

    private function __construct() {}

    public static function isMasked(int $count): bool
    {
        return $count > 0 && $count < self::MIN;
    }

    /** Effectif affichable, ou null quand il est masqué. */
    public static function mask(int $count): ?int
    {
        return self::isMasked($count) ? null : $count;
    }

    /**
     * @param  array<array-key, int>  $counts
     * @return array<array-key, int|null>
     */
    public static function maskAll(array $counts): array
    {
        return array_map(static fn (int $count): ?int => self::mask($count), $counts);
    }

    /**
     * Masque un groupe de cellules dont le total peut être connu par ailleurs : cellules de 1 à 4 masquées, puis, si une
     * seule l'est, suppression complémentaire de la plus petite autre cellule (non nulle d'abord, la première en cas
     * d'égalité). Un groupe d'une seule cellule n'a rien à compléter. Les clés sont conservées.
     *
     * @param  array<array-key, int>  $counts
     * @return array<array-key, int|null>
     */
    public static function maskGroup(array $counts): array
    {
        $masked = array_map(static fn (int $count): bool => self::isMasked($count), $counts);

        if (count(array_filter($masked)) === 1) {
            $candidate = null;

            foreach ($counts as $key => $count) {
                if ($masked[$key]) {
                    continue;
                }

                // Une cellule à zéro ne protège personne : elle ne passe qu'après les cellules non nulles.
                $rank = $count === 0 ? PHP_INT_MAX : $count;

                if ($candidate === null || $rank < $candidate[1]) {
                    $candidate = [$key, $rank];
                }
            }

            if ($candidate !== null) {
                $masked[$candidate[0]] = true;
            }
        }

        $result = [];

        foreach ($counts as $key => $count) {
            $result[$key] = $masked[$key] ? null : $count;
        }

        return $result;
    }
}
