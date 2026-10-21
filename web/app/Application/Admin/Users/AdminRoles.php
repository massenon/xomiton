<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Models\User;

/**
 * Les cinq rôles de ROLE_ET_PERMISSIONS.md §1, du moins au plus étendu, et ce que chaque compte d'administration peut
 * attribuer (RG-15, P12-01). Seul endroit de l'écran « Utilisateurs & rôles » qui connaît des noms de rôle : les droits
 * eux-mêmes restent décidés par les permissions (UserPolicy : `users.manage`, `roles.assign_admin`).
 */
final class AdminRoles
{
    /** @var list<string> du moins au plus étendu */
    public const ALL = ['membre', 'pair', 'moderateur', 'admin', 'superadmin'];

    /** Rôles qui ouvrent `/admin` (RG-15 : attribution réservée à `roles.assign_admin`). */
    public const ADMINISTRATION = ['moderateur', 'admin', 'superadmin'];

    public const SUPERADMIN = 'superadmin';

    private function __construct() {}

    public static function label(string $role): string
    {
        return in_array($role, self::ALL, true) ? (string) __('admin.users.roles.'.$role) : $role;
    }

    public static function isAdministration(string $role): bool
    {
        return in_array($role, self::ADMINISTRATION, true);
    }

    /** Rôle le plus étendu du compte (`membre` à défaut). */
    public static function of(User $user): string
    {
        $names = $user->getRoleNames();

        foreach (array_reverse(self::ALL) as $role) {
            if ($names->contains($role)) {
                return $role;
            }
        }

        return 'membre';
    }

    /**
     * Rôles que le compte peut attribuer : membre et pair avec `users.manage` ; modérateur·rice, administrateur·rice et
     * super-admin seulement avec `roles.assign_admin` (RG-15).
     *
     * @return list<string>
     */
    public static function assignable(User $by): array
    {
        if (! $by->can('users.manage')) {
            return [];
        }

        return $by->can('roles.assign_admin') ? self::ALL : ['membre', 'pair'];
    }
}
