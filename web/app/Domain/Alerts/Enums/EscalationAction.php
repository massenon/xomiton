<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Enums;

/**
 * Décision de la politique d'escalade pour une alerte à un instant donné.
 */
enum EscalationAction: string
{
    case None = 'none';
    case Remind = 'remind';
    case Escalate = 'escalate';

    public function requiresAction(): bool
    {
        return $this !== self::None;
    }
}
