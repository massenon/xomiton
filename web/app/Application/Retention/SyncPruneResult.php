<?php

declare(strict_types=1);

namespace App\Application\Retention;

/**
 * Bilan d'une exécution de `sync:prune`.
 */
final readonly class SyncPruneResult
{
    public function __construct(
        public int $receiptsDeleted,
        public int $devicesRevoked,
        public int $receiptDays,
        public int $deviceDays,
    ) {}
}
