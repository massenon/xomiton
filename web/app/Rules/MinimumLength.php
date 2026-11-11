<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Cases\CaseNotes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Texte vide ou d'au moins 20 caractères (règle `min_len_20` du catalogue), espaces de début et de fin ignorés :
 * notes d'un dossier, récits. Un champ obligatoire porte aussi `required` (le vide est alors refusé par celle-ci).
 */
final class MinimumLength implements ValidationRule
{
    public function __construct(private readonly int $min = CaseNotes::MIN_LENGTH) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_string($value) || ! CaseNotes::isAcceptable($value, $this->min)) {
            $fail('validation.min_len_20')->translate(['min' => $this->min]);
        }
    }
}
