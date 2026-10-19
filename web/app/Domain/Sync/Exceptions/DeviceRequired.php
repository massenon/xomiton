<?php

declare(strict_types=1);

namespace App\Domain\Sync\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * `sync/*` et `heartbeat` sans appareil enregistré du compte (`X-Device-Id` absent, invalide, inconnu ou d'un autre
 * compte) : 403 `device_required`. L'application enregistre d'abord l'appareil par POST me/devices (docs/API.md §3) ;
 * un appareil révoqué reçoit `device_revoked` (App\Domain\Account\Exceptions\DeviceRevoked).
 */
final class DeviceRequired extends DomainException
{
    public function __construct()
    {
        parent::__construct('sync.device.required');
    }

    public function errorCode(): string
    {
        return 'device_required';
    }

    public function status(): int
    {
        return 403;
    }
}
