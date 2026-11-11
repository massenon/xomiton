<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Escalade RG-05 / Q27 par `alerts:escalate` (P5-07) : alerte sans accusé après le délai de son type. `toContactId`
 * = point focal national (garde ou fiche), null quand l'escalade vise les admins de garde (`adminIds`, aucun point
 * focal national ou déjà destinataire). Écouteur attendu (P5-03) : SosUnacknowledged (push, SMS, e-mail
 * `alerte-routee`), lien signé d'accusé pour un contact sans compte. Jamais mis en file (D7).
 */
final readonly class AlertEscalated
{
    use Dispatchable;

    /**
     * @param  list<int>  $adminIds
     */
    public function __construct(
        public int $alertId,
        public string $type,
        public ?int $fromContactId,
        public ?int $toContactId,
        public array $adminIds,
        public string $reason,
        public int $minutesSinceCreation,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function targetsAdmins(): bool
    {
        return $this->toContactId === null;
    }
}
