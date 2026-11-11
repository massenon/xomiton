<?php

declare(strict_types=1);

namespace App\Domain\Account\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * POST me/devices avec un `device_uuid` déjà enregistré par un autre compte (téléphone partagé, réinstallation non
 * propre) : 422 `device_taken` sur `device_uuid`. Jamais réattribué : les alertes et dossiers de l'appareil
 * (`alerts.device_id`, `cases.device_id`) resteraient liés au premier compte. L'application génère un nouvel UUID.
 */
final class DeviceTaken extends DomainException
{
    public function __construct()
    {
        parent::__construct('account.devices.taken');
    }

    public function errorCode(): string
    {
        return 'device_taken';
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return ['device_uuid' => [$this->userMessage()]];
    }
}
