<?php

declare(strict_types=1);

namespace App\Application\Alerts\Data;

use App\Enums\ContactChannel;

/**
 * Demande de rappel depuis l'annuaire membre (annuaire.php l.9-27, P8-12) : contact choisi, moyen de joindre la
 * personne (obligatoire), message facultatif, pseudo transmis seulement si `anonymous = false`.
 */
final readonly class CallbackData
{
    public function __construct(
        public int $contactId,
        public int $userId,
        public string $contact,
        public string $idempotencyKey,
        public ?ContactChannel $preferredChannel = null,
        public ?string $message = null,
        public bool $anonymous = true,
    ) {}
}
