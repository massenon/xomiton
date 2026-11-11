<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Registers;

use App\Application\Cases\ArchiveCase;
use App\Application\Cases\CaseQuality;
use App\Application\Cases\CaseRules;
use App\Application\Cases\CollectOptions;
use App\Application\Cases\CreateCase;
use App\Application\Cases\Data\CaseChanges;
use App\Application\Cases\Data\CaseInput;
use App\Application\Cases\RestoreCase;
use App\Application\Cases\UpdateCase;
use App\Application\Shared\Referentials;
use App\Domain\Cases\CaseReferral;
use App\Domain\Cases\Exceptions\CaseException;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Cases\Exceptions\ClientUuidConflict;
use App\Domain\Cases\Exceptions\ClientUuidReused;
use App\Domain\Cases\Exceptions\InvalidCaseTransition;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\CaseRecord;
use App\Models\User;
use App\Policies\Scopes\CaseRecordScope;
use App\Support\Audit;
use App\Support\LikePattern;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Registres numériques `/admin/registres` (P11-01, prototype admin/registres.php) : les dossiers VBG / VDH de toutes les
 * pair·es (`cases.view_all`, scope CaseRecordScope::visibleTo), jamais leurs notes dans la liste (ADR-33 : le récit est
 * chiffré, ni recherché, ni trié, ni exporté). Colonnes, recherche en clair (code, commune, département, pair), filtres
 * (nature, département, statut — « actifs » par défaut, les archivés n'apparaissant que sur demande —, violence,
 * population, période), KPI hors archivés, export CSV scopé sans notes (`cases.export`, lien signé, journalisé).
 *
 * Écritures par les cas d'usage de l'espace pair, les mêmes règles que l'API (CaseRules, catalogue activable) :
 * « Nouveau dossier » (CreateCase, code attribué par le serveur RG-06, **idempotent** par `clientUuid` ; avertissements
 * non bloquants : date ancienne, doublons suspects), « Modifier » dans la même modale (UpdateCase ; nature et code
 * immuables ; les notes ne sont jamais pré-remplies : champ vide = inchangé, case « effacer les notes »), changement de
 * statut ouvert / en cours / clos (transitions de CaseStatus), **archiver** (ArchiveCase : suppression logique, le dossier
 * sort des listes et des KPI) et **restaurer** (RestoreCase). Le détail (modale) lit les notes après autorisation et
 * journalise `cases.viewed` (code seul). Aucune donnée déchiffrée n'est une propriété publique.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithDataTable;

    public const FORM_MODAL = 'xmCaseForm';

    public const DETAIL_MODAL = 'xmCaseDetail';

    #[SafeUrl(as: 'reg', except: '')]
    public string $kind = '';

    #[SafeUrl(as: 'zone', except: '')]
    public string $zone = '';

    /** '' : dossiers non archivés ; sinon un statut de CaseStatus (dont `archive`). */
    #[SafeUrl(as: 'statut', except: '')]
    public string $status = '';

    #[SafeUrl(as: 'type', except: '')]
    public string $violence = '';

    #[SafeUrl(as: 'pop', except: '')]
    public string $population = '';

    #[SafeUrl(as: 'du', except: '')]
    public string $from = '';

    #[SafeUrl(as: 'au', except: '')]
    public string $until = '';

    /**
     * Formulaire « Nouveau dossier » / « Modifier » (colonnes de `cases` en snake_case, mêmes règles que l'API).
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /** Dossier modifié dans la modale (0 : création) ; verrouillé, jamais modifiable depuis le navigateur. */
    #[Locked]
    public int $editId = 0;

    /** Statut du dossier modifié à l'ouverture de la modale : base des transitions proposées (verrouillé). */
    #[Locked]
    public string $editFrom = '';

    /** Clé d'idempotence de la saisie en cours (ADR-32) : un renvoi de la même saisie rend le dossier d'origine. */
    public string $clientUuid = '';

    /** Modification : effacer les notes existantes (champ vide = notes inchangées). */
    public bool $clearNotes = false;

    /** Dossier ouvert dans la modale « Détail » ; verrouillé. */
    #[Locked]
    public int $detailId = 0;

    public string $detailStatus = '';

    /** À chaque requête Livewire (pas seulement au montage) : une permission retirée en cours de session coupe l'écran. */
    public function boot(): void
    {
        $this->authorize('viewAny', CaseRecord::class);
        abort_unless($this->user()->can('cases.view_all'), 403);
    }

    public function mount(): void
    {
        $this->form = $this->blankForm();
        $this->clientUuid = (string) Str::uuid();
    }

    protected function sortableColumns(): array
    {
        return ['code' => 'code', 'date' => 'occurred_on', 'statut' => 'status', 'registre' => 'kind'];
    }

    /** Colonnes en clair de `cases` ; le récit (`notes`) est chiffré et jamais cherché (ADR-33). Le reste est ajouté par applySearch(). */
    protected function searchableColumns(): array
    {
        return ['code'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['kind', 'zone', 'status', 'violence', 'population', 'from', 'until'];
    }

    // --- Recherche (code, commune, département, pair ; jamais le récit) ---------------------------------------------

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query): Builder
    {
        self::assertClearColumns($query->getModel(), $this->searchableColumns(), 'recherche');
        $term = $this->searchTerm();

        if ($term === '') {
            return $query;
        }

        $pattern = LikePattern::contains($term);

        return $query->where(function (Builder $where) use ($pattern): void {
            foreach (['cases.code', 'communes.name', 'zones.name', 'collectors.pseudo'] as $column) {
                $where->orWhereRaw(LikePattern::clause($column), [$pattern]);
            }
        });
    }

    // --- Actions de la vue ---------------------------------------------------------------------------------------------

    /** Choix d'un statut dans la rangée de pastilles (réinitialise la page et la sélection). */
    public function setStatus(string $status): void
    {
        $this->status = $status === '' || CaseStatus::tryFrom($status) !== null ? $status : '';
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', CaseRecord::class);
        $this->resetErrorBag();
        $this->editId = 0;
        $this->editFrom = '';
        $this->clearNotes = false;
        $this->form = $this->blankForm();
        $this->clientUuid = (string) Str::uuid();
        $this->openModal(self::FORM_MODAL);
    }

    public function openEdit(int $id): void
    {
        $case = $this->find($id);

        if ($case === null) {
            return;
        }

        $this->authorize('update', $case);

        if ($case->isAnonymized() || $case->status === CaseStatus::Archived) {
            $this->toast((string) __('admin_data.registers.errors.locked'), 'error');

            return;
        }

        $this->resetErrorBag();
        $this->editId = (int) $case->getKey();
        $this->editFrom = $case->status->value;
        $this->clearNotes = false;
        // Les notes ne sont jamais recopiées dans le formulaire : aucune donnée déchiffrée dans l'état de la page.
        $this->form = [
            'kind' => $case->kind->value,
            'occurred_on' => $case->occurred_on?->format('Y-m-d') ?? '',
            'zone_id' => (string) $case->zone_id,
            'commune_id' => $case->commune_id === null ? '' : (string) $case->commune_id,
            'population_id' => (string) $case->population_id,
            'sex' => $case->sex->value,
            'age_range' => $case->age_range->value,
            'violence_type_id' => (string) $case->violence_type_id,
            'referral' => $case->referral ?? '',
            'notes' => '',
            'status' => $case->status->value,
        ];
        $this->openModal(self::FORM_MODAL);
    }

    /** Changer le département vide la commune (liste en cascade, RG-08). */
    public function updatedFormZoneId(): void
    {
        $this->form['commune_id'] = '';
    }

    public function save(CreateCase $create, UpdateCase $update, CaseRules $rules): void
    {
        $user = $this->user();

        try {
            $this->editId === 0 ? $this->create($user, $create, $rules) : $this->update($user, $update, $rules);
        } catch (ClientUuidConflict|ClientUuidReused) {
            $this->toast((string) __('admin_data.registers.errors.uuid'), 'error');
        } catch (CaseLocked) {
            $this->toast((string) __('admin_data.registers.errors.locked'), 'error');
        } catch (InvalidCaseTransition $e) {
            $this->addError('form.status', (string) __('admin_data.registers.errors.transition', ['from' => $e->from->label(), 'to' => $e->to->label()]));
        } catch (CaseException $e) {
            $this->toast($e->userMessage(), 'error');
        }
    }

    /** Statut : ouvert, en cours, clos (les transitions de CaseStatus décident ; l'archivage a son propre bouton). */
    public function changeStatus(UpdateCase $update): void
    {
        $case = $this->find($this->detailId);

        if ($case === null) {
            return;
        }

        $target = CaseStatus::tryFrom($this->detailStatus);

        if ($target === null || $target === CaseStatus::Archived) {
            $this->addError('detailStatus', (string) __('admin_data.registers.errors.status'));

            return;
        }

        try {
            $update($this->user(), $case, new CaseChanges([], $target));
        } catch (InvalidCaseTransition $e) {
            $this->addError('detailStatus', (string) __('admin_data.registers.errors.transition', ['from' => $e->from->label(), 'to' => $e->to->label()]));

            return;
        } catch (CaseLocked) {
            $this->toast((string) __('admin_data.registers.errors.locked'), 'error');

            return;
        }

        $this->resetErrorBag('detailStatus');
        unset($this->detail);
        $this->toast((string) __('admin_data.registers.toasts.status', ['code' => $case->code, 'status' => $target->label()]));
    }

    public function archive(int $id, ArchiveCase $archive): void
    {
        $case = $this->find($id);

        if ($case === null) {
            return;
        }

        try {
            $archive($this->user(), $case);
        } catch (CaseLocked) {
            $this->toast((string) __('admin_data.registers.errors.locked'), 'error');

            return;
        }

        $this->closeIfOpen($case);
        $this->toast((string) __('admin_data.registers.toasts.archived', ['code' => $case->code]));
    }

    public function restore(int $id, RestoreCase $restore): void
    {
        $case = $this->find($id);

        if ($case === null) {
            return;
        }

        try {
            $restore($this->user(), $case);
        } catch (CaseLocked|InvalidCaseTransition) {
            $this->toast((string) __('admin_data.registers.errors.restore'), 'error');

            return;
        }

        $this->toast((string) __('admin_data.registers.toasts.restored', ['code' => $case->code]));
    }

    public function openDetail(int $id): void
    {
        $case = $this->find($id);

        if ($case === null) {
            return;
        }

        $this->authorize('view', $case);
        $this->detailId = (int) $case->getKey();
        $this->detailStatus = $case->status->value;
        $this->resetErrorBag('detailStatus');
        unset($this->detail);
        // Lecture des notes : tracée (code seul, jamais le contenu), comme le détail d'une alerte.
        Audit::log('cases.viewed', $case, sprintf('code=%s', $case->code), $this->user());
        $this->openModal(self::DETAIL_MODAL);
    }

    /** Fermeture de la modale « Détail » : plus aucune donnée déchiffrée n'est conservée par la page. */
    public function closeDetail(): void
    {
        $this->detailId = 0;
        $this->detailStatus = '';
        unset($this->detail);
    }

    // --- Export CSV (ADR-33 : jamais les notes) --------------------------------------------------------------------

    public function export(): void
    {
        $this->requestCsvExport();
    }

    protected function csvScreen(): string
    {
        return 'registres';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        $this->authorize('export', CaseRecord::class);
    }

    protected function csvQuery(): Builder
    {
        return $this->filtered(auth()->user() instanceof User ? auth()->user() : $this->user())
            ->with(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label', 'collector:id,pseudo']);
    }

    protected function csvColumns(bool $full): array
    {
        return [
            'code' => (string) __('admin_data.registers.columns.code'),
            'kind' => (string) __('admin_data.registers.columns.kind'),
            'occurred_on' => (string) __('admin_data.registers.columns.date'),
            'zone' => (string) __('admin_data.registers.columns.zone'),
            'commune' => (string) __('admin_data.registers.columns.commune'),
            'population' => (string) __('admin_data.registers.columns.population'),
            'sex' => (string) __('admin_data.registers.columns.sex'),
            'age_range' => (string) __('admin_data.registers.columns.age'),
            'violence' => (string) __('admin_data.registers.columns.violence'),
            'status' => (string) __('admin_data.registers.columns.status'),
            'peer' => (string) __('admin_data.registers.columns.peer'),
            'referral' => (string) __('admin_data.registers.columns.referral'),
        ];
    }

    protected function csvRow(Model $model, bool $full): array
    {
        /** @var CaseRecord $model */
        return [
            'code' => $model->code,
            'kind' => $model->kind->value,
            'occurred_on' => $model->occurred_on?->format('Y-m-d'),
            'zone' => $model->zone?->name,
            'commune' => $model->commune?->name,
            'population' => $model->population?->label,
            'sex' => $model->sex->label(),
            'age_range' => $model->age_range->value,
            'violence' => $model->violenceType?->label,
            'status' => $model->status->label(),
            'peer' => $model->collector?->pseudo,
            'referral' => $model->referral,
        ];
    }

    // --- Données de la vue ---------------------------------------------------------------------------------------------

    /**
     * Décompte par statut, hors filtres (pastilles et KPI) : un dossier archivé n'entre ni dans « Actifs » ni dans les KPI.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $counts = array_fill_keys(array_map(static fn (CaseStatus $s): string => $s->value, CaseStatus::cases()), 0);

        $rows = CaseRecordScope::visibleTo($this->user())->toBase()
            ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        foreach ($rows as $status => $n) {
            $counts[(string) $status] = (int) $n;
        }

        return $counts;
    }

    /**
     * Dossier du modal « Détail » : lecture complète après autorisation (notes comprises) ; calculée à la demande, jamais
     * conservée dans l'état de la page.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function detail(): ?array
    {
        if ($this->detailId === 0) {
            return null;
        }

        $user = $this->user();
        $case = CaseRecordScope::visibleTo($user)->with(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label', 'collector:id,pseudo'])->find($this->detailId);

        if ($case === null || $user->cannot('view', $case)) {
            return null;
        }

        $anonymized = $case->isAnonymized();

        return [
            'case' => $case,
            'notes' => $anonymized ? null : $case->notes,
            'anonymized' => $anonymized,
            'warnings' => $anonymized ? [] : app(CaseQuality::class)->warnings($case, $user),
            'transitions' => array_values(array_filter(
                [$case->status, ...$case->status->allowedTransitions()],
                static fn (CaseStatus $status): bool => $status !== CaseStatus::Archived,
            )),
            'followups' => $case->followups()->count(),
            'forms' => $case->forms()->count(),
            'can_update' => ! $anonymized && $case->status !== CaseStatus::Archived && $user->can('update', $case),
        ];
    }

    /**
     * Communes du département choisi dans la modale (liste en cascade).
     *
     * @return list<array{id: int, name: string}>
     */
    #[Computed]
    public function formCommunes(): array
    {
        $zoneId = (int) ($this->form['zone_id'] ?? 0);

        foreach (app(Referentials::class)->zones() as $zone) {
            if ($zone['id'] === $zoneId) {
                return array_map(static fn (array $commune): array => ['id' => $commune['id'], 'name' => $commune['name']], $zone['communes']);
            }
        }

        return [];
    }

    public function render(): View
    {
        $user = $this->user();
        $rows = $this->paginateQuery($this->filtered($user)
            ->select(['cases.id', 'cases.code', 'cases.kind', 'cases.occurred_on', 'cases.zone_id', 'cases.commune_id', 'cases.population_id', 'cases.sex', 'cases.age_range', 'cases.violence_type_id', 'cases.status', 'cases.collected_by', 'cases.anonymized_at'])
            ->with(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label', 'collector:id,pseudo']));
        [$sortKey, $sortDirection] = $this->currentSort();
        $referentials = app(Referentials::class);

        return view('livewire.admin.registers.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $this->formatter(),
            'zones' => $referentials->zones(),
            'violenceTypes' => $referentials->violenceTypes(),
            'populations' => $referentials->populations(),
            'options' => app(CollectOptions::class)->lists(),
            'referrals' => CaseReferral::OPTIONS,
            'editStatuses' => $this->editStatuses(),
            'canCreate' => $user->can('create', CaseRecord::class),
            'canExport' => $user->can('export', CaseRecord::class),
            'periodError' => $this->periodError(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_data.registers.title'),
            'adminPage' => 'registres',
        ]);
    }

    // --- Interne ---------------------------------------------------------------------------------------------------------

    /**
     * Requête filtrée du scope du compte (recherche et tri ajoutés par WithDataTable) : jointures de lecture seule pour
     * chercher et trier sur le nom de la commune, du département et de la pair·e (colonnes en clair).
     *
     * @return Builder<CaseRecord>
     */
    private function filtered(User $user): Builder
    {
        $query = CaseRecordScope::visibleTo($user)
            ->leftJoin('communes', 'communes.id', '=', 'cases.commune_id')
            ->leftJoin('zones', 'zones.id', '=', 'cases.zone_id')
            ->leftJoin('users as collectors', 'collectors.id', '=', 'cases.collected_by')
            ->select('cases.*');

        $status = CaseStatus::tryFrom($this->status);
        $status === null
            ? $query->where('cases.status', '!=', CaseStatus::Archived->value)
            : $query->where('cases.status', $status->value);

        if (($kind = CaseKind::tryFrom($this->kind)) !== null) {
            $query->where('cases.kind', $kind->value);
        }

        foreach (['zone' => 'zone_id', 'violence' => 'violence_type_id', 'population' => 'population_id'] as $property => $column) {
            if (ctype_digit($this->{$property}) && $this->{$property} !== '') {
                $query->where('cases.'.$column, (int) $this->{$property});
            }
        }

        if (self::validDay($this->from)) {
            $query->where('cases.occurred_on', '>=', $this->from);
        }

        if (self::validDay($this->until)) {
            $query->where('cases.occurred_on', '<=', $this->until);
        }

        return $query;
    }

    /**
     * Statuts proposés à la modification : le statut actuel et ses transitions, sans l'archivage (bouton dédié).
     *
     * @return list<CaseStatus>
     */
    private function editStatuses(): array
    {
        $from = CaseStatus::tryFrom($this->editFrom);

        return $from === null ? [] : array_values(array_filter(
            [$from, ...$from->allowedTransitions()],
            static fn (CaseStatus $status): bool => $status !== CaseStatus::Archived,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'kind' => CaseKind::Vbg->value,
            'occurred_on' => now((string) config('xomiton.display_timezone', 'UTC'))->format('Y-m-d'),
            'zone_id' => '',
            'commune_id' => '',
            'population_id' => '',
            'sex' => '',
            'age_range' => '',
            'violence_type_id' => '',
            'referral' => '',
            'notes' => '',
            'status' => CaseStatus::Open->value,
        ];
    }

    private function create(User $user, CreateCase $create, CaseRules $rules): void
    {
        $data = $this->formData();
        $data['client_uuid'] = trim($this->clientUuid) === '' ? null : $this->clientUuid;
        $validated = $this->validateForm('form', $data, $rules->forCreate(), $this->fieldNames());

        $written = $create($user, CaseInput::fromValidated($validated), $validated['client_uuid'] ?? null);

        $this->closeModal(self::FORM_MODAL);
        $this->resetPage();
        unset($this->counts);

        if (! $written->created) {
            $this->toast((string) __('admin_data.registers.toasts.replayed', ['code' => $written->case->code]), 'info');

            return;
        }

        $this->toast((string) __('admin_data.registers.toasts.created', ['code' => $written->case->code]));

        foreach ($written->warnings as $warning) {
            $this->toast($warning->message, 'warning');
        }
    }

    private function update(User $user, UpdateCase $update, CaseRules $rules): void
    {
        $case = $this->find($this->editId);

        if ($case === null) {
            return;
        }

        $data = $this->formData();
        unset($data['kind']);
        $notes = trim((string) ($this->form['notes'] ?? ''));

        // Les notes existantes ne sont jamais affichées dans le formulaire : vide = inchangées, sauf « effacer ».
        if ($notes === '' && ! $this->clearNotes) {
            unset($data['notes']);
        } elseif ($this->clearNotes && $notes === '') {
            $data['notes'] = null;
        }

        $data = $rules->withCurrentLocation($data, $case);
        $validated = $this->validateForm('form', $data, $rules->forUpdate($user), $this->fieldNames());
        $target = CaseStatus::tryFrom((string) ($validated['status'] ?? ''));

        if ($target === CaseStatus::Archived) {
            $this->addError('form.status', (string) __('admin_data.registers.errors.status'));

            return;
        }

        $written = $update($user, $case, CaseChanges::fromValidated($validated));

        $this->closeModal(self::FORM_MODAL);
        unset($this->counts, $this->detail);
        $this->toast((string) __('admin_data.registers.toasts.updated', ['code' => $written->case->code]));

        foreach ($written->warnings as $warning) {
            $this->toast($warning->message, 'warning');
        }
    }

    /**
     * Formulaire normalisé pour les règles : une liste déroulante vide devient `null` (jamais 0 ni chaîne vide).
     *
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $data = [];

        foreach ($this->form as $key => $value) {
            $data[(string) $key] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return (array) __('admin_data.registers.fields');
    }

    private function find(int $id): ?CaseRecord
    {
        $case = CaseRecordScope::visibleTo($this->user())->find($id);

        if ($case === null) {
            $this->toast((string) __('admin_data.registers.errors.not_found'), 'error');
        }

        return $case;
    }

    private function closeIfOpen(CaseRecord $case): void
    {
        if ($this->detailId === (int) $case->getKey()) {
            $this->closeModal(self::DETAIL_MODAL);
            $this->closeDetail();
        }

        unset($this->counts);
    }

    private function periodError(): ?string
    {
        $invalid = ($this->from !== '' && ! self::validDay($this->from))
            || ($this->until !== '' && ! self::validDay($this->until))
            || (self::validDay($this->from) && self::validDay($this->until) && $this->from > $this->until);

        return $invalid ? (string) __('admin.filters.invalid_period') : null;
    }

    private static function validDay(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
    }
}
