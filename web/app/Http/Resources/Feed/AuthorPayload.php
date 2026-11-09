<?php

declare(strict_types=1);

namespace App\Http\Resources\Feed;

use App\Models\User;

/**
 * Auteur·rice d'une publication ou d'un commentaire tel·le qu'il ou elle sort de l'API du fil (RG-02) : pseudonyme,
 * avatar et rôle ; jamais le nom réel. Contenu anonyme : « Membre anonyme », avatar neutre (null : initiales côté interface)
 * et **aucun identifiant**, même pour l'auteur·rice (`is_mine` suffit).
 */
final class AuthorPayload
{
    private function __construct() {}

    /**
     * @return array{id: int|null, pseudo: string, avatar_url: string|null, role: string|null, anonymous: bool}
     */
    public static function for(?User $user, bool $anonymous): array
    {
        if ($anonymous || $user === null) {
            return [
                'id' => null,
                'pseudo' => (string) __($anonymous ? 'feed.post.anonymous_author' : 'feed.post.default_author'),
                'avatar_url' => null,
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
