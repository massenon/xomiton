<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Action réservée à une conversation de groupe (renommer, ajouter ou retirer des participant·es) ou à une conversation
 * privée (bloquer l'autre personne).
 */
final class WrongConversationKind extends DomainException
{
    private function __construct(string $langKey, private readonly string $errorCode)
    {
        parent::__construct($langKey);
    }

    public static function groupOnly(): self
    {
        return new self('community.messages.api.group_only', 'conversation_group_only');
    }

    public static function privateOnly(): self
    {
        return new self('community.messages.api.private_only', 'conversation_private_only');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
