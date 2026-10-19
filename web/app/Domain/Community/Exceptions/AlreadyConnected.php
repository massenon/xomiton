<?php

declare(strict_types=1);

namespace App\Domain\Community\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Invitation inutile (P8-06) : les deux comptes sont déjà connectés (abonnement dans les deux sens).
 */
final class AlreadyConnected extends DomainException
{
    public function __construct()
    {
        parent::__construct('community.follow.errors.connected');
    }

    public function errorCode(): string
    {
        return 'already_connected';
    }
}
