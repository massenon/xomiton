<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Events;

use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Accusé de réception d'une alerte (P5-02) : par un compte (`via = account`, `byUserId`) ou par le lien signé d'un
 * contact d'annuaire sans compte (`via = signed_link`, `contactId`). Arrête l'escalade RG-05. Identifiants seulement.
 * Écouteurs attendus (P5-03) : retour à la personne (`GET sos/{id}`), cloche de l'administration.
 */
final readonly class AlertAcknowledged
{
    use Dispatchable;

    public const VIA_ACCOUNT = 'account';

    public const VIA_SIGNED_LINK = 'signed_link';

    public function __construct(
        public int $alertId,
        public string $type,
        public ?int $byUserId,
        public ?int $contactId,
        public string $via,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function viaSignedLink(): bool
    {
        return $this->via === self::VIA_SIGNED_LINK;
    }
}
