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
     * P20 (PRD conversation-en-bd, P0-9) : `conversations_bd.manage` — onglet « Conversations en BD » de `/admin/ccc`
     * (publier, dépublier, ordonner, textes et positions des bulles, bibliothèque de scènes) : admin et superadmin, sur une
     * base déjà semée. Idempotent. Base neuve (aucun rôle) : RolesAndPermissionsSeeder pose tout.
     */
    private const GRANTS = [
        'conversations_bd.manage' => ['admin', 'superadmin'],
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
