<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * POST me/devices/{uuid}/wiped pour un appareil que personne n'a marqué « à effacer » (P15-11) : 422 `wipe_not_requested`.
 * Un accusé ne se fabrique pas : il répond à un ordre d'effacement (administration ou retrait du rôle pair).
 */
final class WipeNotRequested extends DomainException
{
    public function __construct()
    {
        parent::__construct('account.devices.wipe_not_requested');
    }

    public function errorCode(): string
    {
        return 'wipe_not_requested';
    }
}
