<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

/** Lien d'accusé de réception qui ne désigne ni le contact routé ni une cible d'escalade active de l'alerte. */
final class AcknowledgeLinkInvalid extends AlertException
{
    public function __construct()
    {
        parent::__construct('alerts.acknowledge.invalid');
    }

    public function status(): int
    {
        return 403;
    }

    public function errorCode(): string
    {
        return 'forbidden';
    }
}
