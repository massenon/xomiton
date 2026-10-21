<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CaseForm;
use App\Models\User;

/**
 * Fiches de référence VBG / VDH (ROLE_ET_PERMISSIONS.md §3, ligne « Fiches VBG / VDH ») : l'administration
 * (`case_forms.manage`) les remplit, les lit, les imprime et les supprime ; la pair·e lit celles de **ses** dossiers
 * (`case_forms.view_own`), jamais plus.
 */
final class CaseFormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('case_forms.manage') || $user->can('case_forms.view_own');
    }

    public function view(User $user, CaseForm $form): bool
    {
        if ($user->can('case_forms.manage')) {
            return true;
        }

        $case = $form->case;

        return $user->can('case_forms.view_own')
            && $case !== null
            && $case->collected_by !== null
            && (int) $case->collected_by === (int) $user->getKey();
    }

    public function create(User $user): bool
    {
        return $user->can('case_forms.manage');
    }

    public function delete(User $user, CaseForm $form): bool
    {
        return $user->can('case_forms.manage');
    }

    /** Impression A4 / PDF côté serveur (ADR-23) : même droit que la lecture complète de l'administration. */
    public function print(User $user, CaseForm $form): bool
    {
        return $user->can('case_forms.manage');
    }
}
