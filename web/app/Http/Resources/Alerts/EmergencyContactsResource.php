<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Application\Alerts\Results\EmergencyContacts;
use App\Application\Shared\KnowledgeBaseAnswerer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET urgences` (P5-06) : `services[]` (numéros nationaux, lien `tel:` prêt à l'emploi) et `contacts[]` (fiches
 * d'urgence publiques, EmergencyContactResource) ; `meta.zone` = département demandé, `meta.count` = nombre de fiches,
 * `meta.truncated` = d'autres fiches existent au-delà du plafond (ListEmergencyContacts::MAX_CONTACTS).
 *
 * @property EmergencyContacts $resource
 */
final class EmergencyContactsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'services' => array_map(static fn (array $service): array => [
                'label' => $service['label'],
                'number' => $service['number'],
                'tel' => KnowledgeBaseAnswerer::telHref($service['number']),
            ], $this->resource->services),
            'contacts' => EmergencyContactResource::collection($this->resource->contacts)->resolve($request),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        $zone = $this->resource->zone;

        return ['meta' => [
            'zone' => $zone === null ? null : ['id' => (int) $zone->getKey(), 'name' => (string) $zone->name],
            'count' => $this->resource->contacts->count(),
            'truncated' => $this->resource->truncated,
        ]];
    }
}
