<?php

declare(strict_types=1);

namespace App\Application\Validation;

use App\Application\Cases\ValidationCatalog;
use App\Application\Shared\Referentials;
use App\Enums\CaseStatus;
use App\Models\User;
use App\Policies\Scopes\CaseRecordScope;
use App\Rules\NotFutureDate;

/**
 * Rapport de qualité des données (P11-05, prototype admin/validation.php « Rapport de qualité des données ») sur les
 * dossiers visibles, non archivés et non anonymisés : complétude par champ (nombre et part de valeurs manquantes), doublons
 * suspects (champs du catalogue `duplicates`), dates futures et dates plus anciennes que l'ancienneté de la règle
 * `plausibility`, et un score de qualité sur 100 (complétude, moins 20 points par part de dossiers en doublon, moins 30 par
 * part de dates douteuses). Aucune colonne chiffrée n'est lue : la présence des notes se mesure par `IS NULL`, sans les
 * déchiffrer ; le rapport ne contient que des effectifs, des codes de dossier et des libellés de référentiel.
 */
final class DataQualityReport
{
    /** Champs contrôlés : clé => colonne de `cases`. */
    public const FIELDS = [
        'zone' => 'zone_id',
        'commune' => 'commune_id',
        'population' => 'population_id',
        'sex' => 'sex',
        'age' => 'age_range',
        'violence' => 'violence_type_id',
        'referral' => 'referral',
        'notes' => 'notes',
    ];

    /** Groupes de doublons détaillés (les autres sont comptés). */
    public const MAX_GROUPS = 12;

    /** Codes de dossier cités par groupe. */
    public const MAX_CODES = 6;

    public function __construct(
        private readonly ValidationCatalog $catalog,
        private readonly Referentials $referentials,
    ) {}

    /**
     * @return array{cases: int, missing: array<string, int>, completeness: float, duplicate_groups: int, duplicate_cases: int, duplicates: list<array{codes: list<string>, more: int, values: array<string, string>}>, future: int, old: int, score: int}
     */
    public function __invoke(User $viewer): array
    {
        $query = fn () => CaseRecordScope::visibleTo($viewer)
            ->toBase()
            ->where('cases.status', '!=', CaseStatus::Archived->value)
            ->whereNull('cases.anonymized_at');

        $selects = ['COUNT(*) as n'];

        foreach (self::FIELDS as $key => $column) {
            $selects[] = 'SUM(CASE WHEN cases.'.$column.' IS NULL THEN 1 ELSE 0 END) as m_'.$key;
        }

        $today = NotFutureDate::today();
        $plausibility = $this->catalog->plausibility();
        $threshold = sprintf('%04d-%s', (int) $today->format('Y') - $plausibility->maxPastYears, $today->format('m-d'));
        $selects[] = 'SUM(CASE WHEN cases.occurred_on > ? THEN 1 ELSE 0 END) as future';
        $selects[] = 'SUM(CASE WHEN cases.occurred_on < ? THEN 1 ELSE 0 END) as old';

        $row = $query()->selectRaw(implode(', ', $selects), [$today->format('Y-m-d'), $threshold])->first();
        $cases = (int) ($row->n ?? 0);
        $missing = [];

        foreach (array_keys(self::FIELDS) as $key) {
            $missing[$key] = (int) ($row->{'m_'.$key} ?? 0);
        }

        [$groups, $duplicateCases, $duplicateGroups] = $this->duplicates($query);
        $future = (int) ($row->future ?? 0);
        $old = (int) ($row->old ?? 0);
        $n = max(1, $cases);
        $completeness = round(100 - array_sum($missing) / ($n * count(self::FIELDS)) * 100, 1);
        $score = (int) max(0, round($completeness - ($duplicateCases / $n) * 20 - (($future + $old) / $n) * 30));

        return [
            'cases' => $cases,
            'missing' => $missing,
            'completeness' => $completeness,
            'duplicate_groups' => $duplicateGroups,
            'duplicate_cases' => $duplicateCases,
            'duplicates' => $groups,
            'future' => $future,
            'old' => $old,
            'score' => $score,
        ];
    }

    /**
     * Doublons suspects : dossiers qui partagent tous les champs du catalogue (un champ vide ne prouve aucun doublon).
     *
     * @param  callable(): \Illuminate\Database\Query\Builder  $query
     * @return array{0: list<array{codes: list<string>, more: int, values: array<string, string>}>, 1: int, 2: int}
     */
    private function duplicates(callable $query): array
    {
        $criteria = $this->catalog->duplicateCriteria();
        $columns = $criteria->columns();
        $buckets = [];

        foreach ($query()->select(['cases.id', 'cases.code', ...array_map(static fn (string $c): string => 'cases.'.$c, $columns)])->orderBy('cases.id')->cursor() as $case) {
            $values = $criteria->valuesFrom((array) $case);

            if ($values === null) {
                continue;
            }

            $buckets[implode('|', array_map(static fn (mixed $v): string => (string) $v, $values))][] = ['code' => (string) $case->code, 'values' => $values];
        }

        $groups = array_values(array_filter($buckets, static fn (array $bucket): bool => count($bucket) > 1));
        $cases = array_sum(array_map('count', $groups));
        $detailed = [];

        foreach (array_slice($groups, 0, self::MAX_GROUPS) as $group) {
            $codes = array_column($group, 'code');
            $detailed[] = [
                'codes' => array_slice($codes, 0, self::MAX_CODES),
                'more' => max(0, count($codes) - self::MAX_CODES),
                'values' => $this->labels($group[0]['values']),
            ];
        }

        return [$detailed, (int) $cases, count($groups)];
    }

    /**
     * Valeurs partagées d'un groupe, en libellés lisibles (commune, population, type de violence par leur nom).
     *
     * @param  array<string, scalar>  $values  colonne => valeur
     * @return array<string, string>
     */
    private function labels(array $values): array
    {
        $communes = [];

        foreach ($this->referentials->zones() as $zone) {
            foreach ($zone['communes'] as $commune) {
                $communes[$commune['id']] = $commune['name'];
            }
        }

        $populations = array_column($this->referentials->populations(), 'label', 'id');
        $violences = array_column($this->referentials->violenceTypes(), 'label', 'id');
        $labels = [];

        foreach ($values as $column => $value) {
            $labels[$column] = match ($column) {
                'commune_id' => $communes[(int) $value] ?? '#'.$value,
                'population_id' => $populations[(int) $value] ?? '#'.$value,
                'violence_type_id' => $violences[(int) $value] ?? '#'.$value,
                'occurred_on' => substr((string) $value, 0, 10),
                default => (string) $value,
            };
        }

        return $labels;
    }
}
