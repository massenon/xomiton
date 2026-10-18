<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch\Recipients;

use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\User;

/**
 * Destinataires d'une escalade (RG-05, Q48, AlertEscalated) : la cible (point focal national, par son compte lié ou
 * directement) et, quand il n'y en a pas, les admins de garde désignés par la commande. Jamais l'auteur·e.
 */
final class EscalationRecipients
{
    /**
     * @param  list<int>  $adminIds
     * @return list<RecipientTarget>
     */
    public function for(Alert $alert, ?int $toContactId, array $adminIds): array
    {
        $targets = new TargetSet($alert);
        $to = $toContactId === null ? null : DirectoryContact::query()->active()->find($toContactId);

        if ($to !== null) {
            $targets->add(RecipientTarget::forDirectoryContact($to, primary: true));
        }

        if ($adminIds !== []) {
            User::query()->active()->whereKey($adminIds)->orderBy('id')->get()
                ->each(static fn (User $admin) => $targets->add(RecipientTarget::account($admin, RecipientKind::Team)));
        }

        return $targets->all();
    }
}
