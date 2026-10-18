<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Conversation privée où l'on ne peut plus écrire (blocage dans un sens ou dans l'autre, autre compte inactif) : 403
 * avec le message neutre de l'écran (« Cette conversation n'est plus disponible. »), quelle que soit la raison.
 */
final class ConversationUnavailable extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.messages.api.conversation_unavailable');
    }

    public function errorCode(): string
    {
        return 'conversation_unavailable';
    }

    public function status(): int
    {
        return 403;
    }
}
