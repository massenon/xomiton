<?php

declare(strict_types=1);

namespace App\Http\Resources\Member;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Carte « Connexions » du profil (P8-07, prototype `my-profile-connections.php`) : pseudo, avatar, rôle, zone si la
 * personne l'affiche, lien vers son profil et vers la messagerie. RG-02 : jamais le nom réel.
 *
 * @mixin User
 */
final class ProfileConnectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $isViewer = $viewer instanceof User && $viewer->is($user);
        $role = $user->getRoleNames()->first();
        $showZone = (bool) ($user->profile?->show_zone ?? true);

        return [
            'id' => (int) $user->getKey(),
            'pseudo' => (string) $user->pseudo,
            'avatar_url' => $user->avatar_url,
            'role_label' => (string) __('profile.roles.'.($role ?? 'membre')),
            'zone_label' => $showZone ? $user->zone?->name : null,
            'url' => $isViewer ? route('profil') : route('membres.show', ['pseudo' => $user->pseudo]),
            'message_url' => $isViewer ? null : route('messages', ['with' => $user->getKey()]),
        ];
    }
}
