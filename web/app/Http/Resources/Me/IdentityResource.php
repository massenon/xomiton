<?php

declare(strict_types=1);

namespace App\Http\Resources\Me;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Section « Profil » de /parametres (GET / PATCH me/identity, P8-09) : la personne connectée seulement. Nom et
 * téléphone ne sortent jamais ailleurs (RG-02 : seul le pseudonyme est public).
 *
 * @mixin User
 */
final class IdentityResource extends JsonResource
{
    /**
     * @return array{name: string|null, pseudo: string, email: string, phone: string|null, bio: string|null, zone_id: int|null, commune_id: int|null}
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'name' => $user->name,
            'pseudo' => (string) $user->pseudo,
            'email' => (string) $user->email,
            'phone' => $user->phone,
            'bio' => $user->profile?->bio,
            'zone_id' => $user->zone_id !== null ? (int) $user->zone_id : null,
            'commune_id' => $user->commune_id !== null ? (int) $user->commune_id : null,
        ];
    }
}
