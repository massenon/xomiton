<?php

declare(strict_types=1);

namespace App\Application\Admin\OnCall;

use App\Models\OnCallShift;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Suppression d'une plage de garde (P1-17, `on_call.manage`). Supprimer une plage EN COURS retire aussitôt la personne
 * des destinataires (OnCallRoster) : l'écran demande confirmation et avertit ; la ligne de journal « on_call.deleted »
 * précise « en cours ». Les SOS déjà notifiés ne sont pas touchés.
 */
final class DeleteOnCallShift
{
    /** Vrai si la plage supprimée était en cours. */
    public function __invoke(User $by, OnCallShift $shift): bool
    {
        Gate::forUser($by)->authorize('delete', $shift);

        $wasActive = $shift->isActive();
        $detail = SaveOnCallShift::detail($shift).($wasActive ? ' ; en cours' : '');
        $shift->delete();

        Audit::log('on_call.deleted', null, $detail, $by);

        return $wasActive;
    }
}
