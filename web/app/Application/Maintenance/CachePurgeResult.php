<?php

declare(strict_types=1);

namespace App\Application\Maintenance;

/**
 * Bilan d'une exécution de `cache:purge-expired`.
 */
final readonly class CachePurgeResult
{
    public function __construct(
        public string $store,
        public string $driver,
        public int $scanned,
        public int $deleted,
        /** Vrai quand le pilote n'est pas `file` : rien à faire. */
        public bool $skipped = false,
    ) {}
}
