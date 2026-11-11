<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    private const NAME = 'moderation.filter';

    /**
     * Les modérateur·rices gardent la gestion de la liste du filtre de contenus (décision client du 2026-11-10) : sur
     * une base déjà semée, la permission est (re)donnée à la modération, à l'admin et au superadmin. Idempotent. Base
     * neuve (aucun rôle) : le seeder pose tout.
     */
    public function up(): void
    {
        $roles = Role::query()->where('guard_name', self::GUARD)->whereIn('name', ['moderateur', 'admin', 'superadmin'])->with('permissions')->get();

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
        $role = Role::query()->where('guard_name', self::GUARD)->where('name', 'moderateur')->first();

        $role?->revokePermissionTo(self::NAME);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
