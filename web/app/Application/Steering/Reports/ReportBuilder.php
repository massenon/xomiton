<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Application\Steering\Period;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Models\Alert;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Support\LocaleFormatter;
use App\Support\SmallCellMask;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agrégats d'un rapport (P10-07, prototype admin/rapports.php) : chiffres clés, évolution mensuelle, types de violence,
 * statuts, registres VBG / VDH, orientation, alertes, suivi et désagrégations (département, commune, population, sexe,
 * tranche d'âge). Dossiers rattachés à la période par leur enregistrement (`cases.created_at`, comme les indicateurs),
 * archivés exclus. **Aucune colonne chiffrée n'est lue** (ni récit, ni notes, ni GPS, ni contact).
 *
 * Masquage des petits effectifs : toute cellule de 1 à 4 devient `null` ; le total d'une ligne dont une cellule est
 * masquée l'est aussi, et un tableau qui contient une cellule masquée n'a pas de ligne « Total » ni de pourcentage
 * déductible : la soustraction ne retrouve jamais l'effectif masqué. Suppression complémentaire (comme
 * DashboardStats) : les cellules qui se somment vers un total connu par ailleurs (total / VBG / VDH des chiffres clés,
 * mois d'une évolution, lignes d'une répartition, lignes et colonnes d'une matrice) forment un groupe dont, quand une
 * seule cellule est masquée, la plus petite autre l'est aussi ; seule leur somme reste déductible. Un groupe dont le
 * total est lui-même inférieur à 5 (donc masqué partout) n'a rien à compléter.
 */
final class ReportBuilder
{
    /** Mois au plus d'une évolution mensuelle. */
    private const MAX_MONTHS = 60;

    private LocaleFormatter $format;

    private bool $masked = false;

    public function build(ReportSpec $spec): ReportDataset
    {
        $this->masked = false;
        $this->format = LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
        $period = $spec->period();
        $tables = [];
        $kpis = in_array('kpi', $spec->sections, true) ? $this->kpis($period) : [];

        if ($kpis !== []) {
            $tables[] = $this->table(__('steering.reports.sections.kpi'), [__('steering.reports.cols.indicator'), __('steering.reports.cols.value')],
                array_map(static fn (array $kpi): array => [$kpi['label'], $kpi['value']], $kpis));
        }

        foreach ($spec->sections as $section) {
            foreach ($this->section($section, $period) as $table) {
                $tables[] = $table;
            }
        }

        foreach ($this->dimensionTables($spec->dimensions, $period) as $table) {
            $tables[] = $table;
        }

        return new ReportDataset(
            title: $spec->title,
            periodLabel: $spec->start->format('d/m/Y').' – '.$spec->end->format('d/m/Y'),
            generatedAt: $this->format->dateTime(now()),
            kpis: $kpis,
            tables: $tables,
            hasMasked: $this->masked,
        );
    }

    /**
     * @return list<array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}>
     */
    private function section(string $section, Period $period): array
    {
        return match ($section) {
            'evol' => [$this->monthly($period)],
            'viol' => [$this->byViolence($period)],
            'statut' => [$this->byStatus($period)],
            'registre' => [$this->byKind($period)],
            'orient' => [$this->byReferral($period)],
            'alertes' => [$this->alerts($period)],
            'suivi' => [$this->followups($period)],
            default => [],
        };
    }

    /** @return list<array{label: string, value: int|string|null}> */
    private function kpis(Period $period): array
    {
        $total = $this->cases($period)->count();
        $vbg = $this->cases($period)->where('cases.kind', CaseKind::Vbg->value)->count();
        $vdh = $this->cases($period)->where('cases.kind', CaseKind::Vdh->value)->count();
        $closed = $this->cases($period)->where('cases.status', CaseStatus::Closed->value)->count();
        $alerts = Alert::query()->toBase()->whereBetween('created_at', [$period->startAt(), $period->endAt()]);
        $alertCount = (clone $alerts)->count();
        $sos = (clone $alerts)->where('type', AlertType::Sos->value)->count();
        $steps = $this->followupQuery($period);
        $stepCount = (clone $steps)->count();
        $done = (clone $steps)->where('outcome', FollowupOutcome::Done->value)->count();

        // Total = VBG + VDH : groupe lié (une cellule masquée seule se retrouverait par différence).
        $linked = SmallCellMask::maskGroup(['total' => $total, 'vbg' => $vbg, 'vdh' => $vdh]);

        return [
            ['label' => (string) __('steering.reports.kpi.cases'), 'value' => $linked['total']],
            ['label' => (string) __('steering.reports.kpi.vbg'), 'value' => $linked['vbg']],
            ['label' => (string) __('steering.reports.kpi.vdh'), 'value' => $linked['vdh']],
            ['label' => (string) __('steering.reports.kpi.closed'), 'value' => $this->percent($closed, $total)],
            ['label' => (string) __('steering.reports.kpi.alerts'), 'value' => $this->cell($alertCount)],
            ['label' => (string) __('steering.reports.kpi.sos'), 'value' => $this->cell($sos)],
            ['label' => (string) __('steering.reports.kpi.followups'), 'value' => $this->percent($done, $stepCount)],
        ];
    }

    /** @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null} */
    private function monthly(Period $period): array
    {
        $labels = [];
        $counts = [];
        $cursor = $period->start->startOfMonth();
        $count = 0;

        while ($cursor->lessThanOrEqualTo($period->end) && $count < self::MAX_MONTHS) {
            $from = $cursor->lessThan($period->start) ? $period->start->startOfDay() : $cursor;
            $to = $cursor->endOfMonth();
            $to = $to->greaterThan($period->endAt()) ? $period->endAt() : $to;
            $n = DB::table('cases')->whereBetween('created_at', [$from, $to])->where('status', '!=', CaseStatus::Archived->value)->count();
            $labels[] = ucfirst($this->format->monthName((int) $cursor->month, true)).' '.$cursor->format('Y');
            $counts[] = $n;
            $cursor = $cursor->addMonthNoOverflow()->startOfMonth();
            $count++;
        }

        // Les mois se somment vers le total des chiffres clés : suppression complémentaire.
        $rows = array_map(static fn (string $label, ?int $n): array => [$label, $n], $labels, $this->group($counts));

        return $this->table(__('steering.reports.sections.evol'), [__('steering.reports.cols.month'), __('steering.reports.cols.cases')], $rows);
    }

    /** @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null} */
    private function byViolence(Period $period): array
    {
        $rows = $this->cases($period)
            ->leftJoin('violence_types', 'violence_types.id', '=', 'cases.violence_type_id')
            ->selectRaw('violence_types.label as label, violence_types.position as position, COUNT(*) as n')
            ->groupBy('violence_types.label', 'violence_types.position')
            ->orderByRaw('violence_types.position IS NULL, violence_types.position')
            ->get();

        return $this->distribution(__('steering.reports.sections.viol'), __('steering.reports.cols.type'), $rows->map(static fn (object $row): array => [(string) ($row->label ?? __('steering.reports.unknown')), (int) $row->n])->all());
    }

    /** @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null} */
    private function byStatus(Period $period): array
    {
        $counts = $this->cases($period)->selectRaw('cases.status as label, COUNT(*) as n')->groupBy('cases.status')->pluck('n', 'label')->all();
        $rows = [];

        foreach ([CaseStatus::Open, CaseStatus::InProgress, CaseStatus::Closed] as $status) {
            $rows[] = [$status->label(), (int) ($counts[$status->value] ?? 0)];
        }

        return $this->distribution(__('steering.reports.sections.statut'), __('steering.reports.cols.status'), $rows);
    }

    /** @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null} */
    private function byKind(Period $period): array
    {
        $counts = $this->cases($period)->selectRaw('cases.kind as label, COUNT(*) as n')->groupBy('cases.kind')->pluck('n', 'label')->all();

        return $this->distribution(__('steering.reports.sections.registre'), __('steering.reports.cols.register'), [
            [CaseKind::Vbg->label(), (int) ($counts[CaseKind::Vbg->value] ?? 0)],
            [CaseKind::Vdh->label(), (int) ($counts[CaseKind::Vdh->value] ?? 0)],
        ]);
    }

    /** @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null} */
    private function byReferral(Period $period): array
    {
        $rows = $this->cases($period)->selectRaw("COALESCE(NULLIF(cases.referral, ''), ?) as label, COUNT(*) as n", [__('steering.reports.not_referred')])
            ->groupBy('label')->orderByDesc('n')->orderBy('label')->get();

        return $this->distribution(__('steering.reports.sections.orient'), __('steering.reports.cols.referral'), $rows->map(static fn (object $row): array => [(string) $row->label, (int) $row->n])->all());
    }

    /** @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null} */
    private function alerts(Period $period): array
    {
        $counts = Alert::query()->toBase()->whereBetween('created_at', [$period->startAt(), $period->endAt()])
            ->selectRaw('type, status, COUNT(*) as n')->groupBy('type', 'status')->get();
        $matrix = [];

        foreach ($counts as $row) {
            $matrix[(string) $row->type][(string) $row->status] = (int) $row->n;
        }

        $statuses = AlertStatus::cases();
        $rows = [];

        foreach (AlertType::cases() as $type) {
            $cells = [];

            foreach ($statuses as $status) {
                $cells[] = (int) ($matrix[$type->value][$status->value] ?? 0);
            }

            $rows[] = [$type->label(), ...$cells];
        }

        return $this->matrix(__('steering.reports.sections.alertes'), [__('steering.reports.cols.type'), ...array_map(static fn (AlertStatus $status): string => $status->label(), $statuses)], $rows);
    }

    /** @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null} */
    private function followups(Period $period): array
    {
        $counts = $this->followupQuery($period)->selectRaw('outcome as label, COUNT(*) as n')->groupBy('outcome')->pluck('n', 'label')->all();
        $rows = [];

        foreach (FollowupOutcome::cases() as $outcome) {
            $rows[] = [$outcome->label(), (int) ($counts[$outcome->value] ?? 0)];
        }

        return $this->distribution(__('steering.reports.sections.suivi'), __('steering.reports.cols.result'), $rows);
    }

    /**
     * @param  list<string>  $dimensions
     * @return list<array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}>
     */
    private function dimensionTables(array $dimensions, Period $period): array
    {
        $dimensions = array_values(array_filter($dimensions, static fn (string $key): bool => in_array($key, ReportDimensions::KEYS, true)));
        $tables = [];

        foreach ($dimensions as $key) {
            $label = ReportDimensions::label($key);
            $counts = $this->byDimension($key, $period);
            $rows = [];

            foreach ($counts as $value => $perKind) {
                $rows[] = [$value, (int) ($perKind[CaseKind::Vbg->value] ?? 0), (int) ($perKind[CaseKind::Vdh->value] ?? 0)];
            }

            $tables[] = $this->breakdown(__('steering.reports.dimension_by', ['dimension' => mb_strtolower($label)]), [$label, CaseKind::Vbg->label(), CaseKind::Vdh->label(), __('steering.reports.cols.total')], $rows);
        }

        if (count($dimensions) >= 2) {
            [$first, $second] = $dimensions;
            $tables[] = $this->cross($first, $second, $period);
        }

        return $tables;
    }

    /**
     * @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}
     */
    private function cross(string $first, string $second, Period $period): array
    {
        $query = $this->cases($period);
        [$firstExpr] = ReportDimensions::select($query, $first, 'dim_a');
        [$secondExpr] = ReportDimensions::select($query, $second, 'dim_b');
        $rows = $query->selectRaw("{$firstExpr} as dim_a, {$secondExpr} as dim_b, COUNT(*) as n")->groupBy('dim_a', 'dim_b')->get();
        $a = [];
        $b = [];
        $matrix = [];

        foreach ($rows as $row) {
            $x = ReportDimensions::display($first, $row->dim_a);
            $y = ReportDimensions::display($second, $row->dim_b);
            $a[$x] = true;
            $b[$y] = true;
            $matrix[$x][$y] = ($matrix[$x][$y] ?? 0) + (int) $row->n;
        }

        ksort($a);
        ksort($b);
        $body = [];

        foreach (array_keys($a) as $x) {
            $line = [(string) $x];

            foreach (array_keys($b) as $y) {
                $line[] = (int) ($matrix[$x][$y] ?? 0);
            }

            $body[] = $line;
        }

        return $this->matrix(
            __('steering.reports.cross', ['a' => ReportDimensions::label($first), 'b' => mb_strtolower(ReportDimensions::label($second))]),
            [ReportDimensions::label($first), ...array_map('strval', array_keys($b))],
            $body,
        );
    }

    /**
     * Effectifs par valeur de la dimension et par registre.
     *
     * @return array<string, array<string, int>>
     */
    private function byDimension(string $key, Period $period): array
    {
        $query = $this->cases($period);
        [$expr] = ReportDimensions::select($query, $key, 'dim');
        $rows = $query->selectRaw("{$expr} as dim, cases.kind as kind, COUNT(*) as n")->groupBy('dim', 'cases.kind')->get();
        $counts = [];

        foreach ($rows as $row) {
            $value = ReportDimensions::display($key, $row->dim);
            $counts[$value][(string) $row->kind] = ($counts[$value][(string) $row->kind] ?? 0) + (int) $row->n;
        }

        ksort($counts);

        return $counts;
    }

    // -- Mise en forme et masquage --------------------------------------------------------------------------------

    /**
     * Tableau « libellé | effectif | % » : pourcentages sur le total réel, masqués si la cellule l'est ; pas de ligne
     * « Total » dès qu'une cellule est masquée.
     *
     * @param  list<array{0: string, 1: int}>  $counts
     * @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}
     */
    private function distribution(string $title, string $label, array $counts): array
    {
        $total = array_sum(array_column($counts, 1));
        $cells = $this->group(array_map(static fn (array $count): int => $count[1], $counts));
        $hidden = in_array(null, $cells, true);

        $rows = [];

        foreach ($counts as $index => [$name, $n]) {
            // Un pourcentage sur le total réel permettrait de retrouver un effectif masqué : aucun pourcentage dans ce cas.
            $rows[] = [$name, $cells[$index], $hidden ? null : $this->percent($n, $total)];
        }

        if (! $hidden && $counts !== []) {
            $rows[] = [(string) __('steering.reports.cols.total'), $this->cell($total), $total > 0 ? $this->format->percent(100, 0) : null];
        }

        return $this->table($title, [$label, __('steering.reports.cols.cases'), __('steering.reports.cols.share')], $rows);
    }

    /**
     * Désagrégation « valeur | VBG | VDH | Total » : le total d'une ligne dont une cellule est masquée l'est aussi.
     *
     * @param  list<string>  $head
     * @param  list<array{0: string, 1: int, 2: int}>  $rows
     * @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}
     */
    private function breakdown(string $title, array $head, array $rows): array
    {
        $body = [];
        $hidden = false;
        $sums = [0, 0];
        // Lignes (VBG + VDH = total de la valeur) et colonnes (somme = total du registre) : suppression complémentaire.
        $masked = $this->maskMatrix(array_map(static fn (array $row): array => [$row[1], $row[2]], $rows));

        foreach ($rows as $index => [$name, $vbg, $vdh]) {
            $cells = $masked[$index];
            $rowHidden = $cells[0] === null || $cells[1] === null;
            $total = $rowHidden ? null : $this->cell($vbg + $vdh);
            $hidden = $hidden || $rowHidden;
            $sums[0] += $vbg;
            $sums[1] += $vdh;
            $body[] = [$name, $cells[0], $cells[1], $total];
        }

        if (! $hidden && $rows !== []) {
            $body[] = [(string) __('steering.reports.cols.total'), $this->cell($sums[0]), $this->cell($sums[1]), $this->cell($sums[0] + $sums[1])];
        }

        return $this->table($title, $head, $body);
    }

    /**
     * Matrice d'effectifs : lignes dont une cellule est masquée sans total de ligne ; pas de ligne « Total » si une
     * cellule est masquée.
     *
     * @param  list<string>  $head
     * @param  list<list<int|string>>  $rows  première colonne : libellé ; suivantes : effectifs
     * @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}
     */
    private function matrix(string $title, array $head, array $rows): array
    {
        $body = [];
        $hidden = false;
        $columns = [];
        $counts = array_map(static fn (array $row): array => array_map('intval', array_slice($row, 1)), $rows);
        $masked = $this->maskMatrix($counts);

        foreach ($rows as $line => $row) {
            $label = array_shift($row);
            $cells = [];
            $rowHidden = false;

            foreach ($row as $index => $n) {
                $n = (int) $n;
                $cell = $masked[$line][$index];
                $rowHidden = $rowHidden || $cell === null;
                $cells[] = $cell;
                $columns[$index] = ($columns[$index] ?? 0) + $n;
            }

            $hidden = $hidden || $rowHidden;
            $body[] = [$label, ...$cells, $rowHidden ? null : $this->cell(array_sum($row))];
        }

        $head[] = (string) __('steering.reports.cols.total');

        if (! $hidden && $rows !== []) {
            $body[] = [(string) __('steering.reports.cols.total'), ...array_map(fn (int $sum): ?int => $this->cell($sum), $columns), $this->cell(array_sum($columns))];
        }

        return $this->table($title, $head, $body);
    }

    /**
     * @param  list<string>  $head
     * @param  list<list<int|string|null>>  $rows
     * @return array{title: string, head: list<string>, rows: list<list<int|string|null>>, note: string|null}
     */
    private function table(string $title, array $head, array $rows): array
    {
        $note = null;

        foreach ($rows as $row) {
            if (in_array(null, $row, true)) {
                $note = (string) __('steering.reports.masked_note', ['min' => SmallCellMask::MIN]);
                $this->masked = true;
                break;
            }
        }

        return ['title' => $title, 'head' => array_map('strval', $head), 'rows' => $rows, 'note' => $note];
    }

    private function cell(int $n): ?int
    {
        return SmallCellMask::mask($n);
    }

    /**
     * Groupe de cellules qui se somment : cellules de 1 à 4 masquées, plus suppression complémentaire quand le total du
     * groupe (5 et plus) est connu par ailleurs. Sous 5 au total, le total est masqué partout : rien à compléter.
     *
     * @param  array<array-key, int>  $counts
     * @return array<array-key, int|null>
     */
    private function group(array $counts): array
    {
        return array_sum($counts) < SmallCellMask::MIN ? SmallCellMask::maskAll($counts) : SmallCellMask::maskGroup($counts);
    }

    /**
     * Matrice d'effectifs dont les lignes et les colonnes se somment vers des totaux connus par ailleurs : masquage des
     * cellules de 1 à 4, puis suppression complémentaire ligne par ligne et colonne par colonne jusqu'à stabilité. Une
     * ligne ou colonne de moins de 5 au total n'a rien à compléter ; une cellule à zéro n'est complétée qu'en dernier
     * recours et ne déclenche pas d'autre complément.
     *
     * @param  array<array-key, list<int>>  $counts
     * @return array<array-key, list<int|null>>
     */
    private function maskMatrix(array $counts): array
    {
        $hard = [];
        $soft = [];
        $lines = [];

        foreach ($counts as $r => $row) {
            foreach ($row as $c => $n) {
                $hard[$r][$c] = SmallCellMask::isMasked($n);
                $soft[$r][$c] = false;
                $lines['r'.$r][] = [$r, $c];
                $lines['c'.$c][] = [$r, $c];
            }
        }

        do {
            $changed = false;

            foreach ($lines as $line) {
                $changed = $this->completeLine($line, $counts, $hard, $soft) || $changed;
            }
        } while ($changed);

        $result = [];

        foreach ($counts as $r => $row) {
            foreach ($row as $c => $n) {
                $result[$r][$c] = $hard[$r][$c] || $soft[$r][$c] ? null : $n;
            }
        }

        return $result;
    }

    /**
     * @param  list<array{0: array-key, 1: array-key}>  $line
     * @param  array<array-key, list<int>>  $counts
     * @param  array<array-key, array<array-key, bool>>  $hard
     * @param  array<array-key, array<array-key, bool>>  $soft
     */
    private function completeLine(array $line, array $counts, array &$hard, array &$soft): bool
    {
        $masked = 0;
        $zeroMasked = 0;
        $sum = 0;

        foreach ($line as [$r, $c]) {
            $masked += $hard[$r][$c] ? 1 : 0;
            $zeroMasked += $soft[$r][$c] ? 1 : 0;
            $sum += $counts[$r][$c];
        }

        if ($masked !== 1 || $zeroMasked !== 0 || $sum < SmallCellMask::MIN) {
            return false;
        }

        $best = null;
        $zero = null;

        foreach ($line as [$r, $c]) {
            if ($hard[$r][$c] || $soft[$r][$c]) {
                continue;
            }

            if ($counts[$r][$c] === 0) {
                $zero ??= [$r, $c];

                continue;
            }

            if ($best === null || $counts[$r][$c] < $best[2]) {
                $best = [$r, $c, $counts[$r][$c]];
            }
        }

        if ($best !== null) {
            $hard[$best[0]][$best[1]] = true;

            return true;
        }

        if ($zero !== null) {
            $soft[$zero[0]][$zero[1]] = true;

            return true;
        }

        return false;
    }

    /** Pourcentage « 33,3 % » ; null si le numérateur ou le dénominateur est un effectif de 1 à 4. */
    private function percent(int $numerator, int $denominator): ?string
    {
        if ($denominator === 0) {
            return $this->format->percent(0, 0);
        }

        if (SmallCellMask::isMasked($numerator) || SmallCellMask::isMasked($denominator)) {
            return null;
        }

        return $this->format->percent($numerator / $denominator * 100, 1);
    }

    private function cases(Period $period): Builder
    {
        return CaseRecord::query()->toBase()
            ->whereBetween('cases.created_at', [$period->startAt(), $period->endAt()])
            ->where('cases.status', '!=', CaseStatus::Archived->value);
    }

    private function followupQuery(Period $period): Builder
    {
        return CaseFollowup::query()->toBase()
            ->whereBetween('case_followups.date', [$period->start->toDateString(), $period->end->toDateString()]);
    }
}
