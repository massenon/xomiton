<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cycle de vie d'un dossier (DATABASE_DICTIONARY.md §6 `cases.status`). Le prototype s'arrêtait à `clos` ;
 * `archive` remplace la suppression : un dossier ne se supprime jamais, il s'archive puis s'anonymise.
 */
enum CaseStatus: string
{
    case Open = 'ouvert';
    case InProgress = 'en_cours';
    case Closed = 'clos';
    case Archived = 'archive';

    public function label(): string
    {
        return (string) __('enums/cases.case_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::InProgress => 'warning',
            self::Closed => 'success',
            self::Archived => 'secondary',
        };
    }

    /** Dossier encore suivi (registres des pair·es, indicateurs « dossiers actifs »). */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::InProgress], true);
    }

    /**
     * Transitions autorisées depuis ce statut (validation applicative du changement de statut).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Closed],
            self::InProgress => [self::Closed, self::Open],
            self::Closed => [self::Archived, self::InProgress],
            self::Archived => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public static function activeStatuses(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $status): bool => $status->isActive()));
    }
}
