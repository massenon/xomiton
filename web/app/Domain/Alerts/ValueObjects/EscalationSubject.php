<?php

declare(strict_types=1);

namespace App\Domain\Alerts\ValueObjects;

use App\Enums\AlertType;
use DateTimeImmutable;

/**
 * Photo d'une alerte pour la politique d'escalade RG-05 (construite par l'application depuis le modèle Alert).
 */
final readonly class EscalationSubject
{
    public function __construct(
        public AlertType $type,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $acknowledgedAt = null,
        public ?DateTimeImmutable $cancelledAt = null,
        public ?DateTimeImmutable $safeAt = null,
        public ?DateTimeImmutable $escalatedAt = null,
        public ?DateTimeImmutable $remindedAt = null,
        public bool $isTerminal = false,
    ) {}

    /** Plus aucune relance n'est due : accusée, annulée, en sécurité ou traitée / close. */
    public function isSettled(): bool
    {
        return $this->acknowledgedAt !== null || $this->cancelledAt !== null || $this->safeAt !== null || $this->isTerminal;
    }

    public function minutesSinceCreation(DateTimeImmutable $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $this->createdAt->getTimestamp(), 60));
    }
}
