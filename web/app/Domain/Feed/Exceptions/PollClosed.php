<?php

declare(strict_types=1);

namespace App\Domain\Feed\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/** Vote ou retrait demandé sur un sondage terminé (échéance passée ou clôture) : les votes sont clos. */
final class PollClosed extends DomainException
{
    public function __construct()
    {
        parent::__construct('feed.poll.already_closed');
    }

    public function errorCode(): string
    {
        return 'poll_closed';
    }
}
