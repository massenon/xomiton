<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Application\Cases\PeerSpace;
use App\Models\User;

/**
 * Espaces proposés à un compte (un seul compte, rôles cumulables — décisions client du 2026-10-29) : `member` (réseau
 * social, tout compte), `peer` (espace pair : compte actif détenant `peer_space.access`, RG-03) et `admin`. C'est le
 * sélecteur d'espace du mobile et les menus du web ; de l'ergonomie, jamais de la sécurité : chaque route
 * `pair/*` et `admin/*` revérifie permission, policy et scope côté serveur.
 *
 * Surface `web` : `admin` ⇔ `admin.access` (menu vers /admin, modération comprise). Surface `mobile` (API `GET me`) :
 * `admin` ⇔ `admin_mobile.access` (le groupe `admin/*` de l'API, admin et super-admin seulement).
 */
final class AccountSpaces
{
    public const MEMBER = 'member';

    public const PEER = 'peer';

    public const ADMIN = 'admin';

    public const SURFACE_WEB = 'web';

    public const SURFACE_MOBILE = 'mobile';

    private function __construct() {}

    /**
     * @return list<string>
     */
    public static function for(User $user, string $surface = self::SURFACE_MOBILE): array
    {
        $spaces = [];

        if ($user->can('social.access')) {
            $spaces[] = self::MEMBER;
        }

        if (PeerSpace::allows($user)) {
            $spaces[] = self::PEER;
        }

        if ($user->can($surface === self::SURFACE_WEB ? 'admin.access' : 'admin_mobile.access')) {
            $spaces[] = self::ADMIN;
        }

        return $spaces;
    }

    /** Espace d'arrivée après la connexion : toujours le fil pour un compte qui a l'espace membre (le menu mène aux autres). */
    public static function landing(User $user): string
    {
        return in_array(self::MEMBER, self::for($user, self::SURFACE_WEB), true) ? self::MEMBER : (self::for($user, self::SURFACE_WEB)[0] ?? self::MEMBER);
    }
}
