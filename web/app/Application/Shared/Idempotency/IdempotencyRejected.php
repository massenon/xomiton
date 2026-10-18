<?php

declare(strict_types=1);

namespace App\Application\Shared\Idempotency;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus du middleware `idempotent` (ADR-32, docs/API.md §1.3) — jamais 409 (réservé au conflit de champ de sync/push ;
 * la boîte d'envoi traite un 409 comme un échec) :
 * - `idempotency_key_required` (422) : en-tête absent sur une route qui l'exige ;
 * - `idempotency_key_invalid` (422) : en-tête qui n'est pas un UUID ;
 * - `idempotency_key_reused` (422, brouillon IETF « Idempotency-Key ») : même clé, même route, même acteur mais corps
 *   différent : erreur du client (le corps rejoué doit être identique : ni compteur d'essais ni horodatage d'envoi) ;
 * - `idempotency_in_progress` (429 + Retry-After) : la requête d'origine est encore en cours au-delà de l'attente ;
 *   rejouable. Sur une route `idempotent:…,nonblocking` (POST sos, P2-08), le middleware répond 202 à la place.
 */
final class IdempotencyRejected extends DomainException
{
    public const REQUIRED = 'idempotency_key_required';

    public const INVALID = 'idempotency_key_invalid';

    public const REUSED = 'idempotency_key_reused';

    public const IN_PROGRESS = 'idempotency_in_progress';

    /** Champ porté par `errors` pour les refus de validation de l'en-tête. */
    public const FIELD = 'idempotency_key';

    private function __construct(
        private readonly string $reason,
        private readonly int $httpStatus,
        private readonly ?int $retryAfter = null,
    ) {
        parent::__construct('errors.api.idempotency.'.$reason, ['seconds' => (string) ($retryAfter ?? 1)]);
    }

    public static function required(): self
    {
        return new self(self::REQUIRED, 422);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID, 422);
    }

    public static function reused(): self
    {
        return new self(self::REUSED, 422);
    }

    public static function inProgress(int $retryAfter): self
    {
        return new self(self::IN_PROGRESS, 429, max(1, $retryAfter));
    }

    public function isInProgress(): bool
    {
        return $this->reason === self::IN_PROGRESS;
    }

    public function retryAfter(): int
    {
        return $this->retryAfter ?? 1;
    }

    public function errorCode(): string
    {
        return $this->reason;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    public function errors(): array
    {
        return in_array($this->reason, [self::REQUIRED, self::INVALID, self::REUSED], true)
            ? [self::FIELD => [$this->userMessage()]]
            : [];
    }

    public function extra(): array
    {
        return $this->retryAfter !== null ? ['retry_after' => $this->retryAfter] : [];
    }

    public function headers(): array
    {
        return $this->retryAfter !== null ? ['Retry-After' => (string) $this->retryAfter] : [];
    }
}
