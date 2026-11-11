<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cycle de vie d'une alerte (DATABASE_DICTIONARY.md §5 `alerts.status`). Le prototype ne connaissait
 * que `nouveau`, `en_cours`, `traite` ; `pris_en_charge` (accusé de réception) et `clos` sont ajoutés par le doc.
 */
enum AlertStatus: string
{
    case New = 'nouveau';
    case Acknowledged = 'pris_en_charge';
    case InProgress = 'en_cours';
    case Handled = 'traite';
    case Closed = 'clos';

    public function label(): string
    {
        return (string) __('enums/alerts.alert_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::New => 'danger',
            self::Acknowledged => 'info',
            self::InProgress => 'warning',
            self::Handled => 'success',
            self::Closed => 'secondary',
        };
    }

    /** Alerte encore à traiter (tableau de garde, compteur de la cloche). */
    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }

    /** Statut final : plus aucune action attendue. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Handled, self::Closed], true);
    }

    /**
     * Transitions autorisées depuis ce statut (validation applicative du changement de statut).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Acknowledged, self::InProgress, self::Closed],
            self::Acknowledged => [self::InProgress, self::Handled, self::Closed],
            self::InProgress => [self::Handled, self::Closed],
            self::Handled => [self::Closed, self::InProgress],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public static function openStatuses(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->isOpen()));
    }
}
