<?php

declare(strict_types=1);

namespace App\Application\Admin;

use App\Enums\AlertStatus;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * Menu de l'administration (P10-01, décision Q14 a : 8 groupes de `design-system/xomiton/pages/dashboard-admin.md`,
 * correspondance des 24 écrans + mode maintenance proposée ici, à valider par le client). Chaque entrée déclare la
 * permission qui la rend visible (`can`, ROLE_ET_PERMISSIONS.md §3) : la vue du modérateur (P12-09) ne garde que
 * Pilotage, Réseau social (réseau, modération) et Utilisateurs ; un groupe sans entrée visible disparaît. Pilotage
 * (tableau de bord, recherche globale) n'exige que `admin.access` : sa présence pour le modérateur reste **à valider**
 * (P12-09 ne cite que Modération, Réseau social et Utilisateurs, plan §7 Q87) ; la recherche globale (P10-08) filtre
 * chaque groupe de résultats par la permission de l'écran correspondant. Les pastilles (alertes nouvelles, comptes en
 * attente) sont mises en cache 30 s et oubliées à chaque changement (watchCounters).
 * L'accès réel reste décidé par les routes et les policies : masquer une entrée ne protège rien.
 */
final class AdminNavigation
{
    /** Durée du cache des compteurs (secondes). */
    public const COUNTERS_TTL = 30;

    public const COUNTERS_CACHE_KEY = 'admin-nav:counters';

    /**
     * Groupe => entrées [clé de route `admin.<clé>`, icône Bootstrap Icons, clé de libellé `menus.admin.*`,
     * permission (null : `admin.access` suffit), compteur facultatif].
     *
     * @var array<string, list<array{0: string, 1: string, 2: string, 3: string|null, 4?: string}>>
     */
    public const GROUPS = [
        'steering' => [
            ['dashboard', 'speedometer2', 'dashboard', null],
            ['recherche', 'search', 'global_search', null],
        ],
        'cases' => [
            ['alertes', 'bell', 'alerts', 'alerts.view', 'alerts_new'],
            ['registres', 'journal-bookmark', 'registers', 'cases.view_all'],
            ['fiches', 'file-earmark-medical', 'forms', 'case_forms.manage'],
            ['suivi', 'person-heart', 'followup', 'case_followups.manage'],
        ],
        'directory' => [
            ['annuaire', 'person-lines-fill', 'directory', 'directory.manage'],
        ],
        'content' => [
            ['contenu', 'journal-text', 'content', 'thematiques.manage'],
            ['ccc', 'collection-play', 'ccc', 'ccc_tools.manage'],
            ['newsletter', 'envelope-paper', 'newsletter', 'newsletter.manage_campaigns'],
            ['emails', 'envelope-at', 'emails', 'email_templates.manage'],
            ['assistant', 'robot', 'assistant', 'assistant_kb.manage'],
        ],
        'data' => [
            ['validation', 'patch-check', 'validation', 'validation_rules.manage'],
            ['synchronisation', 'cloud-arrow-up', 'sync', 'sync.manage'],
        ],
        'reports' => [
            ['indicateurs', 'bar-chart-line', 'indicators', 'indicators.view'],
            ['rapports', 'file-earmark-bar-graph', 'reports', 'reports.generate'],
        ],
        'social' => [
            ['reseau', 'diagram-3', 'network', 'posts.moderate'],
            ['moderation', 'shield-check', 'moderation', 'moderation.manage'],
            ['passerelles', 'share', 'gateways', 'gateways.connect'],
        ],
        'system' => [
            ['utilisateurs', 'people', 'users', 'users.view', 'users_pending'],
            ['inscriptions', 'person-plus', 'registrations', 'registrations.manage'],
            ['mises-a-jour', 'arrow-repeat', 'updates', 'updates.view'],
            ['sauvegardes', 'database-down', 'backups', 'backups.create'],
            ['maintenance', 'cone-striped', 'maintenance', 'platform.maintenance'],
            ['parametres', 'gear', 'settings', 'settings.view'],
            ['journal', 'clock-history', 'audit', 'audit_logs.view'],
        ],
    ];

