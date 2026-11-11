<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * POST me/devices sur un appareil révoqué (par la personne, l'administration ou `sync:prune`) : 403 `device_revoked`.
 * L'application génère alors un nouvel `device_uuid` (docs/API.md §3) ; un appareil révoqué ne se réactive jamais seul.
 */
final class DeviceRevoked extends DomainException
{
    public function __construct()
    {
        parent::__construct('account.devices.revoked');
    }

    public function errorCode(): string
    {
        return 'device_revoked';
    }

    public function status(): int
    {
        return 403;
    }
}
