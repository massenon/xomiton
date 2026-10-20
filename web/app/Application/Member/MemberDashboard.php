<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Application\Content\Queries\ListThematiques;
use App\Application\Content\Queries\UpcomingEvents;
use App\Application\Member\Results\Dashboard;
use App\Enums\CaseStatus;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\User;

/**
 * Tableau de bord de l'application mobile (P6-08, `GET /api/v1/dashboard`, simulateur `dashboard` l.746-776). Lecture
 * seule (ADR-29) assemblée à partir des requêtes existantes, sans nouveau domaine :
 *
 *  - tuiles : Signaler, Urgences, Assistant, Apprendre (nombre de fiches publiées) — clés et routes de l'application,
 *    les libellés restent au client (ARB) ; la tuile SOS est la barre d'onglets, pas une tuile ;
 *  - compteurs : notifications non lues et conversations non lues, ceux de l'en-tête de l'espace membre (MemberShell,
 *    NotificationInbox P6-07) ;
 *  - complétion du profil progressif (`profiles.completion`) ;
 *  - « À la une » : fiches publiées les plus récentes (ListThematiques, ordre de la liste publique) ;
 *  - prochain événement : UpcomingEvents (événements publiés, sans groupe ou d'un groupe public, jamais le lieu) ;
 *  - pair éducateur·rice (`peer_space.access`, compte validé : RG-03 ferme la collecte d'un compte en attente) : 4 KPI de
 *    l'espace pair (prototype espace-pair.php l.13-18 : collectés, ce mois-ci, en cours, clos — **ses** dossiers
 *    seulement, archivés exclus, jamais ceux des autres même sans dossier) et alertes routées vers ses fiches d'annuaire
 *    (`directory_contacts.user_id`, branche `alerts.view_routed` d'AlertScope : jamais toutes les alertes, même pour un
 *    compte `alerts.view`), encore ouvertes, les plus récentes d'abord ; sans `alerts.view_routed`, aucune alerte.
 */
final class MemberDashboard
{
    /** Tuiles communes à tous les rôles : clé => route de l'application mobile (simulateur). */
    public const TILES = [
        'report' => '/sighub',
        'emergency' => '/urgences',
        'assistant' => '/assistant',
        'learn' => '/thematiques',
    ];

    public function __construct(
        private readonly MemberShell $shell,
        private readonly ListThematiques $thematiques,
        private readonly UpcomingEvents $events,
    ) {}

    public function __invoke(User $user): Dashboard
    {
        $user->loadMissing(['profile', 'settings']);
        $featured = ($this->thematiques)(null, '', max(0, (int) config('xomiton.api.dashboard_featured', 5)));

        $tiles = [];
        foreach (self::TILES as $key => $route) {
            $tiles[] = ['key' => $key, 'route' => $route, 'count' => $key === 'learn' ? $featured->totalAll : null];
        }

        return new Dashboard(
            $user,
            $tiles,
            $this->shell->counters($user),
            (int) ($user->profile?->completion ?? 0),
            $featured,
            ($this->events)(1)->first(),
            $this->hasPeerSpace($user) ? $this->peer($user) : null,
        );
    }

    public function hasPeerSpace(User $user): bool
    {
        return $user->status === UserStatus::Active && $user->can('peer_space.access');
    }

    /**
     * Section « pair » (tableau de bord de l'application et écran `/espace-pair`, P9-03) ; à n'appeler que si
     * hasPeerSpace() est vrai.
     *
     * @return array{kpi: array{collected: int, this_month: int, in_progress: int, closed: int}, routed_alerts: array{open: int, new: int, items: \Illuminate\Support\Collection<int, Alert>}, tasks: list<array{key: string, count: int}>}
     */
    public function peer(User $user): array
    {
        $kpi = $this->kpi($user);
        $alerts = Alert::query()
            ->whereIn('routed_to', DirectoryContact::query()->select('id')->where('user_id', $user->getKey()))
            ->open();
        $open = $user->can('alerts.view_routed') ? (clone $alerts)->count() : 0;
        $new = $open === 0 ? 0 : (clone $alerts)->unacknowledged()->count();
        $items = $open === 0 ? collect() : $alerts
            ->with(['zone:id,name', 'commune:id,name'])
            ->latestFirst()
            ->limit(max(0, (int) config('xomiton.api.dashboard_routed_alerts', 5)))
            ->get(['id', 'ulid', 'type', 'status', 'priority', 'zone_id', 'commune_id', 'acknowledged_at', 'cancelled_at', 'created_at']);

        $tasks = array_values(array_filter([
            ['key' => 'alerts_to_take', 'count' => $new],
            ['key' => 'cases_to_contact', 'count' => $kpi['open']],
        ], static fn (array $task): bool => $task['count'] > 0));
        unset($kpi['open']);

        return [
            'kpi' => $kpi,
            'routed_alerts' => ['open' => $open, 'new' => $new, 'items' => $items],
            'tasks' => $tasks,
        ];
    }

    /**
     * Dossiers collectés par le compte, hors archivés (un dossier archivé sort des listes et des KPI, P11-01), en une
     * requête : total, créés ce mois-ci (fuseau de l'application), en cours (ouverts ou en cours), clos ; `open` sert
     * à la tâche « dossiers ouverts à contacter » (prototype l.27-28).
     *
     * @return array{collected: int, this_month: int, in_progress: int, closed: int, open: int}
     */
    private function kpi(User $user): array
    {
        $active = array_map(static fn (CaseStatus $status): string => $status->value, CaseStatus::activeStatuses());
        $marks = implode(', ', array_fill(0, count($active), '?'));

        $row = CaseRecord::query()
            ->collectedBy($user)
            ->whereNull('archived_at')
            ->where('status', '!=', CaseStatus::Archived->value)
            ->toBase()
            ->selectRaw('count(*) as collected')
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as this_month', [now()->startOfMonth()->toDateTimeString()])
            ->selectRaw("sum(case when status in ($marks) then 1 else 0 end) as in_progress", $active)
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as closed', [CaseStatus::Closed->value])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as open_count', [CaseStatus::Open->value])
            ->first();

        return [
            'collected' => (int) ($row->collected ?? 0),
            'this_month' => (int) ($row->this_month ?? 0),
            'in_progress' => (int) ($row->in_progress ?? 0),
            'closed' => (int) ($row->closed ?? 0),
            'open' => (int) ($row->open_count ?? 0),
        ];
    }
}
