<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Domain\Alerts\Dispatch\NotifiedState;
use App\Domain\Alerts\Dispatch\RecipientKind;
use Carbon\CarbonImmutable;

/**
 * Une personne prévenue d'une alerte, telle que la voit l'auteur·e (page /sos, `GET sos/{id}`) : libellé public
 * (jamais le nom réel d'une fiche non publique ni celui d'un·e admin, RG-02), rôle, état agrégé, téléphone seulement
 * pour une fiche publique ou le propre contact de confiance de l'auteur·e.
 */
final readonly class NotifiedPerson
{
    public function __construct(
        public string $key,
        public RecipientKind $kind,
        public string $label,
        public string $role,
        public NotifiedState $state,
        public ?string $phone,
        public ?CarbonImmutable $seenAt,
        public ?CarbonImmutable $updatedAt,
        public bool $routed = false,
    ) {}
}
