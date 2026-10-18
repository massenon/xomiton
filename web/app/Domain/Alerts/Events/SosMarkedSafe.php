<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * « Je suis en sécurité » (signaler-et-sos.md l.17) : statut traité, escalade arrêtée. Écouteur attendu (P5-03) :
 * `notifications.sos_safe` aux personnes prévenues. `byUserId` nul pour un SOS anonyme.
 */
final readonly class SosMarkedSafe
{
    use Dispatchable;

    public function __construct(
        public int $alertId,
        public ?int $byUserId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
