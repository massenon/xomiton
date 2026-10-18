<?php

declare(strict_types=1);

namespace App\Http\Resources\Feed;

use App\Models\User;
use App\Support\MediaUrl;

/**
 * Auteur·rice d'une publication ou d'un commentaire tel·le qu'il ou elle sort de l'API du fil (RG-02) : pseudonyme,
 * avatar et rôle ; jamais le nom réel. Contenu anonyme : « Membre anonyme », avatar neutre du prototype
 * (`personnage-1.png`, inc/render.php l.13) et **aucun identifiant**, même pour l'auteur·rice (`is_mine` suffit).
 */
final class AuthorPayload
{
    public const ANONYMOUS_AVATAR = 'assets/images/xomiton/personnage-1.png';

    private function __construct() {}

    /**
     * @return array{id: int|null, pseudo: string, avatar_url: string, role: string|null, anonymous: bool}
     */
    public static function for(?User $user, bool $anonymous): array
    {
        if ($anonymous || $user === null) {
            return [
                'id' => null,
                'pseudo' => (string) __($anonymous ? 'feed.post.anonymous_author' : 'feed.post.default_author'),
                'avatar_url' => MediaUrl::resolve(self::ANONYMOUS_AVATAR, User::DEFAULT_AVATAR),
                'role' => null,
                'anonymous' => $anonymous,
            ];
        }

        return [
            'id' => (int) $user->getKey(),
            'pseudo' => (string) $user->pseudo,
            'avatar_url' => $user->avatar_url,
            'role' => $user->getRoleNames()->first(),
            'anonymous' => false,
        ];
    }
}
