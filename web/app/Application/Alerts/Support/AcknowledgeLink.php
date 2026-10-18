<?php

declare(strict_types=1);

namespace App\Application\Alerts\Support;

use App\Models\Alert;
use App\Models\DirectoryContact;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\URL;

/**
 * Lien signé d'accusé de réception (P5-02) pour un contact d'annuaire sans compte : généré par P5-03 pour le contact
 * routé et pour la cible d'une escalade, valable 48 h (`xomiton.alerts.acknowledge_link_hours`), lié au couple
 * (alerte, fiche). Le `GET` n'affiche qu'une confirmation : seul le `POST` pose l'accusé (aperçus de liens).
 */
final class AcknowledgeLink
{
    public const ROUTE = 'alertes.accuser';

    private function __construct() {}

    public static function url(Alert $alert, DirectoryContact $contact, ?CarbonInterface $expiresAt = null): string
    {
        $expiresAt ??= now()->addHours((int) config('xomiton.alerts.acknowledge_link_hours', 48));

        return URL::temporarySignedRoute(self::ROUTE, $expiresAt, [
            'alert' => $alert->ulid,
            'contact' => $contact->getKey(),
        ]);
    }

    /**
     * La fiche peut-elle accuser réception de cette alerte ? Active, et contact routé ou cible d'une escalade.
     */
    public static function matches(Alert $alert, DirectoryContact|int|null $contact): bool
    {
        $contact = $contact instanceof DirectoryContact || $contact === null
            ? $contact
            : DirectoryContact::query()->find($contact);

        if ($contact === null || ! $contact->is_active || $contact->trashed()) {
            return false;
        }

        $id = (int) $contact->getKey();

        return (int) $alert->routed_to === $id
            || $alert->escalations()->where('to_contact_id', $id)->exists();
    }
}
