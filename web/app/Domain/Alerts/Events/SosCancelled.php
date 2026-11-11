<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * SOS annulé dans la fenêtre de repentir (Q39, 60 s) : « fausse alerte ». Écouteur attendu (P5-03) :
 * `notifications.sos_cancelled` aux personnes déjà prévenues (`alert_notifications`). `byUserId` nul pour un SOS anonyme.
 */
final readonly class SosCancelled
{
    use Dispatchable;

    public function __construct(
        public int $alertId,
        public ?int $byUserId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
