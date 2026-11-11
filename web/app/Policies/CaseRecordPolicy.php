<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CaseRecord;
use App\Models\User;
use App\Policies\Scopes\CaseRecordScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dossiers VBG / VDH (ROLE_ET_PERMISSIONS.md §3 à §5) : un pair ne voit et ne modifie que ses dossiers
 * (`cases.create`, `cases.update_own`, scope `collected_by`) ; `cases.view_all` et `cases.export` pour l'administration.
 */
final class CaseRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('cases.view_all') || $user->can('cases.create');
    }

    public function view(User $user, CaseRecord $case): bool
    {
        if ($user->can('cases.view_all')) {
            return true;
        }

        return $this->isCollector($user, $case) && ($user->can('cases.create') || $user->can('cases.update_own'));
    }

    public function create(User $user): bool
    {
        return $user->can('cases.create');
    }

    public function update(User $user, CaseRecord $case): bool
    {
        if ($user->can('cases.view_all')) {
            return true;
        }

        return $this->isCollector($user, $case) && $user->can('cases.update_own');
    }

    public function close(User $user, CaseRecord $case): bool
    {
        return $this->update($user, $case);
    }

    public function delete(User $user, CaseRecord $case): bool
    {
        return $user->can('cases.view_all');
    }

    public function export(User $user): bool
    {
        return $user->can('cases.export');
    }

    /**
     * Requête des dossiers visibles, identique à `view()` ligne par ligne (les exports appliquent le même scope).
     *
     * @return Builder<CaseRecord>
     */
    public function scope(User $user): Builder
    {
        return CaseRecordScope::visibleTo($user);
    }

    private function isCollector(User $user, CaseRecord $case): bool
    {
        return $case->collected_by !== null && (int) $case->collected_by === (int) $user->getKey();
    }
}
