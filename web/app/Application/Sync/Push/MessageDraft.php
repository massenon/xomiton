<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

/**
 * Message écrit hors connexion (P9-06, P9-07) : conversation (ULID) et texte ; le `client_uuid` est l'uuid de
 * l'élément (SendMessage, unique par conversation, ADR-32). Pièces jointes : route dédiée seulement (multipart).
 */
final readonly class MessageDraft
{
    public function __construct(
        public string $conversation,
        public string $body,
    ) {}
}
