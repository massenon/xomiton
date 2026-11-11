<?php

declare(strict_types=1);

namespace App\Application\Cases\Data;

use App\Domain\Cases\CaseNotes;
use App\Enums\CaseStatus;

/**
 * Modification partielle d'un dossier, validée par CaseRules::forUpdate() : seuls les champs envoyés, plus le statut
 * visé s'il est fourni. La nature et le code ne changent jamais.
 */
final readonly class CaseChanges
{
    /** Champs modifiables (colonnes de `cases`). */
    public const FIELDS = [
        'occurred_on',
        'zone_id',
        'commune_id',
        'population_id',
        'sex',
        'age_range',
        'violence_type_id',
        'referral',
        'notes',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public array $attributes,
        public ?CaseStatus $status = null,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $attributes = array_intersect_key($validated, array_flip(self::FIELDS));

        if (array_key_exists('notes', $attributes)) {
            $attributes['notes'] = CaseNotes::normalize($attributes['notes'] === null ? null : (string) $attributes['notes']);
        }

        if (array_key_exists('referral', $attributes) && $attributes['referral'] === '') {
            $attributes['referral'] = null;
        }

        return new self($attributes, isset($validated['status']) ? CaseStatus::from((string) $validated['status']) : null);
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [] && $this->status === null;
    }
}
