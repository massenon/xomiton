<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Application\Steering\Period;
use App\Enums\ReportKind;
use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Catalogue des rapports : sections proposées (prototype admin/rapports.php), période par défaut d'un type de rapport
 * et période couverte par une planification.
 */
final class ReportCatalog
{
    /** Sections du rapport, dans l'ordre d'affichage. */
    public const SECTIONS = ['kpi', 'evol', 'viol', 'statut', 'registre', 'orient', 'alertes', 'suivi'];

    /** Sections cochées par défaut. */
    public const DEFAULT_SECTIONS = ['kpi', 'evol', 'viol', 'statut', 'registre', 'orient', 'alertes', 'suivi'];

    public const DEFAULT_DIMENSIONS = ['departement', 'sexe', 'age'];

    /** Période maximale d'un rapport (années). */
    public const MAX_YEARS = 5;

    private function __construct() {}

    /**
     * Période en cours pour un type de rapport périodique (assistant : remplit « Du » et « Au »). Personnalisé et
     * bailleur : l'année en cours.
     */
    public static function currentPeriod(ReportKind $kind, ?CarbonInterface $now = null): Period
    {
        $now = CarbonImmutable::instance($now ?? now());

        return match ($kind) {
            ReportKind::Monthly => new Period($now->startOfMonth(), $now->endOfMonth()->startOfDay()),
            ReportKind::Quarterly => new Period($now->startOfQuarter(), $now->endOfQuarter()->startOfDay()),
            ReportKind::Semiannual => $now->month <= 6
                ? new Period($now->startOfYear(), $now->startOfYear()->addMonths(5)->endOfMonth()->startOfDay())
                : new Period($now->startOfYear()->addMonths(6), $now->endOfYear()->startOfDay()),
            default => new Period($now->startOfYear(), $now->endOfYear()->startOfDay()),
        };
    }

    /**
     * Dernière période entière couverte par une planification, au moment de son échéance : la semaine, le mois, le
     * trimestre, le semestre ou l'année civils qui précèdent.
     */
    public static function previousPeriod(string $frequency, ?CarbonInterface $now = null): Period
    {
        $now = CarbonImmutable::instance($now ?? now());

        return match ($frequency) {
            ReportSchedule::FREQUENCY_WEEKLY => new Period($now->subWeek()->startOfWeek(), $now->subWeek()->endOfWeek()->startOfDay()),
            ReportSchedule::FREQUENCY_QUARTERLY => new Period($now->subQuarterNoOverflow()->startOfQuarter(), $now->subQuarterNoOverflow()->endOfQuarter()->startOfDay()),
            ReportSchedule::FREQUENCY_SEMIANNUAL => self::previousSemester($now),
            ReportSchedule::FREQUENCY_ANNUAL => new Period($now->subYear()->startOfYear(), $now->subYear()->endOfYear()->startOfDay()),
            default => Period::previousMonth($now),
        };
    }

    private static function previousSemester(CarbonImmutable $now): Period
    {
        if ($now->month <= 6) {
            $year = $now->subYear();

            return new Period($year->startOfYear()->addMonths(6), $year->endOfYear()->startOfDay());
        }

        return new Period($now->startOfYear(), $now->startOfYear()->addMonths(5)->endOfMonth()->startOfDay());
    }
}
