<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Application\Steering\Period;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Models\CaseRecord;
use Generator;
use Illuminate\Database\Query\Builder;

/**
 * Registre détaillé d'un rapport : une ligne par dossier non archivé de la période, **pseudonymisée** (code du dossier,
 * registre, date des faits, type de violence, statut, plus les désagrégations demandées). Ni récit, ni notes, ni GPS, ni
 * contact : aucune colonne chiffrée n'est lue (ADR-33). Lu par morceaux d'identifiants (`rows_per_job`) pour que chaque
 * job GenerateReport reste sous 30 s.
 */
final class ReportDetail
{
    /** @return list<string> */
    public static function head(array $dimensions): array
    {
        return [
            (string) __('steering.reports.detail.code'),
            (string) __('steering.reports.detail.register'),
            (string) __('steering.reports.detail.date'),
            (string) __('steering.reports.detail.violence'),
            (string) __('steering.reports.detail.status'),
            ...array_map(static fn (string $key): string => ReportDimensions::label($key), self::dimensions($dimensions)),
        ];
    }

    public function count(Period $period): int
    {
        return $this->base($period)->count();
    }

    /**
     * Bornes d'identifiants de chaque morceau : [après, jusqu'à inclus] ; le dernier est ouvert (null).
     *
     * @return list<array{0: int, 1: int|null}>
     */
    public function boundaries(Period $period, int $perJob): array
    {
        $total = $this->count($period);
        $perJob = max(1, $perJob);
        $parts = max(1, (int) ceil($total / $perJob));
        $bounds = [];
        $after = 0;

        for ($index = 0; $index < $parts; $index++) {
            $until = $index === $parts - 1
                ? null
                : (int) $this->base($period)->orderBy('cases.id')->offset(($index + 1) * $perJob - 1)->limit(1)->value('cases.id');
            $bounds[] = [$after, $until];
            $after = (int) $until;
        }

        return $bounds;
    }

    /**
     * @param  list<string>  $dimensions
     * @return Generator<int, list<int|string|null>>
     */
    public function rows(Period $period, array $dimensions, int $after, ?int $until): Generator
    {
        $dimensions = self::dimensions($dimensions);
        $query = $this->base($period)->where('cases.id', '>', $after)
            ->leftJoin('violence_types', 'violence_types.id', '=', 'cases.violence_type_id')
            ->select(['cases.id', 'cases.code', 'cases.kind', 'cases.occurred_on', 'cases.status', 'violence_types.label as violence']);

        if ($until !== null) {
            $query->where('cases.id', '<=', $until);
        }

        foreach ($dimensions as $key) {
            [$expr] = ReportDimensions::select($query, $key, 'det_'.$key);
            $query->selectRaw($expr.' as dim_'.$key);
        }

        foreach ($query->orderBy('cases.id')->lazyById(500, 'cases.id', 'id') as $row) {
            yield [
                (string) $row->code,
                CaseKind::tryFrom((string) $row->kind)?->label() ?? (string) $row->kind,
                substr((string) $row->occurred_on, 0, 10),
                $row->violence === null ? (string) __('steering.reports.unknown') : (string) $row->violence,
                CaseStatus::tryFrom((string) $row->status)?->label() ?? (string) $row->status,
                ...array_map(static fn (string $key): string => ReportDimensions::display($key, $row->{'dim_'.$key}), $dimensions),
            ];
        }
    }

    /** @return Builder */
    private function base(Period $period)
    {
        return CaseRecord::query()->toBase()
            ->whereBetween('cases.created_at', [$period->startAt(), $period->endAt()])
            ->where('cases.status', '!=', CaseStatus::Archived->value);
    }

    /**
     * @param  list<string>  $dimensions
     * @return list<string>
     */
    private static function dimensions(array $dimensions): array
    {
        return array_values(array_filter($dimensions, static fn (string $key): bool => in_array($key, ReportDimensions::KEYS, true)));
    }
}
