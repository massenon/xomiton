<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Périodicité de calcul d'un indicateur (DATABASE_DICTIONARY.md §7 `indicators.frequency`).
 * Libellés du prototype (« Hebdomadaire », « Mensuelle », « Trimestrielle », « Semestrielle », « Annuelle »)
 * stockés en minuscules sans accent.
 */
enum IndicatorFrequency: string
{
    case Weekly = 'hebdomadaire';
    case Monthly = 'mensuelle';
    case Quarterly = 'trimestrielle';
    case Semiannual = 'semestrielle';
    case Annual = 'annuelle';

    public function label(): string
    {
        return (string) __('enums/steering.indicator_frequency.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Weekly => 'info',
            self::Monthly => 'primary',
            self::Quarterly => 'violet',
            self::Semiannual => 'warning',
            self::Annual => 'secondary',
        };
    }

    /** Nombre de jours couverts par une période (bornes des instantanés `indicators:snapshot`). */
    public function days(): int
    {
        return match ($this) {
            self::Weekly => 7,
            self::Monthly => 30,
            self::Quarterly => 91,
            self::Semiannual => 182,
            self::Annual => 365,
        };
    }

    /**
     * Libellé du prototype (`indicateurs.json` : `freq`) vers la valeur stockée ; null si inconnu.
     */
    public static function fromPrototype(string $label): ?self
    {
        return match (mb_strtolower(trim($label))) {
            'hebdomadaire' => self::Weekly,
            'mensuelle', 'mensuel' => self::Monthly,
            'trimestrielle', 'trimestriel' => self::Quarterly,
            'semestrielle', 'semestriel' => self::Semiannual,
            'annuelle', 'annuel' => self::Annual,
            default => null,
        };
    }
}
