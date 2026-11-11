<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

/**
 * Activation immédiate refusée : la maintenance est déjà active (bascule levée ou fenêtre en cours). Sans ce refus, un
 * onglet resté ouvert ou un second compte réinitialiserait `maint_start` (« depuis », barre d'avancement faux) et
 * ouvrirait une seconde fenêtre en cours (revue du lot maintenance). 422, affiché dans la modale d'activation.
 */
final class MaintenanceAlreadyActive extends MaintenanceException
{
    public function __construct()
    {
        parent::__construct('maintenance.errors.already_active', 'enable');
    }

    public function errorCode(): string
    {
        return 'maintenance_already_active';
    }
}
