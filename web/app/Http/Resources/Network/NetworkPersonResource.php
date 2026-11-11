<?php

declare(strict_types=1);

namespace App\Http\Resources\Network;

use App\Domain\Community\NetworkRules;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Suggestion « Qui suivre » (P8-06, docs/API.md §17) : NetworkPersonPayload plus l'état propre à la personne qui lit —
 * `following` (attribut `followed_by_viewer` de NetworkSuggestions) — et ce qu'elle peut faire (`can.follow` : profil
 * ouvert au réseau ; `can.invite`).
 *
 * @mixin User
 */
final class NetworkPersonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return NetworkPersonPayload::for($user) + [
            'following' => (bool) ($user->getAttribute('followed_by_viewer') ?? false),
            'can' => [
                'follow' => NetworkRules::followable($user->profile?->visibility),
                'invite' => true,
            ],
        ];
    }
}
