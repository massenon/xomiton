<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'web';

    /** Nouvelle permission => rôles qui la reçoivent (le superadmin reçoit tout le catalogue). */
    private const GRANTS = [
        'peers.assign' => ['admin', 'superadmin'],
        'peers.invite' => ['admin', 'superadmin'],
        'admin_mobile.access' => ['admin', 'superadmin'],
        'sync.resolve_own' => ['pair', 'admin', 'superadmin'],
    ];

    /** Rôles qui cumulent le rôle « membre » (un seul compte, rôles cumulables). */
    private const MEMBER_PLUS = ['pair', 'moderateur', 'admin', 'superadmin'];

    /**
     * Un seul compte pour membre, pair et admin (décisions client du 2026-10-29) : sur une base déjà semée, les
     * permissions ajoutées au catalogue sont données aux rôles concernés sans attendre un rejeu du seeder, et chaque
     * compte pair / modération / administration reçoit aussi le rôle « membre » (réseau social conservé). Base neuve
     * (aucun rôle) : rien à faire, le seeder pose tout.
     */
    public function up(): void
    {
        $roles = Role::query()->where('guard_name', self::GUARD)->get()->keyBy('name');

        if ($roles->isEmpty()) {
            return;
        }

        foreach (self::GRANTS as $name => $holders) {
            $permission = Permission::findOrCreate($name, self::GUARD);

            foreach ($holders as $holder) {
                $roles->get($holder)?->givePermissionTo($permission);
            }
        }

        $member = $roles->get('membre');

        if ($member !== null) {
            $users = DB::table('model_has_roles')
                ->whereIn('role_id', $roles->only(self::MEMBER_PLUS)->pluck('id')->all())
                ->where('model_type', 'App\\Models\\User')
                ->distinct()
                ->pluck('model_id');
            $already = DB::table('model_has_roles')->where('role_id', $member->getKey())->where('model_type', 'App\\Models\\User')->pluck('model_id')->all();

            foreach ($users->diff($already) as $userId) {
                DB::table('model_has_roles')->insertOrIgnore(['role_id' => $member->getKey(), 'model_type' => 'App\\Models\\User', 'model_id' => $userId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (array_keys(self::GRANTS) as $name) {
            Permission::query()->where('guard_name', self::GUARD)->where('name', $name)->first()?->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
