<?php

declare(strict_types=1);

namespace App\Domain\Sync\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Conflit détecté par le moteur de synchronisation (SOFTWARE_ARCHITECTURE §5, RG-15 ; P3-08) : identifiant du conflit,
 * appareil, sujet (classe morphée + identifiant) et champ ; jamais les valeurs en conflit (chiffrées en base).
 */
final readonly class SyncConflictDetected
{
    use Dispatchable;

    public function __construct(
        public int $conflictId,
        public int $deviceId,
        public string $subjectType,
        public int $subjectId,
        public string $field,
        public DateTimeImmutable $occurredAt,
    ) {}
}
