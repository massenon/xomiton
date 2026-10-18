<?php

declare(strict_types=1);

namespace App\Application\Shared\Idempotency;

use App\Models\IdempotencyKey;

/**
 * Issue de IdempotencyStore::claim() : la clé est réservée pour cette requête (à exécuter puis compléter ou libérer),
 * ou une réponse est déjà mémorisée et doit être rejouée telle quelle.
 */
final readonly class IdempotencyClaim
{
    private function __construct(
        public IdempotencyKey $record,
        public bool $replay,
    ) {}

    public static function reserved(IdempotencyKey $record): self
    {
        return new self($record, false);
    }

    public static function replay(IdempotencyKey $record): self
    {
        return new self($record, true);
    }
}
