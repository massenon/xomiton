<?php

declare(strict_types=1);

namespace App\Application\Steering;

use App\Enums\IndicatorFrequency;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Zone;
use App\Support\SmallCellMask;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tableau des indicateurs de pilotage (P10-06, prototype admin/indicateurs.php) : pour une période et une zone, la
 * valeur de chaque indicateur actif (calculée en direct par IndicatorCalculator pour les indicateurs intégrés, dernière
 * saisie manuelle pour les autres), son avancement vers la cible et sa tendance sur les six derniers instantanés
 * mensuels (`indicators:snapshot`). Les effectifs de 1 à 4 sont masqués (SmallCellMask) : la valeur d'un indicateur en
 * nombre, le dénominateur d'un pourcentage ; la tendance suit la même règle (un pourcentage dont le dénominateur
 * enregistré est de 1 à 4, ou inconnu, n'a pas de point). Suppression complémentaire : IND-01 = IND-02 + IND-03, donc si
 * l'un des trois est masqué, la plus petite autre cellule du groupe l'est aussi (SmallCellMask::maskGroup) ; sinon la
 * différence le retrouverait. Aucune colonne chiffrée n'est lue (le calculateur n'en lit aucune).
 */
final class IndicatorBoard
{
    /** Nombre de mois de la tendance. */
    public const TREND_MONTHS = 6;

    /** Indicateurs en nombre qui se somment : total = VBG + VDH (clés de calcul). */
    private const LINKED_GROUPS = [['cas_total', 'cas_vbg', 'cas_vdh']];

    /** Seuil « en progression » : avancement vers la cible, en %. */
    public const PROGRESS_FLOOR = 70;

    public function __construct(private readonly IndicatorCalculator $calculator) {}

    /**
     * @return list<IndicatorRow>
     */
    public function rows(Period $period, ?Zone $zone = null, ?IndicatorFrequency $frequency = null): array
    {
        /** @var Collection<int, Indicator> $indicators */
        $indicators = Indicator::query()->active()->ordered()
            ->when($frequency !== null, fn ($query) => $query->ofFrequency($frequency))
            ->get();

        $series = $this->series($indicators, $period, $zone);
        $values = [];

        foreach ($indicators as $indicator) {
            $values[(int) $indicator->getKey()] = $this->valueOf($indicator, $period, $zone);
        }

        $complementary = $this->complementary($indicators, $values);
        $rows = [];

        foreach ($indicators as $indicator) {
            $id = (int) $indicator->getKey();
            $rows[] = $this->row($indicator, $values[$id], $series[$id] ?? [], isset($complementary[$id]));
        }

        return $rows;
    }

    /**
     * Synthèse : indicateurs évalués par niveau d'atteinte (les valeurs masquées ou absentes ne sont pas évaluées).
     *
     * @param  list<IndicatorRow>  $rows
     * @return array{reached: int, progress: int, low: int, unrated: int}
     */
    public static function summary(array $rows): array
    {
        $summary = ['reached' => 0, 'progress' => 0, 'low' => 0, 'unrated' => 0];

        foreach ($rows as $row) {
            match ($row->status) {
                IndicatorRow::REACHED => $summary['reached']++,
                IndicatorRow::PROGRESS => $summary['progress']++,
                IndicatorRow::LOW => $summary['low']++,
                default => $summary['unrated']++,
            };
        }

        return $summary;
    }

    /**
     * Avancement vers la cible, de 0 à 100 (barre de progression du prototype). Sens « baisse » : 100 tant que la valeur
     * reste sous la cible, décroissant au-delà ; une cible nulle exige zéro.
     */
    public static function progress(Indicator $indicator, float $value): float
    {
        $target = (float) $indicator->target;

        if ($indicator->better === Indicator::BETTER_DOWN) {
            $progress = $target > 0
                ? max(0.0, 100 - max(0.0, $value - $target) / $target * 100)
                : ($value == 0.0 ? 100.0 : max(0.0, 100 - $value * 25));
        } else {
            $progress = $target > 0 ? min(100.0, $value / $target * 100) : 100.0;
        }

        return round($progress, 1);
    }

    public static function statusOf(float $progress): string
    {
        return match (true) {
            $progress >= 100 => IndicatorRow::REACHED,
            $progress >= self::PROGRESS_FLOOR => IndicatorRow::PROGRESS,
            default => IndicatorRow::LOW,
        };
    }

