<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Models\DirectoryContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contact routé tel que la personne le voit (parité api.php l.65-66) : rôle toujours ; nom, téléphone et WhatsApp
 * seulement si la fiche est publique (P5-02). Jamais l'e-mail, les notes ni le compte lié.
 *
 * @property DirectoryContact $resource
 */
final class RoutedContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = $this->resource;

        return [
            'role' => $contact->role->label(),
            ...($contact->is_public ? [
                'name' => (string) $contact->name,
                'phone' => $contact->phone,
                'whatsapp' => $contact->whatsapp,
            ] : []),
        ];
    }
}
