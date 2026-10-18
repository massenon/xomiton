<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Exceptions;

use App\Enums\AlertStatus;

/** Changement de statut hors de AlertStatus::allowedTransitions() (`clos` est définitif). */
final class InvalidStatusTransition extends AlertException
{
    public function __construct(public readonly AlertStatus $from, public readonly AlertStatus $to)
    {
        parent::__construct('alerts.errors.invalid_transition', ['from' => $from->label(), 'to' => $to->label()]);
    }

    public function errorCode(): string
    {
        return 'invalid_transition';
    }
}
