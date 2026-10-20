<?php

declare(strict_types=1);

namespace App\Application\Steering\Reports;

use App\Application\Steering\Period;
use App\Enums\ReportFormat;
use App\Enums\ReportKind;
use Carbon\CarbonImmutable;

/**
 * Paramètres d'un rapport (assistant de l'écran Rapports ou planification) : type, période, désagrégations, sections,
 * registre détaillé (lignes pseudonymisées), format et destinataires e-mail.
 */
final readonly class ReportSpec
{
    /**
     * @param  list<string>  $dimensions  clés de GeneratedReport::DIMENSIONS
     * @param  list<string>  $sections  clés de ReportCatalog::SECTIONS
     * @param  list<string>  $recipients  adresses e-mail
     */
    public function __construct(
        public ReportKind $kind,
        public string $title,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public array $dimensions,
        public array $sections,
        public bool $detail,
        public ReportFormat $format,
        public array $recipients = [],
    ) {}

    public function period(): Period
    {
        return new Period($this->start->startOfDay(), $this->end->startOfDay());
    }
}
