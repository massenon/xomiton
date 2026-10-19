<?php

declare(strict_types=1);

namespace App\Http\Resources\Network;

use App\Application\Community\Network\NetworkLinks;
use App\Domain\Community\NetworkRules;
use App\Models\User;

/**
 * Une personne telle qu'elle sort de « Qui suivre et invitations » (P8-06) : **aucune donnée privée** (RG-02) —
 * identifiant du compte, pseudonyme, avatar, rôle public (`member` · `peer` · `team`, badge du prototype, sur tous les
 * rôles du compte : NetworkRules::publicRole) et zone
 * (département) seulement si la personne l'affiche (`profiles.show_zone`), lien du profil public (`/membres/{pseudo}`,
 * P8-07). Jamais le nom réel, l'e-mail, le téléphone, la commune, l'âge, l'identité de genre ni l'orientation.
 */
final class NetworkPersonPayload
{
    private const LABELS = [
        NetworkRules::ROLE_MEMBER => 'community.members.member',
        NetworkRules::ROLE_PEER => 'community.members.peer',
        NetworkRules::ROLE_TEAM => 'community.members.team',
    ];

    private function __construct() {}

    /**
     * @return array{id: int, pseudo: string, avatar_url: string, role: string, role_label: string, zone: array{id: int, name: string}|null, profile_url: string|null}
     */
    public static function for(User $user): array
    {
        $role = NetworkRules::publicRole($user->getRoleNames()->all());
        $zone = NetworkRules::showsZone($user->profile?->show_zone) && $user->zone_id !== null ? $user->zone : null;

        return [
            'id' => (int) $user->getKey(),
            'pseudo' => (string) $user->pseudo,
            'avatar_url' => $user->avatar_url,
            'role' => $role,
            'role_label' => (string) __(self::LABELS[$role]),
            'zone' => $zone !== null ? ['id' => (int) $zone->getKey(), 'name' => (string) $zone->name] : null,
            'profile_url' => NetworkLinks::profile($user),
        ];
    }

    /**
     * Ligne de la carte « Qui suivre » du fil (XmRightColumn, inc/render.php l.70-81) : pseudonyme, avatar, lien du
     * profil (sinon /qui-suivre), « Rôle · Zone » et adresse de l'invitation préparée (`/qui-suivre?invite=<id>`).
     *
     * @return array{id: int, pseudo: string, avatar_url: string, href: string, meta: string, invite_url: string}
     */
    public static function card(User $user): array
    {
        $person = self::for($user);

        return [
            'id' => $person['id'],
            'pseudo' => $person['pseudo'],
            'avatar_url' => $person['avatar_url'],
            'href' => $person['profile_url'] ?? NetworkLinks::index(),
            'meta' => $person['role_label'].($person['zone'] !== null ? ' · '.$person['zone']['name'] : ''),
            'invite_url' => NetworkLinks::invite($person['id']),
        ];
    }
}
