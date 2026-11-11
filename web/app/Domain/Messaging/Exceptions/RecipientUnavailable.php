<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Personne qu'on ne peut pas contacter (compte inactif, profil non visible, blocage dans un sens ou dans l'autre) : un
 * seul message neutre, qui ne dit jamais laquelle de ces raisons s'applique (on ne révèle pas un blocage).
 */
final class RecipientUnavailable extends DomainException
{
    public function __construct(private readonly string $field = 'user_id')
    {
        parent::__construct('community.messages.api.recipient_unavailable');
    }

    public function errorCode(): string
    {
        return 'recipient_unavailable';
    }

    public function errors(): array
    {
        return [$this->field => [$this->userMessage()]];
    }
}
