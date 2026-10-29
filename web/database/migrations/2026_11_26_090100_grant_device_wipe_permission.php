<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    private const NAME = 'device.wipe';

    /**
     * Permission « effacer à distance un appareil » (P15-11) : sur une base déjà semée, elle est donnée aux rôles admin
     * et superadmin sans attendre un rejeu du seeder. Base neuve (aucun rôle) : le seeder pose tout.
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
        Permission::query()->where('guard_name', self::GUARD)->where('name', self::NAME)->first()?->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
