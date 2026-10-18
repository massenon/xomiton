<?php

declare(strict_types=1);

namespace App\Http\Resources\Content;

use App\Models\DirectoryContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contact d'annuaire lié à une fiche thématique (P6-08, `GET thematiques/{slug}`) : ShowThematique ne renvoie que des
 * contacts **actifs et publics** (Q19) ; nom, rôle, structure, téléphone, WhatsApp, département, disponibilité, point
 * focal national. Jamais l'e-mail, les notes ni le compte lié (comme RoutedContactResource).
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
        $zone = $contact->relationLoaded('zone') ? $contact->zone : null;

        return [
            'id' => (int) $contact->getKey(),
            'name' => (string) $contact->name,
            'role' => $contact->role->value,
            'role_label' => $contact->role->label(),
            'org' => $contact->org,
            'phone' => $contact->phone,
            'whatsapp' => $contact->whatsapp,
            'zone' => $zone === null ? null : ['id' => (int) $zone->getKey(), 'name' => (string) $zone->name],
            'availability' => $contact->availability,
            'is_national_focal' => (bool) $contact->is_national_focal,
        ];
    }
}
