<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Followups;

use App\Application\Cases\AddFollowup;
use App\Application\Cases\CaseRules;
use App\Application\Cases\Data\FollowupChanges;
use App\Application\Cases\Data\FollowupInput;
use App\Application\Cases\FollowupBoard;
use App\Application\Cases\UpdateFollowup;
use App\Application\Cases\ValidationCatalog;
use App\Domain\Cases\CaseCode;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Cases\Exceptions\ClientUuidConflict;
use App\Domain\Cases\Exceptions\ClientUuidReused;
use App\Domain\Cases\FollowupStep;
use App\Domain\Cases\ValidationRuleKey;
use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;
use App\Policies\Scopes\CaseRecordScope;
use App\Rules\NotFutureDate;
use App\Support\Audit;
use App\Support\LikePattern;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Suivi des victimes `/admin/suivi` (P11-03, prototype admin/suivi.php) : KPI (% réalisés, en retard = prochaine
 * échéance dépassée, délai moyen entre suivis, dossiers suivis), frise d'un dossier (x-timeline), « Ajouter une étape »
 * (AddFollowup, **idempotent** par `clientUuid`), « Marquer réalisé » (UpdateFollowup), liste de toutes les étapes
 * (recherche sur le code du dossier, l'étape et l'auteur·e ; jamais le commentaire chiffré, ADR-33), encarts « en
 * retard » et « prochains suivis », export CSV scopé sans commentaire (`cases.export`, lien signé, journalisé).
 *
 * Réservé à `case_followups.manage` (administration) ; les dossiers visibles suivent CaseRecordScope, ceux archivés sont
 * hors listes et KPI. Le commentaire d'une étape ne se lit que dans la frise d'un dossier choisi, et son ouverture est
 * journalisée (`case_followups.viewed`, code seul). Aucune donnée déchiffrée n'est une propriété publique.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithDataTable;

    public const STATS_CACHE_KEY = 'admin:followups:kpis';

    /** Code du dossier dont la frise est affichée (URL `?dossier=VBG-0001`). */
    #[SafeUrl(as: 'dossier', except: '')]
    public string $dossier = '';

    #[SafeUrl(as: 'res', except: '')]
    public string $outcome = '';

    #[SafeUrl(as: 'retard', except: false)]
    public bool $late = false;

    /** Saisie du champ « Frise d’un dossier » (le code n’est retenu qu’au clic sur « Afficher »). */
    public string $caseInput = '';

    /**
     * Formulaire « Ajouter une étape de suivi ».
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /** Clé d'idempotence de la saisie en cours (ADR-32), renouvelée après chaque enregistrement. */
    public string $clientUuid = '';

    /** À chaque requête Livewire (pas seulement au montage) : une permission retirée en cours de session coupe l'écran. */
    public function boot(): void
    {
        $this->authorize('viewAny', CaseFollowup::class);
        abort_unless($this->user()->can('case_followups.manage'), 403);
    }

    public function mount(): void
    {
        $this->form = $this->blankForm();
        $this->clientUuid = (string) Str::uuid();
        $this->dossier = $this->resolvedCase($this->dossier)?->code ?? '';
        $this->caseInput = $this->dossier;
        $this->form['case_code'] = $this->dossier;

        if ($this->dossier !== '') {
            $this->auditView($this->dossier);
        }
    }

    protected function sortableColumns(): array
    {
        return ['date' => 'case_followups.date', 'prochaine' => 'case_followups.next_on', 'dossier' => 'cases.code', 'etape' => 'case_followups.step'];
    }

    protected function searchableColumns(): array
    {
        return ['cases.code', 'case_followups.step'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['outcome', 'late'];
    }

    /** Recherche, résultat et « en retard seulement » (un booléen faux n'est pas un filtre actif). */
    public function activeFilterCount(): int
    {
        return ($this->searchTerm() !== '' ? 1 : 0) + ($this->outcome !== '' ? 1 : 0) + ($this->late ? 1 : 0);
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query): Builder
    {
        self::assertClearColumns($query->getModel(), ['step'], 'recherche');
        $term = $this->searchTerm();

        if ($term === '') {
            return $query;
        }

        $pattern = LikePattern::contains($term);

        return $query->where(function (Builder $where) use ($pattern): void {
            foreach (['cases.code', 'case_followups.step', 'authors.pseudo'] as $column) {
                $where->orWhereRaw(LikePattern::clause($column), [$pattern]);
            }
        });
    }

    // --- Actions de la vue ---------------------------------------------------------------------------------------------

    /** Affiche la frise d'un dossier (« Afficher » ou lien d'une ligne) : le code est relu, la lecture journalisée. */
    public function showCase(string $code = ''): void
    {
        $code = $code !== '' ? $code : $this->caseInput;

        if (trim($code) === '') {
            $this->dossier = '';
            $this->resetErrorBag('caseInput');

            return;
        }

        $case = $this->resolvedCase($code);

        if ($case === null) {
            $this->addError('caseInput', (string) __('admin_data.followups.errors.case_unknown'));

            return;
        }

        $this->resetErrorBag('caseInput');
        $this->dossier = $case->code;
        $this->caseInput = $case->code;
        $this->form['case_code'] = $case->code;
        $this->auditView($case->code);
    }

    public function closeCase(): void
    {
        $this->dossier = '';
        $this->caseInput = '';
    }

    public function add(AddFollowup $add, CaseRules $rules): void
    {
        $user = $this->user();
        $data = [];

        foreach ($this->form as $key => $value) {
            $data[(string) $key] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }

        $case = $this->resolvedCase((string) ($data['case_code'] ?? ''));

        if ($case === null) {
            $this->addError('form.case_code', (string) __('admin_data.followups.errors.case_unknown'));

            return;
        }

        if ($this->catalogStrictCode() && ! CaseCode::isValid((string) ($data['case_code'] ?? ''))) {
            $this->addError('form.case_code', (string) __('validation.case_code'));

            return;
        }

        $data['client_uuid'] = trim($this->clientUuid) === '' ? null : $this->clientUuid;
        unset($data['case_code']);
        $validated = $this->validateForm('form', $data, $rules->forFollowupCreate(), $this->fieldNames());

        try {
            $written = $add($user, $case, FollowupInput::fromValidated($validated), $validated['client_uuid'] ?? null);
        } catch (ClientUuidConflict|ClientUuidReused) {
            $this->toast((string) __('admin_data.followups.errors.uuid'), 'error');

            return;
        } catch (CaseLocked) {
            $this->addError('form.case_code', (string) __('admin_data.followups.errors.locked'));

            return;
        }

        Cache::forget(self::STATS_CACHE_KEY);
        $this->resetPage();

        if (! $written->created) {
            $this->toast((string) __('admin_data.followups.toasts.replayed', ['code' => $case->code]), 'info');
        } else {
            $this->toast((string) __('admin_data.followups.toasts.added', ['code' => $case->code]));
        }

        $this->dossier = $case->code;
        $this->caseInput = $case->code;
        $this->form = $this->blankForm();
        $this->form['case_code'] = $case->code;
        $this->clientUuid = (string) Str::uuid();
    }

    public function markDone(int $id, UpdateFollowup $update): void
    {
        $followup = app(FollowupBoard::class)->steps()->select('case_followups.*')->whereKey($id)->first();

        if ($followup === null || CaseRecordScope::visibleTo($this->user())->whereKey($followup->case_id)->doesntExist()) {
            $this->toast((string) __('admin_data.followups.errors.not_found'), 'error');

            return;
        }

        try {
            $update($this->user(), $followup, new FollowupChanges(['outcome' => FollowupOutcome::Done]));
        } catch (CaseLocked) {
            $this->toast((string) __('admin_data.followups.errors.locked'), 'error');

            return;
        }

        Cache::forget(self::STATS_CACHE_KEY);
        $this->toast((string) __('admin_data.followups.toasts.done'));
    }

    // --- Export CSV (ADR-33 : jamais le commentaire) -----------------------------------------------------------------

    public function export(): void
    {
        $this->requestCsvExport();
    }

    protected function csvScreen(): string
    {
        return 'suivi';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        $this->authorize('export', CaseRecord::class);
        abort_unless($this->user()->can('case_followups.manage'), 403);
    }

    protected function csvQuery(): Builder
    {
        return $this->filtered()->with(['case:id,code', 'author:id,pseudo']);
    }

    protected function csvColumns(bool $full): array
    {
        return [
            'case' => (string) __('admin_data.followups.columns.case'),
            'step' => (string) __('admin_data.followups.columns.step'),
            'date' => (string) __('admin_data.followups.columns.date'),
            'by' => (string) __('admin_data.followups.columns.by'),
            'outcome' => (string) __('admin_data.followups.columns.outcome'),
            'next_on' => (string) __('admin_data.followups.columns.next'),
        ];
    }

    protected function csvRow(Model $model, bool $full): array
    {
        /** @var CaseFollowup $model */
        return [
            'case' => $model->case?->code,
            'step' => $model->step,
            'date' => $model->date?->format('Y-m-d'),
            'by' => $model->author?->pseudo,
            'outcome' => $model->outcome->label(),
            'next_on' => $model->next_on?->format('Y-m-d'),
        ];
    }

    // --- Données de la vue ---------------------------------------------------------------------------------------------

    /**
     * KPI (cache 60 s, oubliés à chaque écriture de l'écran).
     *
     * @return array{total: int, done: int, percent: int, overdue: int, upcoming: int, gap_days: int, first_days: int, cases: int}
     */
    #[Computed]
    public function stats(): array
    {
        /** @var array{total: int, done: int, percent: int, overdue: int, upcoming: int, gap_days: int, first_days: int, cases: int} */
        return Cache::remember(self::STATS_CACHE_KEY, 60, static fn (): array => app(FollowupBoard::class)->kpis());
    }

    /**
     * Frise du dossier choisi : étapes chronologiques inverses (commentaires lus ici seulement).
     *
     * @return array{case: CaseRecord, steps: \Illuminate\Support\Collection<int, CaseFollowup>}|null
     */
    #[Computed]
    public function timeline(): ?array
    {
        $case = $this->resolvedCase($this->dossier);

        if ($case === null) {
            return null;
        }

        $case->loadMissing(['commune:id,name', 'population:id,label']);

        return [
            'case' => $case,
            'steps' => $case->followups()->with('author:id,pseudo')->orderByDesc('date')->orderByDesc('id')->get(),
        ];
    }

    public function render(): View
    {
        $board = app(FollowupBoard::class);
        $rows = $this->paginateQuery($this->filtered()
            ->select(['case_followups.id', 'case_followups.case_id', 'case_followups.date', 'case_followups.step', 'case_followups.by_user_id', 'case_followups.outcome', 'case_followups.next_on'])
            ->with(['case:id,code', 'author:id,pseudo']));
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.followups.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'lateSteps' => $board->sidebar(true),
            'soonSteps' => $board->sidebar(false),
            'steps' => FollowupStep::OPTIONS,
            'outcomes' => FollowupOutcome::cases(),
            'caseCodes' => CaseRecordScope::visibleTo($this->user())->where('status', '!=', CaseStatus::Archived->value)->orderByDesc('id')->limit(200)->pluck('code'),
            'canExport' => $this->user()->can('export', CaseRecord::class),
            'today' => NotFutureDate::today()->format('Y-m-d'),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_data.followups.title'),
            'adminPage' => 'suivi',
        ]);
    }

    // --- Interne ---------------------------------------------------------------------------------------------------------

    /**
     * Étapes filtrées (dossiers visibles et non archivés ; résultat, retard) ; recherche et tri ajoutés par WithDataTable.
     *
     * @return Builder<CaseFollowup>
     */
    private function filtered(): Builder
    {
        $query = app(FollowupBoard::class)->steps()
            ->leftJoin('users as authors', 'authors.id', '=', 'case_followups.by_user_id')
            ->whereIn('cases.id', CaseRecordScope::visibleTo($this->user())->select('cases.id')->toBase())
            ->select('case_followups.*');

        if (($outcome = FollowupOutcome::tryFrom($this->outcome)) !== null) {
            $query->where('case_followups.outcome', $outcome->value);
        }

        if ($this->late) {
            $query->whereIn('case_followups.outcome', [FollowupOutcome::Planned->value, FollowupOutcome::Postponed->value])
                ->whereNotNull('case_followups.next_on')
                ->where('case_followups.next_on', '<', NotFutureDate::today()->format('Y-m-d'));
        }

        return $query;
    }

    /**
     * Dossier visible par le compte pour ce code (saisie tolérante : « vbg 42 » → VBG-0042), null sinon ou si archivé.
     */
    private function resolvedCase(string $code): ?CaseRecord
    {
        $parsed = CaseCode::tryParse($code);

        if ($parsed === null) {
            return null;
        }

        return CaseRecordScope::visibleTo($this->user())
            ->where('code', $parsed->value())
            ->where('status', '!=', CaseStatus::Archived->value)
            ->first();
    }

    private function auditView(string $code): void
    {
        Audit::log('case_followups.viewed', null, sprintf('dossier=%s', $code), $this->user());
    }

    private function catalogStrictCode(): bool
    {
        return app(ValidationCatalog::class)->isActive(ValidationRuleKey::CaseCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'case_code' => '',
            'step' => FollowupStep::OPTIONS[0],
            'date' => NotFutureDate::today()->format('Y-m-d'),
            'outcome' => FollowupOutcome::Done->value,
            'next_on' => '',
            'comment' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return (array) __('admin_data.followups.fields');
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
