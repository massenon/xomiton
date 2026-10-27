<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    private const OLD = 'maintenance.manage';

    private const NEW = 'platform.maintenance';

    /**
     * Mode maintenance (P13-06, Q85) : `maintenance.manage` (superadmin seul) devient `platform.maintenance`, détenue
     * par admin et superadmin — les deux rôles qui gardent l'accès pendant la maintenance (RG-14). Sur une base déjà
     * semée, sans cette migration l'admin recevrait 403 sur /admin/maintenance tant que RolesAndPermissionsSeeder n'est
     * pas rejoué, et la ligne `maintenance.manage` resterait orpheline. Base neuve (aucun rôle) : rien à faire, le
     * seeder pose directement la nouvelle permission.
     */
    public function up(): void
    {
        $roles = Role::query()->with('permissions')->where('guard_name', self::GUARD)->whereIn('name', ['admin', 'superadmin'])->get();
        $old = Permission::query()->where('guard_name', self::GUARD)->where('name', self::OLD)->first();

        if ($roles->isEmpty() && $old === null) {
            return;
        }

        $new = Permission::findOrCreate(self::NEW, self::GUARD);

        foreach ($roles as $role) {
            $role->givePermissionTo($new);
        }

        $old?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $new = Permission::query()->where('guard_name', self::GUARD)->where('name', self::NEW)->first();

        if ($new === null) {
            return;
        }

        $old = Permission::findOrCreate(self::OLD, self::GUARD);
        Role::query()->where('guard_name', self::GUARD)->where('name', 'superadmin')->first()?->givePermissionTo($old);
        $new->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
