<?php

declare(strict_types=1);

namespace App\Http\Resources\Forums;

use App\Http\Resources\Feed\AuthorPayload;
use App\Models\User;

/**
 * Auteur·rice d'un sujet ou d'une réponse : celui du fil (AuthorPayload, RG-02 : pseudonyme, jamais le nom réel ;
 * anonyme → « Membre anonyme », avatar neutre, **aucun identifiant ni rôle**, même pour l'auteur·rice) plus le badge du
 * prototype (forum-topic.php l.56, x_role_label : « Pair animateur·rice » pour `pair`, « Équipe Xomiton » pour `admin`
 * et `superadmin` ; `moderateur` sans badge, comme le prototype) pour un contenu signé.
 */
final class ForumAuthor
{
    private const BADGES = [
        'pair' => 'pair',
        'admin' => 'team',
        'superadmin' => 'team',
    ];

    private function __construct() {}

    /**
     * @return array{id: int|null, pseudo: string, avatar_url: string, role: string|null, anonymous: bool, badge: string|null}
     */
    public static function for(?User $user, bool $anonymous): array
    {
        $payload = AuthorPayload::for($user, $anonymous);
        $badge = $payload['role'] !== null ? (self::BADGES[$payload['role']] ?? null) : null;

        return $payload + ['badge' => $badge !== null ? (string) __('community.forums.badges.'.$badge) : null];
    }
}
