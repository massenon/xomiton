<?php

declare(strict_types=1);

namespace App\Application\Shared\Idempotency;

use App\Models\IdempotencyKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Mémoire des requêtes idempotentes (P5-09, ADR-32) en base (`idempotency_keys`, jamais le cache fichier) :
 * même clé + même route + même acteur → même réponse rejouée ; corps différent → 422 `idempotency_key_reused`.
 * Une requête concurrente portant la même clé attend la fin de l'originale (`xomiton.idempotency.wait_seconds`, 2 s,
 * sondages espacés de `poll_milliseconds` en doublant jusqu'à 500 ms : un worker PHP-FPM mutualisé n'est pas bloqué
 * longtemps) puis reçoit sa réponse ; une réservation non complétée depuis `lock_seconds` (processus interrompu) est reprise.
 * Ni l'acteur ni le chemin concret ne sont stockés (portée HMAC, IdempotentRequest::scope()), seulement le gabarit de route.
 * Seules les réponses 2xx sont mémorisées : un échec libère la clé pour que le client corrige et réessaie.
 * La déduplication métier (`alerts.idempotency_key`, `client_uuid`) reste la garantie au-delà du TTL.
 */
final class IdempotencyStore
{
    public function ttlHours(): int
    {
        return max(1, (int) config('xomiton.idempotency.ttl_hours', 48));
    }

    public function waitSeconds(): int
    {
        return max(0, (int) config('xomiton.idempotency.wait_seconds', 2));
    }

    public function lockSeconds(): int
    {
        return max(1, (int) config('xomiton.idempotency.lock_seconds', 60));
    }

    /**
     * Réserve la clé pour cette requête, ou renvoie la réponse déjà mémorisée.
     *
     * @throws IdempotencyRejected corps différent (422) ou requête d'origine toujours en cours (429)
     */
    public function claim(IdempotentRequest $request): IdempotencyClaim
    {
        $budgetMs = $this->waitSeconds() * 1000;
        $waitedMs = 0;
        $pollMs = max(10, (int) config('xomiton.idempotency.poll_milliseconds', 100));

        while (true) {
            $now = now();
            $inserted = IdempotencyKey::query()->insertOrIgnore([
                'idempotency_key' => $request->key,
                'scope' => $request->scope(),
                'method' => $request->method,
                'route' => $request->route,
                'request_hash' => $request->requestHash,
                'expires_at' => $now->copy()->addHours($this->ttlHours()),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $record = $this->find($request);

            if ($record !== null && $inserted === 1) {
                return IdempotencyClaim::reserved($record);
            }

            if ($record !== null && $record->isExpired()) {
                // Fenêtre de rejeu passée mais pas encore purgée : la clé redevient libre.
                $record->delete();

                continue;
            }

            if ($record !== null) {
                if (! hash_equals($record->request_hash, $request->requestHash)) {
                    Log::warning('Idempotence : clé réutilisée avec un autre contenu.', ['method' => $request->method, 'route' => $request->route]);

                    throw IdempotencyRejected::reused();
                }

                if ($record->isCompleted()) {
                    return IdempotencyClaim::replay($record);
                }

                if ($this->isAbandoned($record) && $this->takeOver($record)) {
                    return IdempotencyClaim::reserved($record->refresh());
                }
            }

            if ($waitedMs >= $budgetMs) {
                throw IdempotencyRejected::inProgress(max(1, $this->waitSeconds()));
            }

            $pause = min($pollMs, $budgetMs - $waitedMs);
            Sleep::for($pause)->milliseconds();
            $waitedMs += $pause;
            $pollMs = min(500, $pollMs * 2);
        }
    }

    /**
     * Mémorise la réponse de la requête d'origine.
     *
     * @param  array<string, string>  $headers
     */
    public function complete(IdempotencyKey $record, int $status, array $headers, string $body): void
    {
        $record->forceFill([
            'status_code' => $status,
            'response_headers' => $headers,
            'response_body' => $body,
            'expires_at' => now()->addHours($this->ttlHours()),
        ])->save();
    }

    /** Libère la clé (échec, réponse non rejouable) : le prochain envoi est traité comme neuf. */
    public function release(IdempotencyKey $record): void
    {
        IdempotencyKey::query()->whereKey($record->getKey())->whereNull('status_code')->delete();
    }

    private function find(IdempotentRequest $request): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('scope', $request->scope())
            ->where('idempotency_key', $request->key)
            ->first();
    }

    private function isAbandoned(IdempotencyKey $record): bool
    {
        return $record->updated_at === null || $record->updated_at->lessThanOrEqualTo(now()->subSeconds($this->lockSeconds()));
    }

    /** Reprise atomique d'une réservation abandonnée : une seule requête concurrente l'emporte. */
    private function takeOver(IdempotencyKey $record): bool
    {
        return IdempotencyKey::query()
            ->whereKey($record->getKey())
            ->whereNull('status_code')
            ->where('updated_at', $record->getRawOriginal('updated_at'))
            ->update(['updated_at' => now()]) === 1;
    }
}
