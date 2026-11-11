<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

/** Repentir d'un SOS demandé après la fenêtre `xomiton.alerts.cancel_window_seconds` (Q39). */
final class CancelWindowExpired extends AlertException
{
    public function __construct(public readonly int $seconds)
    {
        parent::__construct('alerts.errors.cancel_window_expired', ['seconds' => $seconds]);
    }

    public function errorCode(): string
    {
        return 'cancel_window_expired';
    }
}
