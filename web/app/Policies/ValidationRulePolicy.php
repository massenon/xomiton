<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ValidationRule;

/**
 * Règles de validation (ROLE_ET_PERMISSIONS.md §3, ligne « Règles de validation ») : l'administration
 * (`validation_rules.manage`) lit le catalogue, active ou désactive ses règles, règle la plausibilité et les doublons
 * suspects. Les règles verrouillées (`required`, `password_strong`) ne se désactivent jamais, quelle que soit la
 * permission (ManageValidationRules).
 */
final class ValidationRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('validation_rules.manage');
    }

    public function view(User $user, ValidationRule $rule): bool
    {
        return $user->can('validation_rules.manage');
    }

    public function update(User $user, ValidationRule $rule): bool
    {
        return $user->can('validation_rules.manage');
    }
}