    /** Couleur de la pastille de chaque compteur (le rouge réservé à l'urgence). */
    private const COUNTER_COLORS = ['alerts_new' => 'danger', 'users_pending' => 'primary'];

    /**
     * Groupes visibles pour le compte, entrée courante marquée (`aria-current`).
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, href: string, icon: string, label: string, current: bool, badge: string|null, badgeColor: string, badgeLabel: string|null}>}>
     */
    public function groups(?User $user, string $current = '', bool $maintenanceActive = false): array
    {
        $current = $current !== '' ? $current : self::currentKey();
        $counters = null;
        $groups = [];

        foreach (self::GROUPS as $group => $entries) {
            $items = [];

            foreach ($entries as $entry) {
                [$key, $icon, $label, $permission] = $entry;
                $counter = $entry[4] ?? null;

                if (! Route::has('admin.'.$key) || ($permission !== null && ! $user?->can($permission))) {
                    continue;
                }

                $badge = null;
                $badgeColor = 'primary';
                $badgeLabel = null;

                if ($key === 'maintenance' && $maintenanceActive) {
                    $badge = (string) __('menus.admin.maintenance_on');
                    $badgeColor = 'danger';
                } elseif ($counter !== null) {
                    $counters ??= self::counters();
                    $value = $counters[$counter] ?? 0;

                    if ($value > 0) {
                        $badge = (string) $value;
                        $badgeColor = self::COUNTER_COLORS[$counter] ?? 'primary';
                        $badgeLabel = trans_choice('admin.nav.counters.'.$counter, $value, ['count' => $value]);
                    }
                }

                $items[] = [
                    'key' => $key,
                    'href' => route('admin.'.$key),
                    'icon' => $icon,
                    'label' => (string) __('menus.admin.'.$label),
                    'current' => $current === $key,
                    'badge' => $badge,
                    'badgeColor' => $badgeColor,
                    'badgeLabel' => $badgeLabel,
                ];
            }

            if ($items !== []) {
                $groups[] = ['key' => $group, 'label' => (string) __('menus.admin.groups.'.$group), 'items' => $items];
            }
        }

        return $groups;
    }

    /**
     * Pastilles du menu (alertes nouvelles, comptes en attente), partagées par toutes les personnes de l'équipe.
     *
     * @return array{alerts_new: int, users_pending: int}
     */
    public static function counters(): array
    {
        try {
            /** @var array{alerts_new: int, users_pending: int} */
            return Cache::remember(self::COUNTERS_CACHE_KEY, self::COUNTERS_TTL, static fn (): array => [
                'alerts_new' => Alert::query()->where('status', AlertStatus::New)->count(),
                'users_pending' => User::query()->where('status', UserStatus::Pending)->count(),
            ]);
        } catch (Throwable) {
            return ['alerts_new' => 0, 'users_pending' => 0];
        }
    }

    public static function forgetCounters(): void
    {
        Cache::forget(self::COUNTERS_CACHE_KEY);
    }

    /**
     * Pastilles à jour sans attendre les 30 s du cache : toute alerte créée, supprimée ou changeant de statut (SOS,
     * signalement, synchro, prise en charge…) et tout compte créé, supprimé ou changeant de statut (validation,
     * suspension) oublient les compteurs. Événements de modèle plutôt que de domaine : aucun chemin d'écriture n'y
     * échappe (AppServiceProvider::boot).
     */
    public static function watchCounters(): void
    {
        foreach ([Alert::class, User::class] as $model) {
            $model::saved(static function (Alert|User $record): void {
                if ($record->wasRecentlyCreated || $record->wasChanged('status')) {
                    self::forgetCounters();
                }
            });
            $model::deleted(static fn (): bool => Cache::forget(self::COUNTERS_CACHE_KEY));
        }
    }

    /** Clé du menu déduite de la route courante (`admin.journal` → `journal`, `admin.maintenance.preview` → `maintenance`). */
    public static function currentKey(): string
    {
        $name = (string) Route::currentRouteName();

        return str_starts_with($name, 'admin.') ? Str::before(Str::after($name, 'admin.'), '.') : '';
    }
}
