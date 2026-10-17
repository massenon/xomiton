<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Résultat d'une étape de suivi (DATABASE_DICTIONARY.md §6 `case_followups.outcome`).
 * Le prototype connaissait « En attente » : importé comme `planifie` (écart résolu dans le dictionnaire).
 */
enum FollowupOutcome: string
{
    case Planned = 'planifie';
    case Done = 'realise';
    case Postponed = 'reporte';
    case Cancelled = 'annule';

    public function label(): string
    {
        return (string) __('enums/cases.followup_outcome.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Planned => 'info',
            self::Done => 'success',
            self::Postponed => 'warning',
            self::Cancelled => 'secondary',
        };
    }

    /** Étape encore à faire : alimente les rappels de la pair·e (`next_on`). */
    public function isPending(): bool
    {
        return in_array($this, [self::Planned, self::Postponed], true);
    }

    /**
     * Libellés du prototype (`suivi.json`) vers l'enum, pour l'import.
     */
    public static function fromPrototype(string $label): self
    {
        return match (trim($label)) {
            'Réalisé' => self::Done,
            'Reporté' => self::Postponed,
            'Annulé' => self::Cancelled,
            default => self::Planned,
        };
    }
}
