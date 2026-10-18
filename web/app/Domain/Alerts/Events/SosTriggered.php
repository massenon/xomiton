<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * SOS déclenché (FR-ALT-03, RG-01 ; P3-08) : identifiants seulement. `hasLocation` dit si une position a été partagée
 * (`sos_share_gps`), sans la transporter ; `userId` est nul pour un SOS anonyme. Jamais mis en file (D7).
 */
final readonly class SosTriggered
{
    use Dispatchable;

    public function __construct(
        public int $alertId,
        public ?int $userId,
        public ?int $zoneId,
        public ?int $communeId,
        public bool $hasLocation,
        public ?int $routedToContactId,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function isAnonymous(): bool
    {
        return $this->userId === null;
    }
}
