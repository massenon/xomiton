<?php

declare(strict_types=1);

namespace App\Application\Sync\Conflicts;

use App\Application\Cases\Results\CaseWarning;
use App\Models\CaseRecord;

/**
 * Résultat d'ApplyCaseChanges : dossier à jour, champs appliqués, champs où le serveur est gardé, conflits enregistrés,
 * avertissements non bloquants (CaseQuality, comme `PATCH pair/cases/{code}`).
 */
final readonly class CaseChangeResult
{
    /**
     * @param  list<string>  $applied
     * @param  list<string>  $kept
     * @param  list<array{field: string, conflict_id: int}>  $conflicts
     * @param  list<CaseWarning>  $warnings
     */
    public function __construct(
        public CaseRecord $case,
        public array $applied,
        public array $kept,
        public array $conflicts,
        public array $warnings = [],
    ) {}

    /**
     * @return list<array{code: string, message: string, codes: list<string>}>
     */
    public function warningsArray(): array
    {
        return array_map(static fn (CaseWarning $warning): array => $warning->toArray(), $this->warnings);
    }

    public function hasConflicts(): bool
    {
        return $this->conflicts !== [];
    }
}
