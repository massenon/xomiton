<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Models\DirectoryContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche publique de l'annuaire sur `/urgences` et `GET urgences` (prototype urgences.php l.80-88 : nom, rôle, commune,
 * département, disponibilité, Appeler, WhatsApp). Fiches `is_public` d'un rôle d'urgence seulement (Q19,
 * ListEmergencyContacts) ; jamais l'e-mail, les notes ni le compte lié.
 *
 * @property DirectoryContact $resource
 */
final class EmergencyContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = $this->resource;

        return [
            'id' => (int) $contact->getKey(),
            'name' => (string) $contact->name,
            'role' => $contact->role->value,
            'role_label' => $contact->role->label(),
            'org' => $contact->org,
            'phone' => $contact->phone,
            'whatsapp' => $contact->whatsapp,
            'availability' => $contact->availability,
            'zone' => $contact->zone === null ? null : ['id' => (int) $contact->zone->getKey(), 'name' => (string) $contact->zone->name],
            'commune' => $contact->commune === null ? null : ['id' => (int) $contact->commune->getKey(), 'name' => (string) $contact->commune->name],
            'national' => $contact->zone_id === null || $contact->is_national_focal,
        ];
    }
}
