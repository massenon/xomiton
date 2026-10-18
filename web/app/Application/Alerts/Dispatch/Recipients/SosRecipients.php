<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch\Recipients;

use App\Application\Alerts\OnCallRoster;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

/**
 * Destinataires d'un SOS (NOTIFICATIONS §2 : contacts de confiance, point focal de zone, admin de garde ; P1-17) :
 *  R1 contact de confiance de l'auteur·e (SOS envoyé depuis un compte, numéro renseigné ; sans nom si anonyme) ;
 *  R2 contact routé (`alerts.routed_to`, RG-04 calculé par le lot domaine : jamais rerouté ici) ;
 *  R3 point focal de la zone (garde, sinon fiche), s'il diffère de R2 ;
 *  R4 point focal national, seulement si R2 et R3 manquent (quelqu'un de l'annuaire est toujours prévenu) ;
 *  R5 admins de garde (repli : admins habilités `alerts.take`).
 * Une personne n'apparaît qu'une fois (un admin lié à une fiche garde le libellé de sa fiche) ; l'auteur·e est exclu·e.
 */
final class SosRecipients
{
    public function __construct(private readonly OnCallRoster $roster) {}

    /**
     * @return list<RecipientTarget>
     */
    public function for(Alert $alert, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $targets = new TargetSet($alert);

        // Un SOS envoyé depuis un compte, même anonyme, prévient le contact de confiance de ce compte (texte sans nom).
        $author = $alert->user;

        if ($author instanceof User) {
            $phone = trim((string) $author->profile?->trusted_contact_phone);

            if ($phone !== '') {
                $targets->add(RecipientTarget::trusted($author, $phone));
            }
        }

        $routed = $alert->routedContact;
        $routed = $routed instanceof DirectoryContact && $routed->is_active ? $routed : null;

        if ($routed !== null) {
            $targets->add(RecipientTarget::forDirectoryContact($routed, primary: true));
        } else {
            Log::warning('alerts.unrouted', ['ulid' => $alert->ulid]);
        }

        $focal = $alert->zone === null ? null : $this->roster->zoneFocal($alert->zone, $at);

        if ($focal !== null && (int) $focal->getKey() !== (int) $routed?->getKey()) {
            $targets->add(RecipientTarget::forDirectoryContact($focal));
        }

        if ($routed === null && $focal === null && ($national = $this->roster->nationalFocal($at)) !== null) {
            $targets->add(RecipientTarget::forDirectoryContact($national, primary: true));
        }

        foreach ($this->roster->adminsOnCall($at) as $admin) {
            $targets->add(RecipientTarget::account($admin, RecipientKind::Team));
        }

        return $targets->all();
    }
}
