<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Cases\CaseCode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Code de dossier au format normalisé `VBG-0000` / `VDH-0000` (règle `case_code` du catalogue, RG-06). Strict :
 * « vbg42 » est refusé (la recherche et les adresses, elles, passent par CaseCode::tryParse, tolérant).
 */
final class CaseCodeFormat implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! CaseCode::isValid($value)) {
            $fail('validation.case_code')->translate();
        }
    }
}
