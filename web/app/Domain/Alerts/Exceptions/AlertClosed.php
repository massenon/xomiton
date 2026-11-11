<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

/** Alerte close ou annulée : plus aucune action de la personne n'est possible. */
final class AlertClosed extends AlertException
{
    public function __construct()
    {
        parent::__construct('alerts.errors.alert_closed');
    }

    public function errorCode(): string
    {
        return 'alert_closed';
    }
}
