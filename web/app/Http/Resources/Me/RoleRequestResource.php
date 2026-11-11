<?php

declare(strict_types=1);

namespace App\Http\Resources\Me;

use App\Models\RoleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de rôle vue par la personne qui l'a faite (D-MOB-15) : identifiant ULID, rôle demandé, statut, son motif et, une
 * fois décidée, le motif de la décision écrit pour elle. Jamais le compte qui a décidé.
 *
 * @property RoleRequest $resource
 */
final class RoleRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        return [
            'id' => $item->ulid,
            'role' => $item->role,
            'status' => $item->status,
            'reason' => $item->reason,
            'decision_reason' => $item->decision_reason,
            'created_at' => $item->created_at?->toIso8601String(),
            'decided_at' => $item->decided_at?->toIso8601String(),
        ];
    }
}
