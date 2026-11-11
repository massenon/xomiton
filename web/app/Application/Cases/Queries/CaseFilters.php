<?php

declare(strict_types=1);

namespace App\Application\Cases\Queries;

use App\Enums\CaseKind;
use App\Enums\CaseStatus;

/**
 * Filtres de la liste des dossiers (`GET pair/cases`) : statut (archivés exclus sauf demande explicite), nature,
 * recherche sur le **code** seulement (colonne en clair, ADR-33 : jamais dans les notes chiffrées).
 */
final readonly class CaseFilters
{
    public function __construct(
        public ?CaseStatus $status = null,
        public ?CaseKind $kind = null,
        public ?string $code = null,
    ) {}

    /**
     * @return array{status: string|null, kind: string|null, q: string|null}
     */
    public function toArray(): array
    {
        return ['status' => $this->status?->value, 'kind' => $this->kind?->value, 'q' => $this->code];
    }
}
