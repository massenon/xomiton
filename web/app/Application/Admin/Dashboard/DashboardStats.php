<?php

declare(strict_types=1);

namespace App\Application\Admin\Dashboard;

use App\Enums\AgeRange;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\Sex;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\CaseRecord;
use App\Models\Population;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use App\Support\LocaleFormatter;
use App\Support\SmallCellMask;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Chiffres du tableau de bord `/admin` (P10-04, dashboard-admin.md, prototype admin/index.php) : compteurs d'alertes,
 * dossiers par statut, comptes, taux de synchro et les cinq graphiques. Lecture seule ; l'écran décide, par permission,
 * ce qu'il appelle. **Cellules de 1 à 4 masquées** (SmallCellMask) dans les KPI de dossiers et dans chaque graphique
 * (valeur `null` : le graphique ne trace rien, le tableau de données écrit « données masquées ») avec suppression
 * complémentaire (SmallCellMask::maskGroup : une cellule masquée seule se retrouverait par différence avec un total
 * connu par ailleurs, p. ex. la somme des trois KPI ou un autre graphique ; Q89). Les compteurs
 * d'alertes sont opérationnels (la garde doit voir un SOS isolé) et ne sont donc pas masqués. Aucune colonne chiffrée
 * n'est lue. Les graphiques sont mis en cache 60 s (graphiques mensuels : pas de besoin de temps réel).
 */
final class DashboardStats
{
    public const CHARTS_CACHE_KEY = 'admin:dashboard:charts';

    public const CHARTS_TTL = 60;

    /** Mois affichés par le graphique « cas par mois ». */
    public const MONTHS = 12;

    /**
     * Alertes nouvelles et SOS actifs (ouverts, non annulés), sur les alertes que la personne peut voir.
     *
     * @param  Builder<Alert>  $scope  AlertScope::routedTo()
     * @return array{new: int, sos_active: int}
     */
    public function alertCounters(Builder $scope): array
    {
        return [
            'new' => (clone $scope)->where('status', AlertStatus::New)->count(),
            'sos_active' => $this->activeSosQuery($scope)->count(),
        ];
    }

    /**
     * SOS actifs, les plus récents d'abord (bandeau, carte et liste du tableau de bord).
     *
     * @param  Builder<Alert>  $scope
     * @return Collection<int, Alert>
     */
    public function activeSos(Builder $scope, int $limit = 20): Collection
    {
        return $this->activeSosQuery($scope)->with(['zone', 'commune'])->latestFirst()->limit($limit)->get();
    }

    /**
     * Dernières alertes reçues, tous types (liste « Dernières alertes »).
     *
     * @param  Builder<Alert>  $scope
     * @return Collection<int, Alert>
     */
    public function latestAlerts(Builder $scope, int $limit = 8): Collection
    {
        return (clone $scope)->with(['zone', 'commune', 'routedContact'])->latestFirst()->limit($limit)->get();
    }

    /**
     * Dossiers ouverts, en cours et clos, effectifs de 1 à 4 masqués (`null`) avec suppression complémentaire : la
     * somme des trois KPI est un total connu, une seule cellule masquée s'en déduirait.
     *
     * @return array{open: int|null, in_progress: int|null, closed: int|null}
     */
    public function caseCounts(): array
    {
        /** @var array<string, int> $rows */
        $rows = CaseRecord::query()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();

        /** @var array{open: int|null, in_progress: int|null, closed: int|null} */
        return SmallCellMask::maskGroup([
            'open' => (int) ($rows[CaseStatus::Open->value] ?? 0),
            'in_progress' => (int) ($rows[CaseStatus::InProgress->value] ?? 0),
            'closed' => (int) ($rows[CaseStatus::Closed->value] ?? 0),
        ]);
    }

    /**
     * Comptes actifs et comptes en attente de validation.
     *
     * @return array{users: int, pending: int}
     */
    public function userCounts(): array
    {
        /** @var array<string, int> $rows */
        $rows = User::query()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->all();

        return [
            'users' => (int) ($rows[UserStatus::Active->value] ?? 0),
            'pending' => (int) ($rows[UserStatus::Pending->value] ?? 0),
        ];
    }

    /**
     * Taux de synchro (P10-04) : appareils synchronisés depuis moins de `xomiton.sync.device_stale_hours` heures
     * (`last_sync_at`) / appareils actifs (non révoqués). Null sans appareil actif : aucun taux à afficher.
     *
     * @return array{rate: float, synced: int, active: int}|null
     */
    public function syncRate(): ?array
    {
        $active = SyncDevice::query()->active()->count();

        if ($active === 0) {
            return null;
        }

        $since = now()->subHours(max(1, (int) config('xomiton.sync.device_stale_hours', 24)));
        $synced = SyncDevice::query()->active()->where('last_sync_at', '>=', $since)->count();

        return ['rate' => round($synced / $active * 100, 1), 'synced' => $synced, 'active' => $active];
    }

