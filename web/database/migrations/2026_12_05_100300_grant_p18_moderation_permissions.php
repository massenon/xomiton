<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * Permissions du tableau de bord d'administration (P18-06, P18-09 ; Q97), sur une base déjà semée :
     *  - `users.ban` (bannir) et `badges.manage` (badges vérifiés) : admin et superadmin ;
     *  - `users.unban` (lever un bannissement) : superadmin seulement.
     * Idempotent. Base neuve (aucun rôle) : RolesAndPermissionsSeeder pose tout.
     */
    private const GRANTS = [
        'users.ban' => ['admin', 'superadmin'],
        'badges.manage' => ['admin', 'superadmin'],
        'users.unban' => ['superadmin'],
    ];

    public function up(): void
    {
        if (! Role::query()->where('guard_name', self::GUARD)->exists()) {
            return;
        }

        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::findOrCreate($name, self::GUARD);

            foreach (Role::query()->where('guard_name', self::GUARD)->whereIn('name', $roles)->get() as $role) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (array_keys(self::GRANTS) as $name) {
            Permission::query()->where('guard_name', self::GUARD)->where('name', $name)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
