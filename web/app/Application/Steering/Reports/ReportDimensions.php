<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Enums\AgeRange;
use App\Enums\Sex;
use Illuminate\Database\Query\Builder;

/**
 * Désagrégations d'un rapport (prototype admin/rapports.php : département, commune, type de population, sexe, tranche
 * d'âge) : jointure et colonne en clair de chacune. Les colonnes lues (noms de référentiels, sexe, tranche d'âge) ne
 * sont jamais chiffrées.
 */
final class ReportDimensions
{
    /** @var list<string> */
    public const KEYS = ['departement', 'commune', 'population', 'sexe', 'age'];

    private function __construct() {}

    public static function label(string $key): string
    {
        return (string) __('steering.reports.dimensions.'.$key);
    }

    /**
     * Ajoute la jointure de la dimension à la requête sur `cases` et renvoie [expression SQL de la valeur].
     *
     * @return array{0: string}
     */
    public static function select(Builder $query, string $key, string $alias): array
    {
        return match ($key) {
            'departement' => [self::join($query, 'zones', 'cases.zone_id', 'dz_'.$alias, 'name')],
            'commune' => [self::join($query, 'communes', 'cases.commune_id', 'dc_'.$alias, 'name')],
            'population' => [self::join($query, 'populations', 'cases.population_id', 'dp_'.$alias, 'label')],
            'sexe' => ['cases.sex'],
            default => ['cases.age_range'],
        };
    }

    /** Libellé affiché de la valeur brute (énumération traduite, référentiel tel quel, « Non renseigné » si vide). */
    public static function display(string $key, mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return (string) __('steering.reports.unknown');
        }

        return match ($key) {
            'sexe' => Sex::tryFrom((string) $raw)?->label() ?? (string) $raw,
            'age' => AgeRange::tryFrom((string) $raw)?->label() ?? (string) $raw,
            default => (string) $raw,
        };
    }

    private static function join(Builder $query, string $table, string $foreign, string $alias, string $column): string
    {
        $query->leftJoin($table.' as '.$alias, $alias.'.id', '=', $foreign);

        return $alias.'.'.$column;
    }
}
