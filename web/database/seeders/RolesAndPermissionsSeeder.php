<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rôles et permissions de ROLE_ET_PERMISSIONS.md (§1, §3, §4) nommées `module.action`.
 * Idempotent : rejouable à chaque déploiement ; les permissions retirées du catalogue ne sont pas supprimées
 * (on ne casse pas une policy encore en production), elles sont seulement retirées des rôles via sync.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public const GUARD = 'web';

    public const ROLES = ['membre', 'pair', 'moderateur', 'admin', 'superadmin'];

    /**
     * Permission => rôles qui la détiennent. Le superadmin reçoit tout le catalogue.
     *
     * @return array<string, list<string>>
     */
    public static function matrix(): array
    {
        $members = ['membre', 'pair', 'moderateur', 'admin'];
        $peerAndAdmin = ['pair', 'admin'];
        $moderators = ['moderateur', 'admin'];
        $admins = ['admin'];
        $superOnly = [];

        return [
            // Accès à l'administration (/admin, middleware can:admin.access)
            'admin.access' => $moderators,

            // Réseau social (fil, sondages, chat, groupes, événements, forums)
            'social.access' => $members,
            'posts.create' => $members,
            'polls.create' => $members,
            'comments.create' => $members,
            'posts.moderate' => $moderators,

            // Ressources et outils CCC
            'ccc_tools.view' => $members,
            'ccc_tools.manage' => $admins,

            // Espace pair (tâches, outils d'animation)
            'peer_space.access' => $peerAndAdmin,

            // Collecte de données, registres, fiches VBG/VDH, suivi des victimes
            'cases.create' => $peerAndAdmin,
            'cases.update_own' => $peerAndAdmin,
            'cases.view_all' => $admins,
            'cases.export' => $admins,
            'case_forms.view_own' => $peerAndAdmin,
            'case_forms.manage' => $admins,
            'case_followups.view_own' => $peerAndAdmin,
            'case_followups.manage' => $admins,

            // Alertes et signalements (le pair n'agit que sur celles routées vers lui·elle : scope Alert::routedTo)
            'alerts.view' => $admins,
            'alerts.view_routed' => $peerAndAdmin,
            'alerts.take' => $peerAndAdmin,
            'alerts.close' => $peerAndAdmin,
            'alerts.manage' => $admins,

            // Annuaire dynamique
            'directory.view' => $members,
            'directory.manage' => $admins,

            // Règles de validation
            'validation_rules.manage' => $admins,

            // Indicateurs et rapports automatisés
            'indicators.view_zone' => $peerAndAdmin,
            'indicators.view' => $admins,
            'indicators.manage' => $admins,
            'reports.generate' => $admins,

            // Modération et contenu
            'moderation.manage' => $moderators,

            // Contenu : thématiques, ressources, FAQ, lois
            'thematiques.manage' => $admins,
            'resources.manage' => $admins,
            'faqs.manage' => $admins,
            'laws.manage' => $admins,

            // Assistant (base de connaissances)
            'assistant_kb.manage' => $admins,

            // Newsletter
            'newsletter.manage_subscribers' => $admins,
            'newsletter.manage_campaigns' => $admins,
            'newsletter.send' => $admins,

            // Passerelles réseaux sociaux
            'gateways.connect' => $admins,
            'gateways.publish' => $admins,

            // Templates e-mails
            'email_templates.manage' => $admins,
            'email_templates.delete_system' => $superOnly,

            // Utilisateur·rices et inscriptions
            'users.view' => $moderators,
            'users.suspend' => $moderators,
            'users.manage' => $admins,
            'users.validate_peers' => $admins,
            'registrations.manage' => $admins,

            // Rôles d'administration
            'roles.assign_admin' => $superOnly,

            // Hors connexion et synchronisation
            'sync.view_own' => $peerAndAdmin,
            'sync.manage' => $admins,

            // Mises à jour, maintenance, sauvegardes
            'updates.view' => $admins,
            'updates.manage' => $superOnly,
            'maintenance.manage' => $superOnly,
            'backups.create' => $admins,
            'backups.download' => $admins,
            'backups.restore' => $superOnly,
            'backups.delete' => $superOnly,

            // Paramètres système, SMTP, purge du journal
            'settings.view' => $admins,
            'settings.manage' => $superOnly,

            // Journal d'audit
            'audit_logs.view' => $admins,
            'audit_logs.purge' => $superOnly,
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $matrix = self::matrix();

        foreach (array_keys($matrix) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $roles = [];
        foreach (self::ROLES as $name) {
            $roles[$name] = Role::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $byRole = array_fill_keys(self::ROLES, []);
        foreach ($matrix as $permission => $holders) {
            foreach ($holders as $role) {
                $byRole[$role][] = $permission;
            }
        }
        // Le superadmin détient l'intégralité du catalogue (ROLE_ET_PERMISSIONS.md §3).
        $byRole['superadmin'] = array_keys($matrix);

        foreach ($byRole as $role => $permissions) {
            $roles[$role]->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
