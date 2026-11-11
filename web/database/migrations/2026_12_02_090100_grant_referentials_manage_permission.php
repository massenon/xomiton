<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    private const NAME = 'referentials.manage';

    /**
     * Référentiels administrables (décision client du 11/11/2026) : sur une base déjà semée, la permission est donnée à
     * l'admin et au superadmin. Idempotent. Base neuve (aucun rôle) : le seeder pose tout.
     */
    public function up(): void
    {
        $roles = Role::query()->where('guard_name', self::GUARD)->whereIn('name', ['admin', 'superadmin'])->get();

        if ($roles->isEmpty()) {
            return;
        }

        $permission = Permission::findOrCreate(self::NAME, self::GUARD);

        foreach ($roles as $role) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $roles = Role::query()->where('guard_name', self::GUARD)->whereIn('name', ['admin', 'superadmin'])->get();

        foreach ($roles as $role) {
            $role->revokePermissionTo(self::NAME);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
