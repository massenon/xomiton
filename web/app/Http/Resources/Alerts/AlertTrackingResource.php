<?php

declare(strict_types=1);

namespace App\Http\Resources\Alerts;

use App\Models\Alert;
use App\Models\AlertStatusChange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Suivi par code sans compte (`GET signalements/{code}`, FR-ALT-01) : statut, dates et historique ; du contact routé,
 * le **rôle** seulement (alerts.report.tracking.handled_by), jamais son nom ni aucune donnée de la personne.
 *
 * @property Alert $resource
 */
final class AlertTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $alert = $this->resource;

        return [
            'tracking_code_display' => $alert->displayCode(),
            'type' => AlertResource::apiType($alert->type),
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'status_text' => (string) __('alerts.report.status_text.'.$alert->status->value),
            'received_at' => $alert->created_at?->toIso8601String(),
            'updated_at' => $alert->updated_at?->toIso8601String(),
            'acknowledged' => $alert->isAcknowledged(),
            'routed_role' => $alert->routedContact?->role->label(),
            'history' => $alert->statusChanges->map(static fn (AlertStatusChange $change): array => [
                'status' => $change->to_status->value,
                'status_label' => $change->to_status->label(),
                'at' => $change->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
