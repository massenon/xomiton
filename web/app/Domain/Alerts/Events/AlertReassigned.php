<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Réattribution manuelle d'une alerte à un autre contact de l'annuaire (P5-02, `routing_fallback = manual`).
 * Écouteur attendu (P5-03) : notification AlertRouted au nouveau contact.
 */
final readonly class AlertReassigned
{
    use Dispatchable;

    public function __construct(
        public int $alertId,
        public string $type,
        public ?int $fromContactId,
        public int $toContactId,
        public int $byUserId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
