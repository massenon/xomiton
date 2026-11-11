<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Dashboard;

use App\Application\Admin\Dashboard\DashboardStats;
use App\Application\Alerts\AcknowledgeAlert;
use App\Application\Platform\MaintenanceStatus;
use App\Enums\AlertType;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\MailOutbox;
use App\Models\User;
use App\Policies\Scopes\AlertScope;
use App\Support\EscalationPolicyFactory;
use App\Support\LocaleFormatter;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Tableau de bord `/admin` (P10-04, dashboard-admin.md, prototype admin/index.php) : bandeau « Alertes SOS actives »
 * en tête avec « Accuser réception » (AcknowledgeAlert, P5-02 ; son à l'arrivée d'un nouveau SOS, activable, fixe en
 * mouvement réduit), 7 KPI (taux de synchro défini par la ligne P10-04), cinq graphiques Chart.js chargés à la demande
 * avec tableau de données accessible, **cellules de 1 à 4 masquées** (SmallCellMask), dernières alertes avec « Prendre
 * en charge », SOS actifs sur une carte schématique (Q15 b : aucune requête vers un fournisseur de tuiles, donc aucune
 * position de SOS qui sorte de la plateforme), à position grossie, sans lien Maps ni coordonnées exactes), raccourcis et
 * état de la plateforme.
 *
 * Chaque bloc est conditionné par la permission de son écran d'origine (le modérateur n'en voit presque aucun) :
 * alertes `alerts.view` (périmètre AlertScope), dossiers `cases.view_all`, comptes `users.view`, synchro `sync.manage`,
 * état de la plateforme `settings.view`. Actualisation toutes les 30 s (le 10 s est réservé à l'écran des alertes, D7).
 */
final class Index extends Component
{
    public const POLL_SECONDS = 30;

    /**
     * Identifiants des SOS actifs déjà annoncés (sonnerie seulement pour un SOS nouveau).
     *
     * @var list<int>
     */
    #[Locked]
    public array $knownSos = [];

    public function mount(DashboardStats $stats): void
    {
        $this->knownSos = $this->canAlerts() ? $this->unacknowledgedSosIds($stats) : [];
    }

    /** Sondage : prévient le navigateur (son, annonce) quand un SOS apparaît depuis le dernier passage. */
    public function refresh(DashboardStats $stats): void
    {
        if (! $this->canAlerts()) {
            return;
        }

        $ids = $this->unacknowledgedSosIds($stats);
        $new = array_values(array_diff($ids, $this->knownSos));

        if ($new !== []) {
            $this->dispatch('xm-sos-new', count: count($new));
        }

        $this->knownSos = $ids;
    }

    /** « Accuser réception » / « Prendre en charge » : accusé de réception d'une alerte visible (arrête l'escalade RG-05). */
    public function acknowledge(int $id): void
    {
        $user = $this->user();
        $alert = $this->canAlerts() ? AlertScope::routedTo($user)->whereKey($id)->first() : null;

        if ($alert === null) {
            $this->dispatch('toast', type: 'error', message: (string) __('admin.alerts.errors.not_found'));

            return;
        }

        try {
            $result = app(AcknowledgeAlert::class)->byUser($alert, $user);
        } catch (AuthorizationException) {
            $this->dispatch('toast', type: 'error', message: (string) __('admin.toasts.forbidden'));

            return;
        }

        if ($result->closed) {
            $this->dispatch('toast', type: 'error', message: (string) __('admin.alerts.errors.invalid_status'));
        } elseif ($result->alreadyAcknowledged) {
            $name = $result->alert->acknowledgedBy?->pseudo ?? (string) __('admin.alerts.detail.someone');
            $this->dispatch('toast', type: 'info', message: (string) __('admin.alerts.errors.already_taken', ['name' => $name]));
        } else {
            $this->dispatch('toast', type: 'success', message: (string) __('admin.toasts.acknowledged'));
        }
    }

    public function render(DashboardStats $stats): View
    {
        $user = $this->user();
        $format = LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
        $canAlerts = $this->canAlerts();
        $canCases = $user->can('cases.view_all');
        $scope = $canAlerts ? AlertScope::routedTo($user) : null;

        return view('livewire.admin.dashboard.index', [
            'format' => $format,
            'canAlerts' => $canAlerts,
            'canCases' => $canCases,
            'alertCounters' => $scope === null ? null : $stats->alertCounters($scope),
            'activeSos' => $scope === null ? collect() : $stats->activeSos($scope),
            'latestAlerts' => $scope === null ? collect() : $stats->latestAlerts($scope),
            'cases' => $canCases ? $stats->caseCounts() : null,
            'charts' => $canCases ? $stats->charts() : [],
            'userCounts' => $user->can('users.view') ? $stats->userCounts() : null,
            'syncRate' => $user->can('sync.manage') ? $stats->syncRate() : null,
            'canSync' => $user->can('sync.manage'),
            'platform' => $user->can('settings.view') ? $this->platformStatus() : null,
            'shortcuts' => $this->shortcuts($user),
            'escalationMinutes' => EscalationPolicyFactory::fromConfig()->delaysFor(AlertType::Sos)->escalateAfterMinutes,
            'pollSeconds' => self::POLL_SECONDS,
        ])->layout('components.layouts.admin', [
            'title' => __('admin.dashboard.title'),
            'adminPage' => 'dashboard',
        ]);
    }

    /** Pas de la grille de la carte schématique, en % du cadre (environ 18 km en longitude, 35 km en latitude). */
    public const PIN_STEP = 5;

    /**
     * Position **grossie** d'un SOS sur la carte schématique du Bénin (cadre de admin/index.php : longitude 0,7–3,9 ;
     * latitude 6,1–12,5), arrondie à la case de la grille (PIN_STEP) : la position exacte n'est lue que dans le détail de
     * l'alerte, qui journalise l'ouverture (`alerts.viewed`, Q89), et ne transite jamais dans les réponses du sondage.
     *
     * @return array{left: float, top: float}|null
     */
    public static function pin(Alert $alert): ?array
    {
        $coordinates = $alert->coordinates();

        if ($coordinates === null) {
            return null;
        }

        $snap = static fn (float $percent): float => (float) max(self::PIN_STEP, min(100 - self::PIN_STEP, round($percent / self::PIN_STEP) * self::PIN_STEP));

        return [
            'left' => $snap(($coordinates['lng'] - 0.7) / 3.2 * 100),
            'top' => $snap((12.5 - $coordinates['lat']) / 6.4 * 100),
        ];
    }

    private function canAlerts(): bool
    {
        // `alerts.view` seul : /admin/alertes est sous `can:alerts.view` (Q89 point 6), un compte `alerts.view_routed` seul
        // n'a aucun écran où ouvrir ces alertes (espace pair) et ne verrait ici que des liens en 403.
        return (bool) $this->user()->can('alerts.view');
    }

    /**
     * @return list<int>
     */
    private function unacknowledgedSosIds(DashboardStats $stats): array
    {
        /** @var Builder<Alert> $scope */
        $scope = AlertScope::routedTo($this->user());

        return $stats->activeSos($scope)
            ->filter(static fn (Alert $alert): bool => ! $alert->isAcknowledged())
            ->map(static fn (Alert $alert): int => (int) $alert->getKey())
            ->values()
            ->all();
    }

    /**
     * État de la plateforme (réglages, maintenance, comptes en attente, e-mails en attente).
     *
     * @return array{version: string, last_update: string|null, registrations: bool, maintenance: bool, offline_sync: bool, pending_accounts: int, queue_pending: int}
     */
    private function platformStatus(): array
    {
        try {
            $maintenance = MaintenanceStatus::active();
        } catch (Throwable) {
            $maintenance = false;
        }

        $updated = Settings::string('last_update');

        return [
            'version' => Settings::string('platform_version', (string) config('xomiton.version', '1.0')),
            'last_update' => $updated === '' ? null : $updated,
            'registrations' => Settings::bool('registrations_open', true),
            'maintenance' => $maintenance,
            'offline_sync' => Settings::bool('offline_sync', true),
            'pending_accounts' => User::query()->where('status', UserStatus::Pending)->count(),
            'queue_pending' => MailOutbox::query()->pending()->count(),
        ];
    }

    /**
     * Raccourcis du prototype, limités aux écrans que la personne peut ouvrir.
     *
     * @return list<array{href: string, icon: string, label: string}>
     */
    private function shortcuts(User $user): array
    {
        $entries = [
            ['registres', 'journal-plus', 'new_case', 'cases.view_all'],
            ['alertes', 'bell', 'alerts', 'alerts.view'],
            ['rapports', 'file-earmark-bar-graph', 'report', 'reports.generate'],
            ['suivi', 'person-heart', 'followup', 'case_followups.manage'],
            ['newsletter', 'envelope-paper', 'newsletter', 'newsletter.manage_campaigns'],
            ['annuaire', 'person-lines-fill', 'directory', 'directory.manage'],
            ['fiches', 'file-earmark-medical', 'case_form', 'case_forms.manage'],
            ['inscriptions', 'person-plus', 'registrations', 'registrations.manage'],
            ['moderation', 'shield-check', 'moderation', 'moderation.manage'],
            ['utilisateurs', 'people', 'users', 'users.view'],
        ];
        $shortcuts = [];

        foreach ($entries as [$route, $icon, $label, $permission]) {
            if ($user->can($permission) && Route::has('admin.'.$route)) {
                $shortcuts[] = ['href' => route('admin.'.$route), 'icon' => $icon, 'label' => (string) __('admin.dashboard.shortcut.'.$label)];
            }
        }

        return $shortcuts;
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
