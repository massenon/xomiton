<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Journal;

use App\Application\Retention\PurgeAuditLogs;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\AuditLog;
use App\Support\Audit;
use App\Support\LocaleFormatter;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Journal d'audit `/admin/journal` (P13-05, prototype admin/journal.php ; écran témoin de WithDataTable, P10-02) :
 * KPI (cache 60 s, oubliés après une purge), recherche (compte, action, détail : colonnes en clair, le détail étant
 * masqué à l'écriture par Audit), filtres action et période dans l'URL (`?q=&ac=&du=&au=`, valeurs mal formées
 * ignorées), tri, pagination serveur, export CSV des filtres actifs (lien signé, journalisé ; aucune colonne sensible),
 * purge des entrées de plus de N jours (7 à 3650) **réservée au superadmin** (`audit_logs.purge`, modale rouge avec
 * saisie de « PURGER » vérifiée côté serveur, purge journalisée). Lecture : `audit_logs.view` (admin, superadmin).
 * Dates affichées et filtrées dans le fuseau d'affichage (`xomiton.display_timezone`), stockées en UTC.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    private const DATE_FORMAT = 'Y-m-d';

    /** Cache des KPI (secondes) et des actions connues du filtre. */
    public const STATS_CACHE_KEY = 'admin:journal:stats';

    public const ACTIONS_CACHE_KEY = 'admin:journal:actions';

    #[SafeUrl(as: 'ac', except: '')]
    public string $action = '';

    #[SafeUrl(as: 'du', except: '')]
    public string $from = '';

    #[SafeUrl(as: 'au', except: '')]
    public string $until = '';

    // --- Purge (superadmin) : volontairement sans type natif. Livewire ne réaffecte jamais `null` à une propriété typée
    // lors de l'hydratation : un champ « jours » vidé reviendrait à la valeur par défaut (90) à la requête suivante au
    // lieu d'être refusé par `required`. Valeur attendue : int, ou '' / null quand le champ est vidé. ---
    /** @var int|string|null */
    public $purgeDays = 90;

    public string $purgeWord = '';

    public function mount(): void
    {
        $this->authorize('viewAny', AuditLog::class);
        $this->purgeDays = self::retentionDays();
    }

    protected function sortableColumns(): array
    {
        return ['date' => 'created_at', 'compte' => 'actor', 'action' => 'action'];
    }

    protected function searchableColumns(): array
    {
        return ['actor', 'action', 'detail'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['action', 'from', 'until'];
    }

    /** Export CSV des filtres actifs (WithDataTable : lien signé, diffusion par paquets, ligne d'audit). */
    public function export(): void
    {
        $this->requestCsvExport();
    }

    protected function csvScreen(): string
    {
        return 'journal';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        $this->authorize('export', AuditLog::class);
    }

    protected function csvQuery(): Builder
    {
        return $this->filtered();
    }

    protected function csvColumns(bool $full): array
    {
        return [
            'at' => (string) __('admin.audit.columns.at'),
            'user' => (string) __('admin.audit.columns.user'),
            'action' => (string) __('admin.audit.columns.action'),
            'detail' => (string) __('admin.audit.columns.detail'),
        ];
    }

    protected function csvRow(Model $model, bool $full): array
    {
        /** @var AuditLog $model */
        return [
            'at' => $this->formatter()->pattern('yyyy-MM-dd HH:mm:ss', $model->created_at),
            'user' => $this->actorOf($model),
            'action' => $model->action,
            'detail' => $model->detail,
        ];
    }

    public function purge(PurgeAuditLogs $purge): void
    {
        $this->authorize('purge', AuditLog::class);
        $this->validate([
            'purgeDays' => ['required', 'integer', 'min:'.PurgeAuditLogs::MIN_DAYS, 'max:'.PurgeAuditLogs::MAX_DAYS],
            'purgeWord' => ['required', 'in:'.__('admin.audit.purge.word')],
        ], [
            'purgeWord.required' => (string) __('admin.audit.purge.word_mismatch'),
            'purgeWord.in' => (string) __('admin.audit.purge.word_mismatch'),
        ], [
            'purgeDays' => (string) __('admin.audit.purge.days'),
        ]);

        $days = (int) $this->purgeDays; // validé : entier entre MIN_DAYS et MAX_DAYS
        $count = $purge->run($days);
        Audit::log('audit.purged', null, trans_choice('admin.audit.purge.audit_detail', $count, ['count' => $count, 'days' => $days]));

        $this->reset('purgeWord');
        $this->closeModal('xmJournalPurge');
        Cache::forget(self::STATS_CACHE_KEY);
        Cache::forget(self::ACTIONS_CACHE_KEY);
        unset($this->stats, $this->actions);
        $this->toast(trans_choice('admin.audit.purge.done', $count, ['count' => $count]), $count > 0 ? 'success' : 'info');
    }

    /**
     * KPI : entrées, aujourd'hui (jour du Bénin), comptes distincts (cache 60 s, recalculés après une purge).
     *
     * @return array{entries: int, today: int, actors: int}
     */
    #[Computed]
    public function stats(): array
    {
        $start = Carbon::now($this->timezone())->startOfDay()->utc();

        /** @var array{entries: int, today: int, actors: int} */
        return Cache::remember(self::STATS_CACHE_KEY, 60, static fn (): array => [
            'entries' => AuditLog::query()->count(),
            'today' => AuditLog::query()->where('created_at', '>=', $start)->count(),
            'actors' => (int) AuditLog::query()->toBase()->selectRaw('COUNT(DISTINCT actor) as n')->value('n'),
        ]);
    }

    /**
     * Actions connues (filtre « Action »), triées.
     *
     * @return list<string>
     */
    #[Computed]
    public function actions(): array
    {
        /** @var list<string> */
        return Cache::remember(self::ACTIONS_CACHE_KEY, 300, static fn (): array => AuditLog::query()
            ->toBase()->distinct()->orderBy('action')->pluck('action')->map(static fn (mixed $a): string => (string) $a)->all());
    }

    public function render(): View
    {
        $rows = $this->paginateQuery($this->filtered()->with('user:id,pseudo'));
        // Variables distinctes des propriétés publiques $sort / $direction (valeurs brutes de l'URL) : tri effectif.
        [$sortKey, $sortDirection] = $this->currentSort();
        $format = $this->formatter();
        $canPurge = (bool) auth()->user()?->can('purge', AuditLog::class);

        return view('livewire.admin.journal.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $format,
            'canPurge' => $canPurge,
            'retentionDays' => self::retentionDays(),
            // Calculé pour le seul superadmin (modale de purge) : aucun COUNT de plus pour les autres comptes.
            'purgeCount' => $canPurge ? $this->purgeCount() : 0,
            'periodError' => $this->periodError(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin.audit.title'),
            'adminPage' => 'journal',
        ]);
    }

    /** Famille d'une action (badge) : préfixe du code, actions de modération regroupées. */
    public static function family(string $action): string
    {
        $prefix = strtok($action, '.') ?: $action;

        return match (true) {
            str_contains($action, 'moderation') || str_ends_with($action, 'words_flagged') => 'moderation',
            $prefix === 'auth' => 'auth',
            in_array($prefix, ['account', 'me', 'user', 'users'], true) => 'account',
            $prefix === 'alerts' => 'alerts',
            in_array($prefix, ['cases', 'case', 'case_followups', 'case_forms'], true) => 'cases',
            $prefix === 'maintenance' => 'maintenance',
            in_array($prefix, ['sync', 'device'], true) => 'sync',
            $prefix === 'export' => 'export',
            $prefix === 'audit' => 'audit',
            in_array($prefix, ['post', 'page', 'group', 'event', 'forum', 'newsletter', 'comment'], true) => 'content',
            default => 'other',
        };
    }

    /** Couleur de badge (sens, jamais décorative : rouge = alerte ou suppression). */
    public static function familyColor(string $family): string
    {
        return [
            'auth' => 'success',
            'alerts' => 'danger',
            'audit' => 'danger',
            'moderation' => 'warning',
            'maintenance' => 'warning',
            'cases' => 'violet',
            'sync' => 'info',
            'export' => 'info',
        ][$family] ?? 'neutral';
    }

    public function actorOf(AuditLog $log): string
    {
        return match ($log->actor) {
            AuditLog::ACTOR_SYSTEM => (string) __('admin.audit.system'),
            AuditLog::ACTOR_ANONYMOUS, null, '' => (string) __('admin.audit.anonymous'),
            default => (string) $log->actor,
        };
    }

    /**
     * Requête filtrée (action, période) ; recherche et tri sont ajoutés par WithDataTable.
     *
     * @return Builder<AuditLog>
     */
    private function filtered(): Builder
    {
        $query = AuditLog::query();

        if ($this->action !== '') {
            $query->ofAction($this->action);
        }

        if (($from = $this->day($this->from)) !== null) {
            $query->where('created_at', '>=', $from->copy()->startOfDay()->utc());
        }

        if (($until = $this->day($this->until)) !== null) {
            $query->where('created_at', '<=', $until->copy()->endOfDay()->utc());
        }

        return $query;
    }

    private function purgeCount(): int
    {
        $value = filter_var($this->purgeDays, FILTER_VALIDATE_INT);

        if ($value === false) {
            return 0;
        }

        $days = max(PurgeAuditLogs::MIN_DAYS, min(PurgeAuditLogs::MAX_DAYS, $value));

        return AuditLog::query()->olderThan($days)->count();
    }

    /** Rétention automatique (`audit_log_days`) ; réglage hors bornes : valeur de la configuration, sans erreur 500. */
    private static function retentionDays(): int
    {
        $fallback = max(PurgeAuditLogs::MIN_DAYS, min(PurgeAuditLogs::MAX_DAYS, (int) config('xomiton.retention.audit_log_days', 90)));

        try {
            return app(PurgeAuditLogs::class)->resolveDays();
        } catch (InvalidArgumentException) {
            return $fallback;
        }
    }

    private function periodError(): ?string
    {
        $from = $this->day($this->from);
        $until = $this->day($this->until);
        $invalid = ($this->from !== '' && $from === null) || ($this->until !== '' && $until === null) || ($from && $until && $from->gt($until));

        return $invalid ? (string) __('admin.filters.invalid_period') : null;
    }

    private function day(string $value): ?Carbon
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat(self::DATE_FORMAT, $value, $this->timezone());
        } catch (InvalidFormatException) {
            return null;
        }

        return $date instanceof Carbon && $date->format(self::DATE_FORMAT) === $value ? $date : null;
    }

    private function timezone(): string
    {
        return (string) config('xomiton.display_timezone', 'UTC');
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, $this->timezone());
    }
}
