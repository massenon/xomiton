<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\RoleRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Demande de rôle pair dans la file de l'administration (D-MOB-15) : la personne (pseudonyme, zone, ancienneté du compte),
 * son motif facultatif et l'état de la demande. L'e-mail et le téléphone ne sortent pas ici (la fiche `admin/members/{id}`
 * les porte pour `users.manage`).
 *
 * @property RoleRequest $resource
 */
final class AdminRoleRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $viewer = $request->user();
        $user = $item->user;

        return [
            'id' => $item->ulid,
            'role' => $item->role,
            'status' => $item->status,
            'reason' => $item->reason,
            'decision_reason' => $item->decision_reason,
            'user' => [
                'id' => (int) $user->getKey(),
                'pseudo' => $user->pseudo,
                'avatar_url' => $user->avatar_url,
                'zone' => $user->zone === null ? null : ['id' => (int) $user->zone->getKey(), 'name' => $user->zone->name],
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'created_at' => $item->created_at?->toIso8601String(),
            'decided_at' => $item->decided_at?->toIso8601String(),
            'can' => [
                'decide' => $item->isPending() && $viewer instanceof \App\Models\User && $viewer->can('assignPeerRole', $user),
            ],
        ];
    }
}
