<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Application\Retention\Concerns\DeletesInChunks;
use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Log;

/**
 * Rétention des réponses mémorisées par le middleware `idempotent` (P5-09, ADR-32) : supprime les lignes dont
 * `expires_at` est passé (TTL `xomiton.idempotency.ttl_hours`). Une réservation encore valable n'est jamais touchée.
 */
final class PurgeExpiredIdempotencyKeys
{
    use DeletesInChunks;

    /**
     * @return int Nombre de lignes supprimées.
     */
    public function run(): int
    {
        $deleted = $this->deleteInChunks(IdempotencyKey::query()->expired());

        Log::info('Rétention : clés d’idempotence expirées purgées.', ['count' => $deleted]);

        return $deleted;
    }
}
