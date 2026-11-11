<?php

declare(strict_types=1);

namespace App\Application\Alerts\Results;

use App\Models\Alert;

/**
 * Résultat d'un accusé de réception : posé maintenant, déjà posé (aucune écriture) ou refusé car l'alerte est close
 * ou annulée (aucune action attendue).
 */
final readonly class AcknowledgementResult
{
    public function __construct(
        public Alert $alert,
        public bool $alreadyAcknowledged = false,
        public bool $closed = false,
    ) {}

    public function acknowledgedNow(): bool
    {
        return ! $this->alreadyAcknowledged && ! $this->closed;
    }
}