    /**
     * @param  array{0: float|null, 1: int|null, 2: string|null}  $computed  valeur, dénominateur, raison de l'absence
     * @param  list<array{label: string, value: float|null}>  $trend
     */
    private function row(Indicator $indicator, array $computed, array $trend, bool $complementary): IndicatorRow
    {
        [$value, $base, $reason] = $computed;

        if ($value === null) {
            return new IndicatorRow($indicator, null, false, $reason, null, IndicatorRow::NONE, [], null);
        }

        $masked = $indicator->isManual() ? false : ($complementary || $this->isMasked($indicator, $value, $base));

        if ($masked) {
            return new IndicatorRow($indicator, null, true, null, null, IndicatorRow::MASKED, [], null);
        }

        $progress = self::progress($indicator, $value);

        return new IndicatorRow($indicator, $value, false, null, $progress, self::statusOf($progress), $trend, self::direction($trend));
    }

    /**
     * @return array{0: float|null, 1: int|null, 2: string|null} valeur, dénominateur, raison de l'absence de valeur
     */
    private function valueOf(Indicator $indicator, Period $period, ?Zone $zone): array
    {
        if ($indicator->isManual()) {
            $latest = IndicatorValue::query()
                ->where('indicator_id', $indicator->getKey())
                ->where('zone_id', $zone?->getKey())
                ->manual()
                ->orderByDesc('period_end')->orderByDesc('id')
                ->first();

            return $latest === null ? [null, null, 'manual_missing'] : [(float) $latest->value, null, null];
        }

        $key = (string) $indicator->calc_key;

        if (! $this->calculator->supports($key)) {
            return [null, null, $this->calculator->unavailableReason($key)];
        }

        $computation = $this->calculator->compute($key, $period, $zone);

        return $computation->isAvailable()
            ? [(float) $computation->value, $computation->base, null]
            : [null, null, (string) $computation->reason];
    }

    /**
     * Suppression complémentaire des groupes liés (total = VBG + VDH) : identifiants des indicateurs à masquer en plus de
     * ceux dont l'effectif est de 1 à 4. Les valeurs manuelles et les indicateurs sans valeur n'en font pas partie.
     *
     * @param  Collection<int, Indicator>  $indicators
     * @param  array<int, array{0: float|null, 1: int|null, 2: string|null}>  $values
     * @return array<int, true>
     */
    private function complementary(Collection $indicators, array $values): array
    {
        $byKey = [];

        foreach ($indicators as $indicator) {
            $id = (int) $indicator->getKey();

            if (! $indicator->isManual() && ! $indicator->isPercentage() && $values[$id][0] !== null) {
                $byKey[(string) $indicator->calc_key] = $id;
            }
        }

        $extra = [];

        foreach (self::LINKED_GROUPS as $group) {
            $present = array_values(array_filter($group, static fn (string $key): bool => isset($byKey[$key])));

            // Un groupe doit contenir son total et au moins une partie pour que la différence existe.
            if (count($present) < 2 || ! in_array($group[0], $present, true)) {
                continue;
            }

            $counts = [];

            foreach ($present as $key) {
                $counts[$key] = (int) round((float) $values[$byKey[$key]][0]);
            }

            foreach (SmallCellMask::maskGroup($counts) as $key => $count) {
                if ($count === null && ! SmallCellMask::isMasked($counts[$key])) {
                    $extra[$byKey[$key]] = true;
                }
            }
        }

        return $extra;
    }

    /** Nombre : la valeur entière de 1 à 4 ; pourcentage : un dénominateur de 1 à 4. */
    private function isMasked(Indicator $indicator, float $value, ?int $base): bool
    {
        if ($indicator->isPercentage()) {
            return $base !== null && SmallCellMask::isMasked($base);
        }

        return SmallCellMask::isMasked((int) round($value));
    }

