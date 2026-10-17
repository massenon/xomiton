<?php

declare(strict_types=1);

namespace App\Application\Retention;

/**
 * Bilan d'une exécution de `reports:purge`.
 */
final readonly class ReportsPurgeResult
{
    public function __construct(
        public int $filesDeleted,
        public int $rowsMarked,
        public int $rowsDeleted,
        public string $disk,
    ) {}

    /** Rapports expirés traités (marqués ou supprimés). */
    public function purged(): int
    {
        return $this->rowsMarked + $this->rowsDeleted;
    }
}
