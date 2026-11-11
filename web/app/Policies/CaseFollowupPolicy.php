<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;

/**
 * Suivi des victimes : les droits découlent du dossier parent (`case_followups.view_own` sur ses dossiers,
 * `case_followups.manage` pour l'administration).
 */
final class CaseFollowupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('case_followups.manage') || $user->can('case_followups.view_own');
    }

    public function view(User $user, CaseFollowup $followup): bool
    {
        if ($user->can('case_followups.manage')) {
            return true;
        }

        return $user->can('case_followups.view_own') && $this->ownsCase($user, $followup->case);
    }

    /**
     * Ajout d'un suivi sur un dossier donné : `$this->authorize('create', [CaseFollowup::class, $case])`.
     */
    public function create(User $user, ?CaseRecord $case = null): bool
    {
        if ($user->can('case_followups.manage')) {
            return true;
        }

        if ($case === null) {
            return $user->can('cases.update_own');
        }

        return $user->can('cases.update_own') && $this->ownsCase($user, $case);
    }

    public function update(User $user, CaseFollowup $followup): bool
    {
        if ($user->can('case_followups.manage')) {
            return true;
        }

        return $user->can('cases.update_own') && $this->ownsCase($user, $followup->case);
    }

    public function delete(User $user, CaseFollowup $followup): bool
    {
        return $user->can('case_followups.manage');
    }

    private function ownsCase(User $user, ?CaseRecord $case): bool
    {
        return $case !== null && $case->collected_by !== null && (int) $case->collected_by === (int) $user->getKey();
    }
}
