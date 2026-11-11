<?php

declare(strict_types=1);

namespace App\Application\Platform;

/**
 * Bilan d'un appel de `xomiton:reencrypt` : compteurs par table (valeurs rechiffrées, déjà à jour, indéchiffrables)
 * et indication d'arrêt sur temps imparti (`completed` à false : relancer la commande pour reprendre).
 */
final readonly class ReencryptResult
{
    /**
     * @param  array<string, array{processed: int, skipped: int, failed: int}>  $tables
     */
    public function __construct(
        public array $tables,
        public bool $completed,
        public float $elapsedSeconds,
        public bool $dryRun,
    ) {}

    public function processed(): int
    {
        return (int) array_sum(array_column($this->tables, 'processed'));
    }

    public function skipped(): int
    {
        return (int) array_sum(array_column($this->tables, 'skipped'));
    }

    public function failed(): int
    {
        return (int) array_sum(array_column($this->tables, 'failed'));
    }

    public function hasFailures(): bool
    {
        return $this->failed() > 0;
    }
}
