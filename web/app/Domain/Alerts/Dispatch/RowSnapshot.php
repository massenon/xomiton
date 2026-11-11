<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Dispatch;

use DateTimeImmutable;

/**
 * Ligne `alert_notifications` réduite à ce qui décide l'état « vu / SMS envoyé / envoyé / en attente » (P5-03).
 */
final readonly class RowSnapshot
{
    public function __construct(
        public string $channel,
        public string $status,
        public ?DateTimeImmutable $seenAt = null,
    ) {}
}
