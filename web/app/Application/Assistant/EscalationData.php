<?php

declare(strict_types=1);

namespace App\Application\Assistant;

use App\Enums\AlertSource;
use App\Enums\ContactChannel;

/**
 * « Parler à un humain » depuis l'assistant (P8-11) : moyen de joindre la personne (obligatoire : une personne formée
 * la recontacte), canal préféré et message facultatifs, pseudo transmis seulement si `anonymous = false`, conversation
 * enregistrée à marquer pour relecture (facultative, ignorée d'une demande anonyme : EscalateToHuman).
 */
final readonly class EscalationData
{
    public function __construct(
        public int $userId,
        public string $contact,
        public string $idempotencyKey,
        public AlertSource $source,
        public ?ContactChannel $preferredChannel = null,
        public ?string $message = null,
        public bool $anonymous = true,
        public ?int $conversationId = null,
        public ?int $deviceId = null,
    ) {}
}
