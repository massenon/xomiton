<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Signalement d'une conversation de groupe sans message d'une autre personne : rien à transmettre à la modération.
 */
final class NothingToReport extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.messages.api.nothing_to_report');
    }

    public function errorCode(): string
    {
        return 'nothing_to_report';
    }

    public function errors(): array
    {
        return ['message_id' => [$this->userMessage()]];
    }
}
