<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

/** Annulation ou « Je suis en sécurité » demandés sur une alerte qui n'est pas un SOS. */
final class NotAnSos extends AlertException
{
    public function __construct()
    {
        parent::__construct('alerts.errors.not_sos');
    }

    public function errorCode(): string
    {
        return 'not_sos';
    }
}
