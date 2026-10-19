<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

/** Fenêtre déjà terminée ou annulée : rien à annuler (P13-06). */
final class MaintenanceWindowClosed extends MaintenanceException
{
    public function __construct()
    {
        parent::__construct('maintenance.errors.window_closed', 'window');
    }

    public function errorCode(): string
    {
        return 'maintenance_window_closed';
    }
}
