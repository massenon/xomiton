<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Directory;

use App\Application\Directory\ChangeDirectoryContact;
use App\Application\Directory\Data\DirectoryImportReport;
use App\Application\Directory\DirectoryContactRules;
use App\Application\Directory\DirectoryCsv;
use App\Application\Directory\ImportDirectoryCsv;
use App\Application\Directory\SaveDirectoryContact;
use App\Application\Shared\Referentials;
use App\Enums\DirectoryRole;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\CsvExport;
use App\Support\LikePattern;
use App\Support\LocaleFormatter;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Annuaire dynamique `/admin/annuaire` (P11-04, prototype admin/annuaire.php) : les personnes et structures que le
 * routage RG-04 cible pour une alerte. KPI, liste (recherche sur le nom, l'organisation et l'e-mail), filtres rôle /
 * département / statut, **activer / désactiver** (seules les fiches actives sont ciblées), supprimer (logique), modale
 * contact — rôles dont « Responsable de réseau » et « Personnel identifié », drapeaux public / urgence / point focal
 * national, lien éventuel vers un compte —, **import CSV** (modèle téléchargeable, lignes rejetées dans un rapport),
 * **export CSV** avec coordonnées (`cases.export` en plus de `directory.manage`, ADR-33 : téléphone et e-mail sont des
 * colonnes sensibles, export « complet » journalisé) et, en dessous, l'aperçu du routage (RoutingPreview, composant
 * enfant rafraîchi à chaque écriture).
 *
 * Réservé à `directory.manage` (DirectoryContactPolicy). Les mêmes règles que l'import valident la modale
 * (DirectoryContactRules : `phone_bj`, `email`, `commune_in_zone`).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithDataTable;
    use WithFileUploads;

    public const FORM_MODAL = 'xmDirectoryForm';

    public const IMPORT_MODAL = 'xmDirectoryImport';

    #[SafeUrl(as: 'role', except: '')]
    public string $role = '';

    #[SafeUrl(as: 'zone', except: '')]
    public string $zone = '';

    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    /**
     * Formulaire de la modale « Contact » (colonnes de `directory_contacts` en snake_case, plus `user_ref`).
     *
     * @var array<string, mixed>
     */
    public array $form = [];

    /** Contact modifié (0 : création) ; verrouillé. */
    #[Locked]
    public int $editId = 0;

    /** Fichier CSV de l'import. */
    public ?UploadedFile $csv = null;

    /**
     * Rapport du dernier import (effectifs et lignes rejetées, sans coordonnées).
     *
     * @var array{created: int, updated: int, rejected: list<array{line: int, name: string, reasons: list<string>}>}|null
     */
    public ?array $importReport = null;

    public function boot(): void
    {
        $this->authorize('viewAny', DirectoryContact::class);
        abort_unless($this->user()->can('directory.manage'), 403);
    }

    public function mount(): void
    {
        $this->form = $this->blankForm();
    }

    protected function sortableColumns(): array
    {
        return ['nom' => 'directory_contacts.name', 'role' => 'directory_contacts.role', 'zone' => 'zones.name', 'actif' => 'directory_contacts.is_active'];
    }

    protected function searchableColumns(): array
    {
        return ['directory_contacts.name', 'directory_contacts.org', 'directory_contacts.email'];
    }

    protected function defaultSort(): array
    {
        return ['nom', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['role', 'zone', 'status'];
    }

    // --- Actions de la vue ---------------------------------------------------------------------------------------------

    public function openCreate(): void
    {
        $this->authorize('create', DirectoryContact::class);
        $this->resetErrorBag();
        $this->editId = 0;
        $this->form = $this->blankForm();
        $this->openModal(self::FORM_MODAL);
    }

    public function openEdit(int $id): void
    {
        $contact = DirectoryContact::query()->with('user:id,pseudo')->find($id);

        if ($contact === null) {
            $this->toast((string) __('admin_data.directory.errors.not_found'), 'error');

            return;
        }

        $this->authorize('update', $contact);
        $this->resetErrorBag();
        $this->editId = (int) $contact->getKey();
        $this->form = [
            'name' => $contact->name,
            'role' => $contact->role->value,
            'org' => $contact->org ?? '',
            'availability' => $contact->availability ?? '',
            'phone' => $contact->phone ?? '',
            'email' => $contact->email ?? '',
            'whatsapp' => $contact->whatsapp ?? '',
            'zone_id' => $contact->zone_id === null ? '' : (string) $contact->zone_id,
            'commune_id' => $contact->commune_id === null ? '' : (string) $contact->commune_id,
            'user_ref' => $contact->user?->pseudo ?? '',
            'is_active' => $contact->is_active,
            'is_public' => $contact->is_public,
            'is_emergency' => $contact->is_emergency,
            'is_national_focal' => $contact->is_national_focal,
        ];
        $this->openModal(self::FORM_MODAL);
    }

    /** Changer le département vide la commune (liste en cascade). */
    public function updatedFormZoneId(): void
    {
        $this->form['commune_id'] = '';
    }

    public function save(SaveDirectoryContact $save, DirectoryContactRules $rules): void
    {
        $user = $this->user();
        $contact = $this->editId === 0 ? null : DirectoryContact::query()->find($this->editId);

        if ($this->editId !== 0 && $contact === null) {
            $this->toast((string) __('admin_data.directory.errors.not_found'), 'error');

            return;
        }

        $data = [];

        foreach ($this->form as $key => $value) {
            $data[(string) $key] = is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value;
        }

        $account = $this->resolveAccount((string) ($data['user_ref'] ?? ''));
        unset($data['user_ref']);

        if ($account === false) {
            $this->resetErrorBag();
            $this->addError('form.user_ref', (string) __('admin_data.directory.errors.account_unknown'));

            return;
        }

        $data['user_id'] = $account?->getKey();
        $validated = $this->validateForm('form', $data, $rules->rules(), $this->fieldNames());
        $problems = DirectoryContactRules::crossChecks($validated);

        if ($problems !== []) {
            foreach ($problems as $field => $message) {
                $this->addError('form.'.$field, $message);
            }

            return;
        }

        $saved = $save($user, $validated, $contact);

        $this->closeModal(self::FORM_MODAL);
        $this->afterWrite();
        $this->toast((string) __($contact === null ? 'admin_data.directory.toasts.created' : 'admin_data.directory.toasts.updated', ['name' => $saved->name]));
    }

    public function toggle(int $id, ChangeDirectoryContact $change): void
    {
        $contact = DirectoryContact::query()->find($id);

        if ($contact === null) {
            $this->toast((string) __('admin_data.directory.errors.not_found'), 'error');

            return;
        }

        $change->toggle($this->user(), $contact);
        $this->afterWrite();
        $this->toast((string) __($contact->is_active ? 'admin_data.directory.toasts.activated' : 'admin_data.directory.toasts.deactivated', ['name' => $contact->name]));
        $this->warnIfNoNationalFocal();
    }

    public function delete(int $id, ChangeDirectoryContact $change): void
    {
        $contact = DirectoryContact::query()->find($id);

        if ($contact === null) {
            $this->toast((string) __('admin_data.directory.errors.not_found'), 'error');

            return;
        }

        $change->delete($this->user(), $contact);
        $this->afterWrite();
        $this->toast((string) __('admin_data.directory.toasts.deleted', ['name' => $contact->name]));
        $this->warnIfNoNationalFocal();
    }

    // --- Import / export CSV ---------------------------------------------------------------------------------------

    public function openImport(): void
    {
        $this->authorize('import', DirectoryContact::class);
        $this->resetErrorBag();
        $this->csv = null;
        $this->importReport = null;
        $this->openModal(self::IMPORT_MODAL);
    }

    public function import(ImportDirectoryCsv $import): void
    {
        $this->authorize('import', DirectoryContact::class);

        try {
            $this->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:1024']], [], ['csv' => (string) __('admin_data.directory.import.file')]);
        } catch (ValidationException $e) {
            $this->csv = null;

            throw $e;
        }

        try {
            $report = $import($this->user(), (string) $this->csv?->get());
        } catch (DomainException $e) {
            $this->addError('csv', $e->getMessage());

            return;
        } finally {
            $this->csv = null;
        }

        $this->importReport = $this->reportToArray($report);
        $this->afterWrite();
        $this->toast(
            (string) __('admin_data.directory.import.done', ['accepted' => $report->accepted(), 'rejected' => count($report->rejected)]),
            $report->accepted() > 0 ? 'success' : 'warning',
        );
    }

    /** Modèle de fichier : en-têtes et une ligne d'exemple (aucune donnée réelle). */
    public function downloadTemplate(): StreamedResponse
    {
        $this->authorize('import', DirectoryContact::class);

        return CsvExport::download(
            'modele-import-annuaire.csv',
            DirectoryCsv::headers(),
            [array_combine(DirectoryCsv::COLUMNS, (array) __('admin_data.directory.csv_example'))],
            full: true,
        );
    }

    /** Export CSV avec coordonnées : export « complet » (ADR-33), journalisé `export.csv_full`. */
    public function export(): void
    {
        $this->requestCsvExport(true);
    }

    protected function csvScreen(): string
    {
        return 'annuaire';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        $this->authorize('export', DirectoryContact::class);
    }

    protected function csvQuery(): Builder
    {
        return $this->filtered()->with(['zone:id,name', 'commune:id,name']);
    }

    protected function csvColumns(bool $full): array
    {
        $columns = DirectoryCsv::headers();

        if (! $full) {
            // Sans export complet : jamais téléphone, e-mail ni WhatsApp.
            unset($columns['phone'], $columns['email'], $columns['whatsapp']);
        }

        return $columns;
    }

    protected function csvRow(Model $model, bool $full): array
    {
        /** @var DirectoryContact $model */
        $yes = fn (bool $value): string => (string) __($value ? 'admin_data.directory.yes' : 'admin_data.directory.no');

        return [
            'name' => $model->name,
            'role' => $model->role->label(),
            'org' => $model->org,
            'phone' => $model->phone,
            'email' => $model->email,
            'whatsapp' => $model->whatsapp,
            'zone' => $model->zone?->name,
            'commune' => $model->commune?->name,
            'availability' => $model->availability,
            'active' => $yes($model->is_active),
            'public' => $yes($model->is_public),
            'national_focal' => $yes($model->is_national_focal),
            'emergency' => $yes($model->is_emergency),
        ];
    }

    // --- Données de la vue ---------------------------------------------------------------------------------------------

    /**
     * Communes du département choisi dans la modale (liste en cascade).
     *
     * @return list<array{id: int, name: string}>
     */
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
        $rows = $this->paginateQuery($this->filtered()
            ->select('directory_contacts.*')
            ->with(['zone:id,name', 'commune:id,name', 'user:id,pseudo']));
        [$sortKey, $sortDirection] = $this->currentSort();
        $user = $this->user();

        return view('livewire.admin.directory.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'stats' => $this->stats(),
            'zones' => app(Referentials::class)->zones(),
            'roles' => DirectoryRole::cases(),
            'communes' => $this->formCommunes(),
            'canExport' => $user->can('export', DirectoryContact::class) && $user->can('cases.export'),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_data.directory.title'),
            'adminPage' => 'annuaire',
        ]);
    }

    // --- Interne ---------------------------------------------------------------------------------------------------------

    /**
     * Recherche sur le nom, l'organisation et l'e-mail (colonnes en clair ; jamais le téléphone).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query): Builder
    {
        self::assertClearColumns($query->getModel(), ['name', 'org', 'email'], 'recherche');
        $term = $this->searchTerm();

        if ($term === '') {
            return $query;
        }

        $pattern = LikePattern::contains($term);

        return $query->where(function (Builder $where) use ($pattern): void {
            foreach (['directory_contacts.name', 'directory_contacts.org', 'directory_contacts.email'] as $column) {
                $where->orWhereRaw(LikePattern::clause($column), [$pattern]);
            }
        });
    }

    /**
     * @return Builder<DirectoryContact>
     */
    private function filtered(): Builder
    {
        $query = DirectoryContact::query()->leftJoin('zones', 'zones.id', '=', 'directory_contacts.zone_id');

        if (($role = DirectoryRole::tryFrom($this->role)) !== null) {
            $query->where('directory_contacts.role', $role->value);
        }

        if (ctype_digit($this->zone) && $this->zone !== '') {
            $query->where('directory_contacts.zone_id', (int) $this->zone);
        }

        if ($this->status === 'actif') {
            $query->where('directory_contacts.is_active', true);
        } elseif ($this->status === 'inactif') {
            $query->where('directory_contacts.is_active', false);
        }

        return $query->select('directory_contacts.*');
    }

    /**
     * KPI de l'annuaire (fiches non supprimées).
     *
     * @return array{total: int, active: int, focal: int, trio: string}
     */
    private function stats(): array
    {
        $byRole = DirectoryContact::query()->toBase()->selectRaw('role, COUNT(*) as n')->groupBy('role')->pluck('n', 'role');
        $active = DirectoryContact::query()->active()->count();

        return [
            'total' => (int) $byRole->sum(),
            'active' => $active,
            'focal' => (int) ($byRole[DirectoryRole::PointFocal->value] ?? 0),
            'trio' => implode(' · ', [
                (int) ($byRole[DirectoryRole::Juriste->value] ?? 0),
                (int) ($byRole[DirectoryRole::Psychologue->value] ?? 0),
                (int) ($byRole[DirectoryRole::Gynecologue->value] ?? 0),
            ]),
        ];
    }

    /**
     * Compte désigné par « pseudo ou e-mail » (null : aucun lien ; false : introuvable).
     */
    private function resolveAccount(string $reference): User|false|null
    {
        $reference = trim($reference);

        if ($reference === '') {
            return null;
        }

        $user = User::query()
            ->where(fn (Builder $query) => $query->whereRaw('LOWER(pseudo) = ?', [mb_strtolower($reference)])->orWhereRaw('LOWER(email) = ?', [mb_strtolower($reference)]))
            ->first();

        return $user ?? false;
    }

    private function afterWrite(): void
    {
        $this->dispatch('directory-changed');
    }

    private function warnIfNoNationalFocal(): void
    {
        if (ChangeDirectoryContact::nationalFocalMissing()) {
            $this->toast((string) __('admin_data.directory.toasts.no_national_focal'), 'warning');
        }
    }

    /**
     * @return array{created: int, updated: int, rejected: list<array{line: int, name: string, reasons: list<string>}>}
     */
    private function reportToArray(DirectoryImportReport $report): array
    {
        return ['created' => $report->created, 'updated' => $report->updated, 'rejected' => array_slice($report->rejected, 0, 100)];
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'name' => '',
            'role' => DirectoryRole::Juriste->value,
            'org' => '',
            'availability' => '',
            'phone' => '',
            'email' => '',
            'whatsapp' => '',
            'zone_id' => '',
            'commune_id' => '',
            'user_ref' => '',
            'is_active' => true,
            'is_public' => true,
            'is_emergency' => false,
            'is_national_focal' => false,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fieldNames(): array
    {
        return (array) __('admin_data.directory.fields');
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