    /**
     * Les cinq graphiques, effectifs masqués. Chaque entrée : clé, type Chart.js, étiquettes, séries (valeurs
     * `null` = masquées) et indicateur `masked` (au moins une cellule masquée).
     *
     * @return list<array{key: string, type: string, labels: list<string>, datasets: list<array{label: string, data: list<int|null>}>, masked: bool}>
     */
    public function charts(): array
    {
        /** @var list<array{key: string, type: string, labels: list<string>, datasets: list<array{label: string, data: list<int|null>}>, masked: bool}> */
        return Cache::remember(self::CHARTS_CACHE_KEY, self::CHARTS_TTL, fn (): array => [
            $this->perMonth(),
            $this->grouped('violence', 'doughnut', 'violence_type_id', ViolenceType::query()->pluck('label', 'id')->all()),
            $this->grouped('zone', 'bar', 'zone_id', Zone::query()->pluck('name', 'id')->all()),
            $this->grouped('population', 'bar', 'population_id', Population::query()->pluck('label', 'id')->all()),
            $this->sexByAge(),
        ]);
    }

    /** @return Builder<CaseRecord> */
    private function statisticalCases(): Builder
    {
        return CaseRecord::query()->where('status', '!=', CaseStatus::Archived->value);
    }

    /**
     * @param  Builder<Alert>  $scope
     * @return Builder<Alert>
     */
    private function activeSosQuery(Builder $scope): Builder
    {
        return (clone $scope)->sos()->open()->whereNull('cancelled_at');
    }

    /**
     * @return array{key: string, type: string, labels: list<string>, datasets: list<array{label: string, data: list<int|null>}>, masked: bool}
     */
    private function perMonth(): array
    {
        $format = LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
        $start = now()->startOfMonth()->subMonths(self::MONTHS - 1);
        $counts = [];
        $labels = [];

        for ($i = 0; $i < self::MONTHS; $i++) {
            $month = $start->copy()->addMonths($i);
            $counts[$month->format('Y-m')] = 0;
            $labels[] = $format->monthYear($month);
        }

        // Regroupement par jour puis par mois en PHP : aucune fonction de date propre à un moteur SQL (SQLite en test, MySQL en production).
        $perDay = $this->statisticalCases()->toBase()
            ->where('occurred_on', '>=', $start->toDateString())
            ->selectRaw('occurred_on, COUNT(*) as n')
            ->groupBy('occurred_on')
            ->get();

        foreach ($perDay as $row) {
            $key = substr((string) $row->occurred_on, 0, 7);

            if (isset($counts[$key])) {
                $counts[$key] += (int) $row->n;
            }
        }

        $data = array_values(SmallCellMask::maskGroup($counts));

        return $this->chart('month', 'line', $labels, [['label' => (string) __('admin.dashboard.series.cases'), 'data' => $data]]);
    }

    /**
     * @param  array<int, string>  $names
     * @return array{key: string, type: string, labels: list<string>, datasets: list<array{label: string, data: list<int|null>}>, masked: bool}
     */
    private function grouped(string $key, string $type, string $column, array $names): array
    {
        /** @var array<int|string, int> $rows */
        $rows = $this->statisticalCases()->toBase()->whereNotNull($column)
            ->selectRaw($column.', COUNT(*) as n')->groupBy($column)->orderByDesc('n')->pluck('n', $column)->all();

        $labels = [];
        $counts = [];

        foreach ($rows as $id => $count) {
            $labels[] = (string) ($names[(int) $id] ?? __('admin.dashboard.unknown'));
            $counts[] = (int) $count;
        }

        $data = array_values(SmallCellMask::maskGroup($counts));

        return $this->chart($key, $type, $labels, [['label' => (string) __('admin.dashboard.series.cases'), 'data' => $data]]);
    }

    /**
     * @return array{key: string, type: string, labels: list<string>, datasets: list<array{label: string, data: list<int|null>}>, masked: bool}
     */
    private function sexByAge(): array
    {
        /** @var array<string, array<string, int>> $grid */
        $grid = [];

        $rows = $this->statisticalCases()->toBase()->whereNotNull('sex')->whereNotNull('age_range')
            ->selectRaw('sex, age_range, COUNT(*) as n')->groupBy('sex', 'age_range')->get();

        foreach ($rows as $row) {
            $grid[(string) $row->sex][(string) $row->age_range] = (int) $row->n;
        }

        $labels = array_map(static fn (AgeRange $age): string => $age->label(), AgeRange::cases());

        // Toutes les cellules du graphique forment un seul groupe : c'est l'ensemble qui se somme.
        $cells = [];

        foreach (Sex::cases() as $sex) {
            foreach (AgeRange::cases() as $age) {
                $cells[$sex->value.'|'.$age->value] = $grid[$sex->value][$age->value] ?? 0;
            }
        }

        $cells = SmallCellMask::maskGroup($cells);
        $datasets = [];

        foreach (Sex::cases() as $sex) {
            $datasets[] = [
                'label' => $sex->label(),
                'data' => array_map(static fn (AgeRange $age): ?int => $cells[$sex->value.'|'.$age->value], AgeRange::cases()),
            ];
        }

        return $this->chart('sex_age', 'bar', $labels, $datasets);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<array{label: string, data: list<int|null>}>  $datasets
     * @return array{key: string, type: string, labels: list<string>, datasets: list<array{label: string, data: list<int|null>}>, masked: bool}
     */
    private function chart(string $key, string $type, array $labels, array $datasets): array
    {
        $masked = false;

        foreach ($datasets as $dataset) {
            $masked = $masked || in_array(null, $dataset['data'], true);
        }

        return ['key' => $key, 'type' => $type, 'labels' => $labels, 'datasets' => $datasets, 'masked' => $masked];
    }
}