    /**
     * Instantanés mensuels des six derniers mois se terminant au mois de la fin de période, par indicateur.
     *
     * @param  Collection<int, Indicator>  $indicators
     * @return array<int, list<array{label: string, value: float|null}>>
     */
    private function series(Collection $indicators, Period $period, ?Zone $zone): array
    {
        if ($indicators->isEmpty()) {
            return [];
        }

        $last = $period->end->startOfMonth();
        $months = [];

        for ($offset = self::TREND_MONTHS - 1; $offset >= 0; $offset--) {
            $months[] = $last->subMonthsNoOverflow($offset);
        }

        /** @var \Illuminate\Support\Collection<int, \Illuminate\Support\Collection<string, IndicatorValue>> $stored */
        $stored = IndicatorValue::query()
            ->whereIn('indicator_id', $indicators->modelKeys())
            ->where('zone_id', $zone?->getKey())
            ->whereDate('period_start', '>=', $months[0]->toDateString())
            ->whereDate('period_end', '<=', $last->endOfMonth()->toDateString())
            ->chronological()
            ->get()
            ->groupBy('indicator_id')
            ->map(fn ($values) => $values->keyBy(fn (IndicatorValue $value): string => $value->period_start->format('Y-m')));

        $series = [];

        foreach ($indicators as $indicator) {
            $byMonth = $stored->get($indicator->getKey());
            $points = [];

            foreach ($months as $month) {
                /** @var IndicatorValue|null $row */
                $row = $byMonth?->get($month->format('Y-m'));
                $value = $row === null ? null : (float) $row->value;

                if ($value !== null && ! $indicator->isPercentage() && SmallCellMask::isMasked((int) round($value))) {
                    $value = null;
                }

                // Pourcentage calculé : le point n'est tracé que si le dénominateur enregistré est d'au moins 5 (un
                // instantané sans dénominateur ne peut pas être vérifié : il n'est pas tracé).
                if ($value !== null && $indicator->isPercentage() && ! $row->is_manual && ($row->base === null || SmallCellMask::isMasked((int) $row->base))) {
                    $value = null;
                }

                $points[] = ['label' => $month->format('m/Y'), 'value' => $value, 'raw' => $row === null || $row->is_manual ? null : (float) $row->value];
            }

            $series[(int) $indicator->getKey()] = $points;
        }

        $series = $this->maskLinkedSeries($indicators, $series, count($months));

        foreach ($series as $id => $points) {
            $points = array_map(static fn (array $point): array => ['label' => $point['label'], 'value' => $point['value']], $points);
            $series[$id] = array_filter($points, static fn (array $point): bool => $point['value'] !== null) === [] ? [] : $points;
        }

        return $series;
    }

    /**
     * Suppression complémentaire de la tendance : mois par mois, total = VBG + VDH (comme pour la valeur du jour).
     *
     * @param  Collection<int, Indicator>  $indicators
     * @param  array<int, list<array{label: string, value: float|null, raw: float|null}>>  $series
     * @return array<int, list<array{label: string, value: float|null, raw: float|null}>>
     */
    private function maskLinkedSeries(Collection $indicators, array $series, int $months): array
    {
        $byKey = [];

        foreach ($indicators as $indicator) {
            if (! $indicator->isManual() && ! $indicator->isPercentage()) {
                $byKey[(string) $indicator->calc_key] = (int) $indicator->getKey();
            }
        }

        foreach (self::LINKED_GROUPS as $group) {
            $present = array_values(array_filter($group, static fn (string $key): bool => isset($byKey[$key])));

            if (count($present) < 2 || ! in_array($group[0], $present, true)) {
                continue;
            }

            for ($index = 0; $index < $months; $index++) {
                $counts = [];

                foreach ($present as $key) {
                    $raw = $series[$byKey[$key]][$index]['raw'] ?? null;

                    if ($raw === null) {
                        continue 2;
                    }

                    $counts[$key] = (int) round($raw);
                }

                foreach (SmallCellMask::maskGroup($counts) as $key => $count) {
                    if ($count === null) {
                        $series[$byKey[$key]][$index]['value'] = null;
                    }
                }
            }
        }

        return $series;
    }

    /**
     * Sens de la tendance entre les deux derniers instantanés disponibles : up, down ou flat ; null sans deux points.
     *
     * @param  list<array{label: string, value: float|null}>  $trend
     */
    private static function direction(array $trend): ?string
    {
        $values = array_values(array_filter(array_column($trend, 'value'), static fn (mixed $value): bool => $value !== null));

        if (count($values) < 2) {
            return null;
        }

        $delta = $values[count($values) - 1] - $values[count($values) - 2];

        return $delta > 0.004 ? 'up' : ($delta < -0.004 ? 'down' : 'flat');
    }
}
