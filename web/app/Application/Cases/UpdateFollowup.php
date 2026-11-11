<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Cases\Data\FollowupChanges;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Enums\CaseStatus;
use App\Models\CaseFollowup;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Modification d'une étape de suivi (P9-02, `PATCH pair/cases/{code}/followups/{id}` ; prototype admin/suivi.php
 * l.17 « marquer comme réalisée ») : autorisation `CaseFollowupPolicy::update` (le pair sur les étapes de **ses**
 * dossiers), dossier archivé ou anonymisé refusé (422 `case_locked`). Audit « case_followups.updated » (champs
 * modifiés, jamais le commentaire).
 */
final class UpdateFollowup
{
    public function __invoke(User $by, CaseFollowup $followup, FollowupChanges $changes): CaseFollowup
    {
        Gate::forUser($by)->authorize('update', $followup);

        $case = $followup->case;

        if ($case === null || $case->isAnonymized() || $case->status === CaseStatus::Archived) {
            throw new CaseLocked;
        }

        $followup->fill($changes->attributes);
        $dirty = array_values(array_intersect(FollowupChanges::FIELDS, array_keys($followup->getDirty())));
        $followup->save();

        if ($dirty !== []) {
            Audit::log('case_followups.updated', $followup, sprintf('dossier=%s ; champs=%s', $case->code, implode(',', $dirty)), $by);
        }

        return $followup;
    }
}
