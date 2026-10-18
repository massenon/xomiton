<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Alerte routée vers un·e pair, en résumé (P6-08, section `peer` de `GET dashboard` ; prototype espace-pair.php l.85-96) :
 * type, statut, priorité, lieu transmis (commune, sinon département : Alert::placeLabel, jamais la position), date,
 * accusé de réception. Jamais le récit, le contact, la position ni l'autrice ou l'auteur : le détail et la prise en
 * charge passent par l'espace pair (P9-03, AlertPolicy).
 *
 * @property Alert $resource
 */
final class RoutedAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alert = $this->resource;

        return [
            'id' => $alert->ulid,
            'type' => AlertResource::apiType($alert->type),
            'type_label' => $alert->type->label(),
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'priority' => $alert->priority->value,
            'priority_label' => $alert->priority->label(),
            'place' => $alert->placeLabel(),
            'acknowledged' => $alert->isAcknowledged(),
            'created_at' => $alert->created_at?->toIso8601String(),
        ];
    }
}
