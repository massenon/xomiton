<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Rappel Q27 d'un signalement ou d'un besoin sans accusé (24 h par défaut), par `alerts:escalate` (P5-07) : au contact
 * routé (`routedContactId`), et aux admins de garde (`adminIds`). Écouteur attendu (P5-03) : AlertRouted rejouée avec
 * le texte de rappel.
 */
final readonly class AlertReminderDue
{
    use Dispatchable;

    /**
     * @param  list<int>  $adminIds
     */
    public function __construct(
        public int $alertId,
        public string $type,
        public ?int $routedContactId,
        public array $adminIds,
        public int $minutesSinceCreation,
        public DateTimeImmutable $occurredAt,
    ) {}
}
