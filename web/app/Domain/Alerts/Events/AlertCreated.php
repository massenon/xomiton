<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Signalement ou demande créée (FR-ALT-01, RG-04 ; P3-08). Identifiants et valeurs d'enum seulement : jamais le récit,
 * la position ni le contact, chiffrés en base et inutiles aux écouteurs (notification du contact routé, journal).
 * Un SOS émet SosTriggered, pas cet événement.
 */
final readonly class AlertCreated
{
    use Dispatchable;

    public function __construct(
        public int $alertId,
        public string $type,
        public string $priority,
        public ?int $zoneId,
        public ?int $communeId,
        public ?int $routedToContactId,
        public ?string $routingFallback,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function isRouted(): bool
    {
        return $this->routedToContactId !== null;
    }
}
