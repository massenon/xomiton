<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

/**
 * Garde-fou anti-verrouillage (P13-06) : un compte qui ne garderait pas l'accès pendant la maintenance (rôle autre
 * qu'admin / superadmin, RG-14) ne peut ni l'activer ni la planifier.
 */
final class MaintenanceLockout extends MaintenanceException
{
    public function __construct()
    {
        parent::__construct('maintenance.errors.lockout');
    }

    public function errorCode(): string
    {
        return 'maintenance_lockout';
    }

    public function status(): int
    {
        return 403;
    }
}
