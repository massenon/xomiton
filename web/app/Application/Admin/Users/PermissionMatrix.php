<?php

declare(strict_types=1);

namespace App\Application\Admin\Users;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Matrice des permissions affichée en lecture seule sur « Utilisateurs & rôles » (P12-01) : générée depuis spatie
 * (rôles et permissions en base), jamais éditable — aucune source ne prévoit son édition (RG-15 porte sur l'attribution
 * d'un rôle). Chaque module regroupe des permissions du catalogue ; niveau d'un rôle : tout le module (complet), une
 * partie (lecture / limité), rien (aucun accès), ou « super-admin » pour un module que seul ce rôle détient. Un module
 * absent de la liste fait échouer le test de couverture (tests/Feature/Admin/UsersScreenTest) : le catalogue ne dérive pas.
 */
final class PermissionMatrix
{
    public const NONE = 'none';

    public const READ = 'read';

    public const FULL = 'full';

    public const SUPER = 'superadmin';

    /**
     * Module (clé de `admin.users.modules.*`) => permissions du catalogue (RolesAndPermissionsSeeder::matrix()).
     *
     * @var array<string, list<string>>
     */
    public const MODULES = [
        'social' => ['social.access', 'posts.create', 'polls.create', 'comments.create'],
        'ccc_read' => ['ccc_tools.view'],
        'peer_space' => ['peer_space.access'],
        'collect' => ['cases.create', 'cases.update_own', 'cases.view_all', 'cases.export'],
        'forms' => ['case_forms.view_own', 'case_forms.manage'],
        'followup' => ['case_followups.view_own', 'case_followups.manage'],
        'alerts' => ['alerts.view_routed', 'alerts.take', 'alerts.close', 'alerts.view', 'alerts.manage'],
        'directory' => ['directory.view', 'directory.manage'],
        'validation' => ['validation_rules.manage'],
        'indicators' => ['indicators.view_zone', 'indicators.view', 'indicators.manage', 'reports.generate'],
        'moderation' => ['moderation.manage', 'posts.moderate'],
        'content' => ['thematiques.manage', 'resources.manage', 'faqs.manage', 'laws.manage', 'ccc_tools.manage'],
        'assistant' => ['assistant_kb.manage'],
        'newsletter' => ['newsletter.manage_subscribers', 'newsletter.manage_campaigns', 'newsletter.send'],
        'gateways' => ['gateways.connect', 'gateways.publish'],
        'emails' => ['email_templates.manage', 'email_templates.delete_system'],
        'users' => ['users.view', 'users.suspend', 'users.manage', 'users.validate_peers', 'peers.assign', 'peers.invite'],
        'registrations' => ['registrations.manage'],
        'admin_roles' => ['roles.assign_admin'],
        'sync' => ['sync.view_own', 'sync.resolve_own', 'sync.manage'],
        'platform' => ['updates.view', 'updates.manage', 'platform.maintenance', 'backups.create', 'backups.download', 'backups.restore', 'backups.delete'],
        'settings' => ['settings.view', 'settings.manage', 'audit_logs.view', 'audit_logs.purge'],
        'admin_access' => ['admin.access', 'admin_mobile.access'],
    ];

    /**
     * @return array{roles: list<array{key: string, label: string, accounts: int}>, rows: list<array{key: string, levels: array<string, string>}>}
     */
    public static function build(): array
    {
        $held = [];

        foreach (AdminRoles::ALL as $role) {
            $model = Role::query()->where('name', $role)->where('guard_name', RolesAndPermissionsSeeder::GUARD)->first();
            $held[$role] = $model === null ? [] : $model->permissions()->pluck('name')->all();
        }

        $counts = self::accountsByRole();
        $roles = array_map(static fn (string $role): array => [
            'key' => $role,
            'label' => AdminRoles::label($role),
            'accounts' => $counts[$role] ?? 0,
        ], AdminRoles::ALL);

        $rows = [];

        foreach (self::MODULES as $module => $permissions) {
            $levels = [];

            foreach (AdminRoles::ALL as $role) {
                $levels[$role] = self::level($permissions, $held, $role);
            }

            $rows[] = ['key' => $module, 'levels' => $levels];
        }

        return ['roles' => $roles, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $permissions
     * @param  array<string, list<string>>  $held
     */
    private static function level(array $permissions, array $held, string $role): string
    {
        $mine = array_intersect($permissions, $held[$role] ?? []);

        if ($mine === []) {
            return self::NONE;
        }

        if (count($mine) < count($permissions)) {
            return self::READ;
        }

        // Module que ce rôle est seul à détenir (tout le catalogue du module) : « super-admin ».
        foreach (AdminRoles::ALL as $other) {
            if ($other !== $role && array_intersect($permissions, $held[$other] ?? []) !== []) {
                return self::FULL;
            }
        }

        return self::SUPER;
    }

    /**
     * Comptes (non supprimés) par rôle.
     *
     * @return array<string, int>
     */
    private static function accountsByRole(): array
    {
        $tables = (array) config('permission.table_names', []);
        $pivot = (string) ($tables['model_has_roles'] ?? 'model_has_roles');
        $roles = (string) ($tables['roles'] ?? 'roles');

        return DB::table($pivot)
            ->join($roles, $roles.'.id', '=', $pivot.'.role_id')
            ->join('users', 'users.id', '=', $pivot.'.model_id')
            ->where($pivot.'.model_type', (new User)->getMorphClass())
            ->whereNull('users.deleted_at')
            ->groupBy($roles.'.name')
            ->selectRaw($roles.'.name as name, count(*) as total')
            ->pluck('total', 'name')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }
}
