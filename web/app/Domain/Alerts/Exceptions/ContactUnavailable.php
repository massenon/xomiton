<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

/** Contact de l'annuaire inactif, supprimé ou non proposable (demande de rappel, réattribution). */
final class ContactUnavailable extends AlertException
{
    public function __construct()
    {
        parent::__construct('alerts.errors.contact_unavailable');
    }

    public function errorCode(): string
    {
        return 'contact_unavailable';
    }
}
