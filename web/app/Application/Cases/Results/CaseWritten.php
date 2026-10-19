<?php

declare(strict_types=1);

namespace App\Application\Cases\Results;

use App\Models\CaseRecord;

/**
 * Résultat d'une création ou d'une modification de dossier : le dossier, s'il vient d'être créé (201) ou rejoué par
 * son `client_uuid` (200, ADR-32), et les avertissements non bloquants.
 */
final readonly class CaseWritten
{
    /**
     * @param  list<CaseWarning>  $warnings
     */
    public function __construct(
        public CaseRecord $case,
        public bool $created,
        public array $warnings = [],
    ) {}

    /**
     * @return list<array{code: string, message: string, codes: list<string>}>
     */
    public function warningsArray(): array
    {
        return array_map(static fn (CaseWarning $warning): array => $warning->toArray(), $this->warnings);
    }
}
