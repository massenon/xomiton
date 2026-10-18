<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch\Recipients;

use App\Application\Alerts\OnCallRoster;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Models\Alert;
use App\Models\DirectoryContact;
use Carbon\CarbonInterface;

/**
 * Destinataires d'une alerte routée, d'une réattribution et d'un rappel (NOTIFICATIONS §2 « Alerte routée ») : contact
 * de l'annuaire ciblé (actuel) puis admins de garde, une fois par personne, jamais l'auteur·e.
 */
final class RoutedAlertRecipients
{
    public function __construct(private readonly OnCallRoster $roster) {}

    /**
     * @return list<RecipientTarget>
     */
    public function for(Alert $alert, ?CarbonInterface $at = null): array
    {
        $targets = new TargetSet($alert);
        $routed = $alert->routedContact;

        if ($routed instanceof DirectoryContact && $routed->is_active) {
            $targets->add(RecipientTarget::forDirectoryContact($routed, primary: true));
        }

        foreach ($this->roster->adminsOnCall($at ?? now()) as $admin) {
            $targets->add(RecipientTarget::account($admin, RecipientKind::Team));
        }

        return $targets->all();
    }
}
