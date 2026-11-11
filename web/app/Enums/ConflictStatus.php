<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'un conflit de synchronisation (DATABASE_DICTIONARY.md §10 `sync_conflicts.status`).
 */
enum ConflictStatus: string
{
    case Open = 'a_resoudre';
    case Resolved = 'resolu';

    public function label(): string
    {
        return (string) __('enums/platform.conflict_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Open => 'danger',
            self::Resolved => 'success',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Valeur du prototype (`sync_conflicts.status` : « à résoudre », « résolu »).
     */
    public static function fromPrototype(string $status): self
    {
        return match (mb_strtolower(trim($status))) {
            'résolu', 'resolu' => self::Resolved,
            default => self::Open,
        };
    }
}
