<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Type de rapport de pilotage (DATABASE_DICTIONARY.md §7 `generated_reports.kind`, `report_schedules.kind`).
 * `rtype` du prototype (admin/rapports.php).
 */
enum ReportKind: string
{
    case Monthly = 'mensuel';
    case Quarterly = 'trimestriel';
    case Semiannual = 'semestriel';
    case Annual = 'annuel';
    case Donor = 'bailleur';
    case Custom = 'personnalise';

    public function label(): string
    {
        return (string) __('enums/steering.report_kind.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Monthly => 'primary',
            self::Quarterly => 'info',
            self::Semiannual => 'info',
            self::Annual => 'violet',
            self::Donor => 'warning',
            self::Custom => 'secondary',
        };
    }

    /** Rapport à période fixe : peut être planifié (`report_schedules`). */
    public function isPeriodic(): bool
    {
        return in_array($this, [self::Monthly, self::Quarterly, self::Semiannual, self::Annual], true);
    }
}
