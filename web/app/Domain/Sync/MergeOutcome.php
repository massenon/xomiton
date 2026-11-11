<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Résultat de FieldMerge : valeurs à appliquer, champs où le serveur est gardé, conflits (valeurs serveur et appareil)
 * et champs déjà identiques.
 */
final readonly class MergeOutcome
{
    /**
     * @param  array<string, string|null>  $applied
     * @param  list<string>  $kept
     * @param  array<string, array{server: string|null, device: string|null}>  $conflicts
     * @param  list<string>  $unchanged
     */
    public function __construct(
        public array $applied,
        public array $kept,
        public array $conflicts,
        public array $unchanged,
    ) {}

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }
}
