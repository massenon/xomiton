<?php

declare(strict_types=1);

namespace App\Application\Cases\Results;

use App\Models\CaseFollowup;

/**
 * Résultat d'un ajout d'étape de suivi : l'étape et si elle vient d'être créée (201) ou rejouée (200, ADR-32).
 */
final readonly class FollowupWritten
{
    public function __construct(
        public CaseFollowup $followup,
        public bool $created,
    ) {}
}
