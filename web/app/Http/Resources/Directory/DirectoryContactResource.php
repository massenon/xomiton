<?php

declare(strict_types=1);

namespace App\Http\Resources\Directory;

use App\Application\Directory\BrowseDirectory;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Domain\Directory\DirectoryReach;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\WhatsAppLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche de l'annuaire membre (P8-12 ; prototype annuaire.php l.84-108) : nom, rôle (libellé et couleur de
 * DirectoryRole::memberBadge(), les mêmes que sur /urgences : jamais le rouge de l'urgence, Q62), organisation, commune
 * et département (ou national), disponibilité, téléphone (`tel` : lien d'appel), WhatsApp (`whatsapp_url` :
 * WhatsAppLink, numéro WhatsApp sinon téléphone, indicatif 229) et e-mail. `is_public` faux : fiche réservée au réseau
 * (servie seulement à une portée `network`). `can.callback` : DirectoryReach::canRequestCallback() (Q51 : fiche
 * publique seule), la portée étant calculée une fois par requête. Jamais les notes ni le compte lié.
 *
 * @property DirectoryContact $resource
 */
final class DirectoryContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = $this->resource;
        $phone = self::filled($contact->phone);
        $whatsapp = self::filled($contact->whatsapp);
        $isPublic = (bool) $contact->is_public;

        return [
            'id' => (int) $contact->getKey(),
            'name' => (string) $contact->name,
            'role' => $contact->role->value,
            'role_label' => $contact->role->label(),
            'role_color' => $contact->role->memberBadge(),
            'org' => self::filled($contact->org),
            'phone' => $phone,
            'tel' => $phone === null ? null : KnowledgeBaseAnswerer::telHref($phone),
            'whatsapp' => $whatsapp,
            'whatsapp_url' => WhatsAppLink::for($whatsapp, $phone),
            'email' => self::filled($contact->email),
            'availability' => self::filled($contact->availability),
            'zone' => $contact->zone === null ? null : ['id' => (int) $contact->zone->getKey(), 'name' => (string) $contact->zone->name],
            'commune' => $contact->commune === null ? null : ['id' => (int) $contact->commune->getKey(), 'name' => (string) $contact->commune->name],
            'national' => $contact->zone_id === null || $contact->is_national_focal,
            'is_public' => $isPublic,
            'can' => ['callback' => self::reachOf($request)->canRequestCallback($isPublic)],
        ];
    }

    /** Portée de la personne connectée (BrowseDirectory::reachOf), calculée une fois par requête. */
    private static function reachOf(Request $request): DirectoryReach
    {
        $reach = $request->attributes->get(DirectoryReach::class);

        if (! $reach instanceof DirectoryReach) {
            $viewer = $request->user() ?? $request->user('sanctum');
            $reach = $viewer instanceof User ? BrowseDirectory::reachOf($viewer) : DirectoryReach::None;
            $request->attributes->set(DirectoryReach::class, $reach);
        }

        return $reach;
    }

    private static function filled(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }
}
