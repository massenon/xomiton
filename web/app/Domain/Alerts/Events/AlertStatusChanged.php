<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Changement de statut d'une alerte (P5-02 : ligne `alert_status_changes` écrite par le cas d'usage). Valeurs d'enum
 * (`AlertStatus->value`) ; `byUserId` nul pour la personne anonyme ou le système. Écouteur attendu (P5-03) :
 * notification `alert_status_changed` à l'auteur·rice si l'alerte n'est pas anonyme (`authorId`).
 */
final readonly class AlertStatusChanged
{
    use Dispatchable;

    public function __construct(
        public int $alertId,
        public string $type,
        public ?string $from,
        public string $to,
        public ?int $byUserId,
        public ?int $authorId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
