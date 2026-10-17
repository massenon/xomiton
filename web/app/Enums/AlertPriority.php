<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Priorité d'une alerte (DATABASE_DICTIONARY.md §5 `alerts.priority`) ; un SOS est toujours critique.
 */
enum AlertPriority: string
{
    case Normal = 'normale';
    case High = 'haute';
    case Critical = 'critique';

    public function label(): string
    {
        return (string) __('enums/alerts.alert_priority.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Normal => 'secondary',
            self::High => 'warning',
            self::Critical => 'danger',
        };
    }

    /** Rang numérique pour trier le tableau de garde (critique d'abord). */
    public function weight(): int
    {
        return match ($this) {
            self::Normal => 1,
            self::High => 2,
            self::Critical => 3,
        };
    }
}
