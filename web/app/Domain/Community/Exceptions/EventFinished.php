<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * « Je participe » refusé : l'événement est terminé (EventDisclosure::acceptsResponses). Annuler reste possible.
 */
final class EventFinished extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.events.errors.past');
    }

    public function errorCode(): string
    {
        return 'event_finished';
    }
}
