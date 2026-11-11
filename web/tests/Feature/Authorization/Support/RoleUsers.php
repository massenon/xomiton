<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization\Support;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Comptes de test par rôle (ROLE_ET_PERMISSIONS.md §6 : un cas autorisé et un cas refusé par rôle).
 * Les rôles viennent du seeder réel : aucune permission n'est inventée dans les tests.
 */
final class RoleUsers
{
    /** @var list<string> */
    public const ROLES = RolesAndPermissionsSeeder::ROLES;

    public static function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolesAndPermissionsSeeder)->run();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function make(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->syncRoles([$role]);

        return $user->fresh() ?? $user;
    }

    /**
     * Un compte par rôle, indexé par nom de rôle.
     *
     * @return array<string, User>
     */
    public static function all(): array
    {
        $users = [];

        foreach (self::ROLES as $role) {
            $users[$role] = self::make($role);
        }

        return $users;
    }

    /**
     * Vérifie une capacité pour chaque rôle : autorisée pour `$allowed`, refusée pour tous les autres
     * (ROLE_ET_PERMISSIONS.md §6). `$subject` est un modèle, une classe, ou une fabrique recevant le compte testé.
     *
     * @param  array<string, User>  $users
     * @param  list<string>  $allowed
     */
    public static function assertAbility(array $users, string $ability, mixed $subject, array $allowed): void
    {
        foreach ($users as $role => $user) {
            $target = is_callable($subject) ? $subject($user) : $subject;
            $arguments = is_array($target) ? $target : [$target];
            $expected = in_array($role, $allowed, true);

            test()->assertSame(
                $expected,
                $user->can($ability, $arguments),
                sprintf('%s : « %s » devrait être %s pour le rôle %s.', is_object($arguments[0] ?? null) ? $arguments[0]::class : (string) ($arguments[0] ?? '-'), $ability, $expected ? 'autorisé' : 'refusé', $role),
            );
        }
    }

    /**
     * Rôles autres que ceux listés (pour construire le cas « refusé »).
     *
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function except(array $allowed): array
    {
        return array_values(array_diff(self::ROLES, $allowed));
    }
}
