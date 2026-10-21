<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Models\CaseForm;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Suppression d'une fiche de référence (P11-02 : « suppression journalisée », prototype admin/fiches.php l.15) :
 * `CaseFormPolicy::delete` (`case_forms.manage`). La fiche disparaît (récit, auteur·e des faits, signataire chiffrés
 * avec elle) ; le dossier reste. Journal d'audit « case_forms.deleted » (référence et code de dossier, jamais le contenu).
 */
final class DeleteCaseForm
{
    public function __invoke(User $by, CaseForm $form): void
    {
        Gate::forUser($by)->authorize('delete', $form);

        $form->loadMissing('case:id,code');
        $detail = sprintf('fiche=%s ; dossier=%s', $form->reference, $form->case?->code ?? '-');
        $form->delete();

        Audit::log('case_forms.deleted', null, $detail, $by);
    }
}
