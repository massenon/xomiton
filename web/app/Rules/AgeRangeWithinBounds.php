<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Cases\Plausibility;
use App\Enums\AgeRange;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Tranche d'âge comprise entre les bornes de plausibilité (règle `age_range` du catalogue, RG-08 reformulée par
 * Q25 a : « tranche comprise entre 10-14 et 50+ », bornes `age_min` / `age_max` de la règle `plausibility`). À placer
 * après `Rule::enum(AgeRange::class)` : une valeur hors liste est refusée par celle-ci.
 */
final class AgeRangeWithinBounds implements ValidationRule
{
    public function __construct(private readonly Plausibility $plausibility) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $range = is_string($value) ? AgeRange::tryFrom($value) : null;

        if ($range !== null && ! $this->plausibility->acceptsAgeRange($range)) {
            $fail('validation.age_range')->translate([
                'min' => $this->plausibility->ageMin,
                'max' => $this->plausibility->ageMax,
            ]);
        }
    }
}
