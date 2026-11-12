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
     * Permissions du tableau de bord d'administration, bloc contenu et engagement (P18-10 à P18-14), sur une base déjà semée :
     *  - `forums.manage` (créer, renommer, réordonner, archiver les forums), `announcements.manage` (annonce globale),
     *    `events.manage` (file d'approbation des événements : réglage) : admin et superadmin ;
     *  - `suggestions.manage` (boîte à suggestions) : modérateur·rice, admin et superadmin.
     * Idempotent. Base neuve (aucun rôle) : RolesAndPermissionsSeeder pose tout.
     */
    private const GRANTS = [
        'forums.manage' => ['admin', 'superadmin'],
        'announcements.manage' => ['admin', 'superadmin'],
        'events.manage' => ['admin', 'superadmin'],
        'suggestions.manage' => ['moderateur', 'admin', 'superadmin'],
    ];

    public function up(): void
    {
        if (! Role::query()->where('guard_name', self::GUARD)->exists()) {
            return;
        }

        foreach (self::GRANTS as $name => $roles) {
            $permission = Permission::findOrCreate($name, self::GUARD);

            foreach (Role::query()->with('permissions')->where('guard_name', self::GUARD)->whereIn('name', $roles)->get() as $role) {
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
