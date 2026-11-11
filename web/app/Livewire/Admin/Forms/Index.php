<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Forms;

use App\Application\Cases\CaseFormRules;
use App\Application\Cases\CaseFormSheet;
use App\Application\Cases\Data\CaseFormInput;
use App\Application\Cases\DeleteCaseForm;
use App\Application\Cases\SaveCaseForm;
use App\Domain\Cases\CaseCode;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Enums\CaseStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Policies\Scopes\CaseRecordScope;
use App\Rules\NotFutureDate;
use App\Support\Audit;
use App\Support\LikePattern;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Fiches de référence VBG / VDH `/admin/fiches` (P11-02, prototype admin/fiches.php) : création liée à un dossier
 * existant (SaveCaseForm : référence `FR-AAAA-nnnn`, identification pseudonymisée reprise du dossier), cinq sections
 * (identification, faits, **sept services reçus**, orientation urgente vers l'annuaire, consentement écrit ou oral et
 * partage), lecture (modale), **impression A4 / PDF côté serveur** (CaseFormPdfController, pied « Confidentiel »,
 * ADR-23) et suppression journalisée (DeleteCaseForm). Le consentement de la personne est obligatoire.
 *
 * Réservé à `case_forms.manage` ; les fiches suivent le périmètre des dossiers (CaseRecordScope) et ne concernent que les
 * dossiers non archivés. La liste n'affiche ni récit, ni auteur·e des faits, ni signataire (colonnes chiffrées) et ne les
 * cherche pas (ADR-33) ; la lecture complète d'une fiche (modale) est journalisée (`case_forms.viewed`, référence seule).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithDataTable;

    public const FORM_MODAL = 'xmFormEditor';

    public const DETAIL_MODAL = 'xmFormDetail';

    /** Services proposés à la saisie : les sept du prototype (la rubrique `autre` se dit dans les précisions). */
    public const SERVICES = ['ecoute', 'medical', 'psy', 'juridique', 'hebergement', 'dignite', 'securite'];

    /** Code d'un dossier : n'affiche que ses fiches et pré-remplit « Nouvelle fiche » (lien depuis les registres). */
    #[SafeUrl(as: 'dossier', except: '')]
    public string $dossier = '';

    #[SafeUrl(as: 'type', except: '')]
    public string $kind = '';

    /**
     * Formulaire « Nouvelle fiche » (colonnes de `case_forms` en snake_case, plus `case_code`).
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /** Fiche ouverte dans la modale « Détail » ; verrouillée. */
    #[Locked]
    public int $detailId = 0;

    /** À chaque requête Livewire (pas seulement au montage) : une permission retirée en cours de session coupe l'écran. */
    public function boot(): void
    {
        $this->authorize('viewAny', CaseForm::class);
        abort_unless($this->user()->can('case_forms.manage'), 403);
    }

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    protected function sortableColumns(): array
    {
        return ['reference' => 'case_forms.reference', 'date' => 'case_forms.created_at', 'dossier' => 'cases.code'];
    }

    protected function searchableColumns(): array
    {
        return ['case_forms.reference', 'case_forms.referral_label'];
    }

    protected function defaultSort(): array
    {
        return ['date', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['dossier', 'kind'];
    }

    /**
     * Recherche sur la référence, le code du dossier, l'orientation et l'auteur·e de la fiche (colonnes en clair).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query): Builder
    {
        self::assertClearColumns($query->getModel(), ['reference', 'referral_label'], 'recherche');
        $term = $this->searchTerm();

        if ($term === '') {
            return $query;
        }

        $pattern = LikePattern::contains($term);

        return $query->where(function (Builder $where) use ($pattern): void {
            foreach (['case_forms.reference', 'cases.code', 'case_forms.referral_label', 'authors.pseudo'] as $column) {
                $where->orWhereRaw(LikePattern::clause($column), [$pattern]);
            }
        });
    }

    // --- Actions de la vue ---------------------------------------------------------------------------------------------

    public function openCreate(): void
    {
        $this->authorize('create', CaseForm::class);
        $this->resetErrorBag();
        $this->form = $this->blankForm();
        $this->openModal(self::FORM_MODAL);
    }

    public function save(SaveCaseForm $save, CaseFormRules $rules): void
    {
        $user = $this->user();
        $data = [];

        foreach ($this->form as $key => $value) {
            $data[(string) $key] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }

        $case = $this->resolvedCase((string) ($data['case_code'] ?? ''));

        if ($case === null) {
            $this->resetErrorBag();
            $this->addError('form.case_code', (string) __('admin_data.forms.errors.case_unknown'));

            return;
        }

        unset($data['case_code']);
        $validated = $this->validateForm('form', $data, $rules->rules(), $this->fieldNames());

        try {
            $form = $save($user, $case, CaseFormInput::fromValidated($validated));
        } catch (CaseLocked) {
            $this->addError('form.case_code', (string) __('admin_data.forms.errors.locked'));

            return;
        }

        $this->closeModal(self::FORM_MODAL);
        $this->form = $this->blankForm();
        $this->resetPage();
        $this->toast((string) __('admin_data.forms.toasts.created', ['reference' => $form->reference]));
        $this->openDetail((int) $form->getKey(), false);
    }

    public function openDetail(int $id, bool $audit = true): void
    {
        $form = $this->find($id);

        if ($form === null) {
            return;
        }

        $this->authorize('view', $form);
        $this->detailId = (int) $form->getKey();
        unset($this->detail);

        // Lecture du récit : tracée (référence seule), comme le détail d'une alerte.
        if ($audit) {
            Audit::log('case_forms.viewed', $form, sprintf('fiche=%s', $form->reference), $this->user());
        }

        $this->openModal(self::DETAIL_MODAL);
    }

    /** Fermeture de la modale « Détail » : plus aucune donnée déchiffrée n'est conservée par la page. */
    public function closeDetail(): void
    {
        $this->detailId = 0;
        unset($this->detail);
    }

    public function delete(int $id, DeleteCaseForm $delete): void
    {
        $form = $this->find($id);

        if ($form === null) {
            return;
        }

        $reference = $form->reference;
        $delete($this->user(), $form);

        if ($this->detailId === $id) {
            $this->closeModal(self::DETAIL_MODAL);
            $this->closeDetail();
        }

        $this->toast((string) __('admin_data.forms.toasts.deleted', ['reference' => $reference]));
    }

    // --- Données de la vue ---------------------------------------------------------------------------------------------

    /**
     * Dossier visé par le formulaire (code saisi) : sa description pseudonymisée, jamais ses notes.
     */
    #[Computed]
    public function selectedCase(): ?CaseRecord
    {
        $code = (string) ($this->form['case_code'] ?? '');

        if (trim($code) === '') {
            return null;
        }

        return $this->resolvedCase($code)?->loadMissing(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label']);
    }

    /**
     * Fiche de la modale « Détail » : lecture complète après autorisation ; calculée à la demande, jamais conservée dans
     * l'état de la page.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function detail(): ?array
    {
        if ($this->detailId === 0) {
            return null;
        }

        $form = $this->find($this->detailId, notify: false);

        return $form === null ? null : ['id' => (int) $form->getKey(), 'sheet' => app(CaseFormSheet::class)($form)];
    }

    public function render(): View
    {
        $user = $this->user();
        $rows = $this->paginateQuery($this->filtered($user)
            ->select(['case_forms.id', 'case_forms.case_id', 'case_forms.reference', 'case_forms.created_by', 'case_forms.referral_label', 'case_forms.consent_given', 'case_forms.consent_sharing', 'case_forms.created_at'])
            ->with(['case:id,code,kind']));

        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.forms.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'services' => self::SERVICES,
            'contacts' => DirectoryContact::query()->active()->with('zone:id,name')->ordered()->limit(300)->get(['id', 'name', 'role', 'zone_id']),
            'caseCodes' => CaseRecordScope::visibleTo($user)->where('status', '!=', CaseStatus::Archived->value)->orderByDesc('id')->limit(200)->pluck('code'),
            'urgencies' => CaseFormRules::URGENCIES,
            'today' => NotFutureDate::today()->format('Y-m-d'),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_data.forms.title'),
            'adminPage' => 'fiches',
        ]);
    }

    // --- Interne ---------------------------------------------------------------------------------------------------------

    /**
     * Fiches des dossiers visibles et non archivés (sans les filtres de l'écran).
     *
     * @return Builder<CaseForm>
     */
    private function visible(User $user): Builder
    {
        return CaseForm::query()
            ->join('cases', 'cases.id', '=', 'case_forms.case_id')
            ->leftJoin('users as authors', 'authors.id', '=', 'case_forms.created_by')
            ->where('cases.status', '!=', CaseStatus::Archived->value)
            ->whereIn('cases.id', CaseRecordScope::visibleTo($user)->select('cases.id')->toBase())
            ->select('case_forms.*');
    }

    /**
     * Fiches visibles, filtrées par dossier et par nature ; recherche et tri ajoutés par WithDataTable.
     *
     * @return Builder<CaseForm>
     */
    private function filtered(User $user): Builder
    {
        $query = $this->visible($user);

        if (($parsed = CaseCode::tryParse($this->dossier)) !== null) {
            $query->where('cases.code', $parsed->value());
        } elseif ($this->dossier !== '') {
            $query->whereRaw('1 = 0');
        }

        if (in_array($this->kind, ['VBG', 'VDH'], true)) {
            $query->where('cases.kind', $this->kind);
        }

        return $query;
    }

    private function find(int $id, bool $notify = true): ?CaseForm
    {
        $form = $this->visible($this->user())->whereKey($id)->first();

        if ($form === null && $notify) {
            $this->toast((string) __('admin_data.forms.errors.not_found'), 'error');
        }

        return $form;
    }

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

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        $today = NotFutureDate::today()->format('Y-m-d');

        return [
            'case_code' => CaseCode::tryParse($this->dossier)?->value() ?? '',
            'pseudo' => '',
            'facts_date' => '',
            'facts_place' => '',
            'facts_author' => '',
            'facts_description' => '',
            'services' => array_fill_keys(self::SERVICES, false),
            'services_note' => '',
            'referral_contact_id' => '',
            'referral_date' => $today,
            'referral_reason' => '',
            'referral_urgency' => 'normale',
            'consent_given' => false,
            'consent_mode' => 'oral',
            'consent_sharing' => false,
            'consent_signatory' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return (array) __('admin_data.forms.fields');
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
