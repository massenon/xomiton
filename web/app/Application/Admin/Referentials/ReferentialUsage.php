<?php

declare(strict_types=1);

namespace App\Application\Admin\Referentials;

use App\Models\Commune;
use App\Models\Need;
use App\Models\Population;
use App\Models\ViolenceType;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Où un élément de référentiel est utilisé (dossiers, alertes, profils, gardes, annuaire, groupes, indicateurs, invitations) :
 * un élément utilisé n'est jamais supprimé, on le désactive. Les comptes se font en SQL direct (`DB::table`) pour compter aussi
 * les lignes archivées (dossiers supprimés logiquement) et ne dépendre d'aucun scope global ; ils sont groupés par
 * identifiant pour éviter une requête par ligne de tableau.
 */
final class ReferentialUsage
{
    /** @var list<string> Tables dont la colonne `zone_id` référence une zone. */
    public const ZONE_TABLES = ['users', 'directory_contacts', 'alerts', 'cases', 'groups', 'indicator_values', 'on_call_shifts', 'role_invitations'];

    /** @var list<string> Tables dont la colonne `commune_id` référence une commune. */
    public const COMMUNE_TABLES = ['users', 'directory_contacts', 'alerts', 'cases'];

    private function __construct() {}

    /**
     * Références hors communes : nombre de lignes liées à chaque zone.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int> identifiant => total (0 si inutilisée)
     */
    public static function zones(array $ids): array
    {
        return self::sumByColumn(self::ZONE_TABLES, 'zone_id', $ids);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    public static function communes(array $ids): array
    {
        return self::sumByColumn(self::COMMUNE_TABLES, 'commune_id', $ids);
    }

    /**
     * Nombre de communes (actives ou non) de chaque zone.
     *
     * @param  array<int, int>  $zoneIds
     * @return array<int, int>
     */
    public static function communeCounts(array $zoneIds): array
    {
        return self::countBy('communes', 'zone_id', $zoneIds) + array_fill_keys($zoneIds, 0);
    }

    /**
     * Dossiers (`violence_type_id`) et signalements (`alerts.category` = slug) d'un type de violence.
     *
     * @param  array<int, ViolenceType>  $types
     * @return array<int, int>
     */
    public static function violenceTypes(array $types): array
    {
        $ids = array_map(static fn (ViolenceType $type): int => (int) $type->getKey(), $types);
        $cases = self::countBy('cases', 'violence_type_id', $ids);
        $out = [];

        foreach ($types as $type) {
            $id = (int) $type->getKey();
            $out[$id] = ($cases[$id] ?? 0) + DB::table('alerts')->where('category', $type->slug)->count();
        }

        return $out;
    }

    /**
     * Signalements qui citent le besoin (`alerts.needs`, tableau JSON de slugs).
     *
     * @param  array<int, Need>  $needs
     * @return array<int, int>
     */
    public static function needs(array $needs): array
    {
        $out = [];

        foreach ($needs as $need) {
            $out[(int) $need->getKey()] = DB::table('alerts')->whereJsonContains('needs', $need->slug)->count();
        }

        return $out;
    }

    /**
     * @param  array<int, Population>  $populations
     * @return array<int, int>
     */
    public static function populations(array $populations): array
    {
        $ids = array_map(static fn (Population $population): int => (int) $population->getKey(), $populations);
        $cases = self::countBy('cases', 'population_id', $ids);

        return array_map(static fn (int $id): int => $cases[$id] ?? 0, array_combine($ids, $ids));
    }

    public static function zone(Zone $zone): int
    {
        return self::zones([(int) $zone->getKey()])[(int) $zone->getKey()] ?? 0;
    }

    public static function commune(Commune $commune): int
    {
        return self::communes([(int) $commune->getKey()])[(int) $commune->getKey()] ?? 0;
    }

    public static function violenceType(ViolenceType $type): int
    {
        return self::violenceTypes([$type])[(int) $type->getKey()] ?? 0;
    }

    public static function need(Need $need): int
    {
        return self::needs([$need])[(int) $need->getKey()] ?? 0;
    }

    public static function population(Population $population): int
    {
        return self::populations([$population])[(int) $population->getKey()] ?? 0;
    }

    /**
     * @param  list<string>  $tables
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private static function sumByColumn(array $tables, string $column, array $ids): array
    {
        $totals = array_fill_keys($ids, 0);

        foreach ($tables as $table) {
            foreach (self::countBy($table, $column, $ids) as $id => $count) {
                $totals[$id] = ($totals[$id] ?? 0) + $count;
            }
        }

        return $totals;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int> identifiant => nombre de lignes (absent : zéro)
     */
    private static function countBy(string $table, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $out = [];

        foreach (DB::table($table)->whereIn($column, $ids)->selectRaw($column.' as ref_id, count(*) as total')->groupBy($column)->get() as $row) {
            $out[(int) $row->ref_id] = (int) $row->total;
        }

        return $out;
    }
}
