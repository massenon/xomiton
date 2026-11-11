<?php

declare(strict_types=1);

namespace App\Application\Cases\Data;

use App\Domain\Cases\CaseNotes;
use App\Enums\AgeRange;
use App\Enums\CaseKind;
use App\Enums\Sex;
use App\Models\CaseRecord;

/**
 * Dossier à créer, validé par CaseRules::forCreate() (Form Request de `pair/cases` ou CaseValidator de `sync/push`).
 */
final readonly class CaseInput
{
    public function __construct(
        public CaseKind $kind,
        public string $occurredOn,
        public int $zoneId,
        public ?int $communeId,
        public int $populationId,
        public Sex $sex,
        public AgeRange $ageRange,
        public int $violenceTypeId,
        public ?string $referral = null,
        public ?string $notes = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            kind: CaseKind::from((string) $validated['kind']),
            occurredOn: (string) $validated['occurred_on'],
            zoneId: (int) $validated['zone_id'],
            communeId: isset($validated['commune_id']) ? (int) $validated['commune_id'] : null,
            populationId: (int) $validated['population_id'],
            sex: Sex::from((string) $validated['sex']),
            ageRange: AgeRange::from((string) $validated['age_range']),
            violenceTypeId: (int) $validated['violence_type_id'],
            referral: isset($validated['referral']) && $validated['referral'] !== '' ? (string) $validated['referral'] : null,
            notes: CaseNotes::normalize(isset($validated['notes']) ? (string) $validated['notes'] : null),
        );
    }

    /**
     * Colonnes de `cases` (hors code, statut, collecte et synchronisation).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'kind' => $this->kind,
            'occurred_on' => $this->occurredOn,
            'zone_id' => $this->zoneId,
            'commune_id' => $this->communeId,
            'population_id' => $this->populationId,
            'sex' => $this->sex,
            'age_range' => $this->ageRange,
            'violence_type_id' => $this->violenceTypeId,
            'referral' => $this->referral,
            'notes' => $this->notes,
        ];
    }

    /**
     * Le dossier enregistré porte-t-il exactement cette saisie ? Rejeu d'un même `client_uuid` (CreateCase) : un écart
     * est un nouvel envoi sous une clé déjà utilisée (422 `idempotency_key_reused`), jamais un doublon silencieux.
     */
    public function matches(CaseRecord $case): bool
    {
        return $case->kind === $this->kind
            && $case->occurred_on?->format('Y-m-d') === $this->occurredOn
            && self::id($case->zone_id) === $this->zoneId
            && self::id($case->commune_id) === $this->communeId
            && self::id($case->population_id) === $this->populationId
            && $case->sex === $this->sex
            && $case->age_range === $this->ageRange
            && self::id($case->violence_type_id) === $this->violenceTypeId
            && $case->referral === $this->referral
            && $case->notes === $this->notes;
    }

    private static function id(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
