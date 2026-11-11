<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

/** Fenêtre planifiée qui chevauche une fenêtre active, en cours ou à venir (P13-06 : chevauchement refusé, 422). */
final class MaintenanceWindowOverlaps extends MaintenanceException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct('maintenance.errors.overlap', 'starts_at', ['from' => $from, 'to' => $to]);
    }

    public function errorCode(): string
    {
        return 'maintenance_overlap';
    }
}
