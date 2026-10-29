<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\RoleInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Invitation à l'équipe (`admin/invitations`) : rôle, statut, empreinte du code (4 derniers caractères), adresse liée,
 * expiration, compte inscrit. Le code et le lien ne sortent qu'une fois, dans la réponse 201 de création (`code`, `link`).
 *
 * @property RoleInvitation $resource
 */
final class AdminInvitationResource extends JsonResource
{
    /**
     * @param  array{code: string, link: string}|null  $secret
     */
    public function __construct($resource, private readonly ?array $secret = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;

        return [
            'id' => (int) $invitation->getKey(),
            'role' => $invitation->role,
            'status' => $invitation->isExpired() ? 'expired' : $invitation->status,
            'code_hint' => $invitation->code_hint,
            'email' => $invitation->email,
            'note' => $invitation->note,
            'invited_by' => $invitation->relationLoaded('inviter') ? $invitation->inviter?->pseudo : null,
            'user' => $invitation->relationLoaded('user') && $invitation->user !== null ? ['id' => (int) $invitation->user->getKey(), 'pseudo' => $invitation->user->pseudo] : null,
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'redeemed_at' => $invitation->redeemed_at?->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
        ] + ($this->secret === null ? [] : ['code' => $this->secret['code'], 'link' => $this->secret['link']]);
    }
}
