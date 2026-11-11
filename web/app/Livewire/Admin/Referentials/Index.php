<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Referentials;

use App\Application\Admin\Referentials\ManageReferentials;
use App\Application\Admin\Referentials\ReferentialRules;
use App\Application\Admin\Referentials\ReferentialUsage;
use App\Application\Shared\Countries;
use App\Domain\Referentials\Exceptions\ReferentialRefused;
use App\Enums\DirectoryRole;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Models\Commune;
use App\Models\Need;
use App\Models\Population;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Référentiels `/admin/referentiels` (décision client du 11/11/2026, permission `referentials.manage`) : onglets Zones et
 * communes (zones de tout pays, communes d'une zone), Types de violence, Besoins, Populations. CRUD complet : créer,
 * modifier, activer / désactiver, réordonner (Monter / Descendre), supprimer avec confirmation seulement si l'élément n'est
 * utilisé nulle part (sinon refus clair qui propose de le désactiver). La permission est revérifiée à chaque action ; les
 * identifiants de l'élément édité sont verrouillés (#[Locked]) ; chaque écriture passe par ManageReferentials (journal,
 * invalidation du cache des référentiels).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;

    public const TABS = ['zones', 'violence_types', 'needs', 'populations'];

    public const MODAL = 'xmReferentialModal';

    #[SafeUrl(as: 'onglet', except: 'zones')]
    public string $tab = 'zones';

    /** Pays dont les zones sont affichées (onglet Zones). */
    #[SafeUrl(as: 'pays', except: Countries::BENIN)]
    public string $country = Countries::BENIN;

    /** Identifiant de la zone dont on gère les communes (vide : liste des zones). */
    #[SafeUrl(as: 'zone', except: '')]
    public string $zone = '';

    /** Élément en cours de modification (null : création). */
    #[Locked]
    public ?int $itemId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        $this->authorizeManage();

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'zones';
        }

        if (! Countries::isValid($this->country)) {
            $this->country = Countries::BENIN;
        }

        // Une zone d'URL inconnue ou d'un autre pays ramène à la liste des zones du pays.
        $zone = $this->currentZone();

        if ($zone === null) {
            $this->zone = '';
        } else {
            $this->country = (string) $zone->country_code;
        }

        $this->resetForm();
    }

    public function setTab(string $tab): void
    {
        $this->authorizeManage();

        if (! in_array($tab, self::TABS, true)) {
            return;
        }

        $this->tab = $tab;
        $this->zone = '';
        $this->resetForm();
    }

    /** Changement de pays (liste déroulante de l'onglet Zones). */
    public function updatedCountry(): void
    {
        $this->authorizeManage();

        if (! Countries::isValid($this->country)) {
            $this->country = Countries::BENIN;
        }

        $this->zone = '';
    }

    public function openZone(int $id): void
    {
        $this->authorizeManage();
        $zone = Zone::query()->findOrFail($id);
        $this->tab = 'zones';
        $this->country = (string) $zone->country_code;
        $this->zone = (string) $zone->getKey();
        $this->resetForm();
    }

    public function closeZone(): void
    {
        $this->authorizeManage();
        $this->zone = '';
        $this->resetForm();
    }

    // --- Formulaire ---------------------------------------------------------------------------------------------------

    public function resetForm(): void
    {
        $this->reset('itemId');
        $this->form = $this->blankForm();
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->openModal(self::MODAL);
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $item = $this->findItem($id);
        $this->resetForm();
        $this->itemId = (int) $item->getKey();
        $this->form = $this->formFor($item);
        $this->openModal(self::MODAL);
    }

    public function save(ManageReferentials $manage): void
    {
        $this->authorizeManage();
        $kind = $this->kind();
        $item = $this->itemId === null ? null : $this->findItem($this->itemId);
        $form = $this->normalizedForm($kind, $item);

        try {
            $saved = match ($kind) {
                'commune' => $this->saveCommune($manage, $item, $form),
                'violence_types' => $manage->saveViolenceType($item instanceof ViolenceType ? $item : null, $this->validated($form, ReferentialRules::violenceType($this->itemId)), $this->actor()),
                'needs' => $manage->saveNeed($item instanceof Need ? $item : null, $this->validated($form, ReferentialRules::need($this->itemId)), $this->actor()),
                'populations' => $manage->savePopulation($item instanceof Population ? $item : null, $this->validated($form, ReferentialRules::population($this->itemId)), $this->actor()),
                default => $manage->saveZone($item instanceof Zone ? $item : null, $this->validated($form, ReferentialRules::zone($this->itemId, (string) ($form['country_code'] ?? ''))), $this->actor()),
            };
        } catch (ReferentialRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->closeModal(self::MODAL);
        $this->resetForm();
        $this->toast((string) __($saved->wasRecentlyCreated ? 'admin_referentials.toasts.created' : 'admin_referentials.toasts.saved', ['label' => $this->labelOf($saved)]));
    }

    // --- Actions sur une ligne ----------------------------------------------------------------------------------------

    public function toggle(int $id, ManageReferentials $manage): void
    {
        $this->authorizeManage();
        $item = $this->findItem($id);
        $active = $manage->toggle($item, $this->actor());
        $this->toast((string) __($active ? 'admin_referentials.toasts.activated' : 'admin_referentials.toasts.deactivated', ['label' => $this->labelOf($item)]));
    }

    public function move(int $id, string $direction, ManageReferentials $manage): void
    {
        $this->authorizeManage();
        $item = $this->findItem($id);
        $position = $manage->move($item, $direction === 'up' ? -1 : 1, $this->actor());

        if ($position !== null) {
            $this->toast((string) __('admin_referentials.toasts.moved', ['label' => $this->labelOf($item), 'position' => $position]), 'info');
        }
    }

    public function delete(int $id, ManageReferentials $manage): void
    {
        $this->authorizeManage();
        $item = $this->findItem($id);
        $label = $this->labelOf($item);

        try {
            $manage->delete($item, $this->actor());
        } catch (ReferentialRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->toast((string) __('admin_referentials.toasts.deleted', ['label' => $label]), 'warning');
    }

    // --- Lecture ------------------------------------------------------------------------------------------------------

    /**
     * Genre de ligne affiché : `commune` quand une zone est ouverte, sinon l'onglet.
     */
    public function kind(): string
    {
        if ($this->tab === 'zones') {
            return $this->currentZone() === null ? 'zones' : 'commune';
        }

        return in_array($this->tab, self::TABS, true) ? $this->tab : 'zones';
    }

    public function currentZone(): ?Zone
    {
        return ctype_digit($this->zone) && $this->zone !== '' ? Zone::query()->find((int) $this->zone) : null;
    }

    /**
     * Lignes du tableau courant, dans l'ordre d'affichage.
     *
     * @return list<array{id: int, label: string, slug: string, active: bool, usage: int, communes: int, country: string, icon: string|null, text: string|null, role: string|null}>
     */
    public function rows(): array
    {
        return match ($this->kind()) {
            'commune' => $this->communeRows(),
            'violence_types' => $this->violenceRows(),
            'needs' => $this->needRows(),
            'populations' => $this->populationRows(),
            default => $this->zoneRows(),
        };
    }

    /**
     * Pays proposés pour les zones : ceux qui en ont déjà (avec leur nombre), puis tous les autres.
     *
     * @return array{with: list<array{value: string, label: string, count: int}>, others: list<array{value: string, label: string}>}
     */
    public function countries(): array
    {
        /** @var array<string, int> $counts */
        $counts = Zone::query()->selectRaw('country_code, count(*) as total')->groupBy('country_code')->pluck('total', 'country_code')->map(static fn (mixed $total): int => (int) $total)->all();
        $with = [];
        $others = [];

        foreach (Countries::all() as $country) {
            if (isset($counts[$country['value']]) || $country['value'] === $this->country) {
                $with[] = ['value' => $country['value'], 'label' => $country['label'], 'count' => $counts[$country['value']] ?? 0];
            } else {
                $others[] = $country;
            }
        }

        return ['with' => $with, 'others' => $others];
    }

    /**
     * Compteurs affichés sur les onglets.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'zones' => Zone::query()->count(),
            'violence_types' => ViolenceType::query()->count(),
            'needs' => Need::query()->count(),
            'populations' => Population::query()->count(),
        ];
    }

    /**
     * Rôles d'annuaire proposés comme cible d'un besoin.
     *
     * @return array<string, string>
     */
    public function roleOptions(): array
    {
        $options = ['' => (string) __('admin_referentials.needs.no_role')];

        foreach (DirectoryRole::cases() as $role) {
            if ($role !== DirectoryRole::PointFocal) {
                $options[$role->value] = $role->label();
            }
        }

        return $options;
    }

    /** Le pays de la zone en cours d'édition est modifiable seulement si la zone n'est utilisée nulle part. */
    public function countryLocked(): bool
    {
        $item = $this->itemId === null ? null : Zone::query()->find($this->itemId);

        return $item instanceof Zone && ! app(ManageReferentials::class)->countryEditable($item);
    }

    public function render(): View
    {
        $this->authorizeManage();

        return view('livewire.admin.referentials.index', [
            'rows' => $this->rows(),
            'kind' => $this->kind(),
            'currentZone' => $this->currentZone(),
            'counts' => $this->counts(),
            'countries' => $this->tab === 'zones' ? $this->countries() : ['with' => [], 'others' => []],
            'roleOptions' => $this->roleOptions(),
            'countryLocked' => $this->countryLocked(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_referentials.title'),
            'adminPage' => 'referentiels',
        ]);
    }

    // --- Interne ------------------------------------------------------------------------------------------------------

    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('referentials.manage'), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Élément du genre courant (une commune doit appartenir à la zone ouverte).
     */
    private function findItem(int $id): Model
    {
        return match ($this->kind()) {
            'commune' => Commune::query()->where('zone_id', (int) $this->zone)->findOrFail($id),
            'violence_types' => ViolenceType::query()->findOrFail($id),
            'needs' => Need::query()->findOrFail($id),
            'populations' => Population::query()->findOrFail($id),
            default => Zone::query()->findOrFail($id),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function blankForm(): array
    {
        return [
            'label' => '',
            'slug' => '',
            'country_code' => $this->country,
            'icon' => 'bi-exclamation-circle',
            'description' => '',
            'routed_role' => '',
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formFor(Model $item): array
    {
        $label = $item instanceof Zone || $item instanceof Commune ? $item->name : $item->getAttribute('label');
        $role = $item instanceof Need ? $item->routed_role?->value : null;

        return [
            'label' => is_string($label) ? $label : '',
            'slug' => (string) $item->getAttribute('slug'),
            'country_code' => $item instanceof Zone ? (string) $item->country_code : $this->country,
            'icon' => $item instanceof ViolenceType ? (string) $item->icon : 'bi-exclamation-circle',
            'description' => $item instanceof ViolenceType ? (string) $item->description : '',
            'routed_role' => $role ?? '',
            'is_active' => (bool) $item->getAttribute('is_active'),
        ];
    }

    /**
     * Formulaire prêt à valider : textes rognés, code déduit du libellé s'il est vide, code figé à la modification.
     *
     * @return array<string, mixed>
     */
    private function normalizedForm(string $kind, ?Model $item): array
    {
        $form = $this->form;
        $label = is_string($form['label'] ?? null) ? trim($form['label']) : '';
        $form['label'] = $label;

        if ($item !== null) {
            // Le code est une clé stable : le formulaire modifié à la main ne le change pas.
            $form['slug'] = (string) $item->getAttribute('slug');
        } else {
            $slug = is_string($form['slug'] ?? null) ? trim($form['slug']) : '';
            $form['slug'] = $slug !== '' ? $slug : match ($kind) {
                'needs' => ManageReferentials::slugFrom($label, '_', 40),
                'violence_types' => ManageReferentials::slugFrom($label, '-', ReferentialRules::VIOLENCE_SLUG_MAX),
                'zones' => ManageReferentials::slugFrom($label, '-', 60),
                'commune' => ManageReferentials::slugFrom($label, '-', 80),
                default => ManageReferentials::slugFrom($label, '-', 40),
            };
        }

        if (is_string($form['routed_role'] ?? null) && $form['routed_role'] === '') {
            $form['routed_role'] = null;
        }

        if ($item instanceof Zone && $item->country_code !== strtoupper((string) ($form['country_code'] ?? '')) && $this->countryLocked()) {
            $form['country_code'] = $item->country_code;
        }

        $form['country_code'] = strtoupper(is_string($form['country_code'] ?? null) ? trim($form['country_code']) : '');
        $form['is_active'] = (bool) ($form['is_active'] ?? false);

        return $form;
    }

    /**
     * @param  array<string, mixed>  $form
     * @param  array<string, list<mixed>>  $rules
     * @return array<string, mixed>
     */
    private function validated(array $form, array $rules): array
    {
        return $this->validateForm('form', $form, $rules, (array) __('admin_referentials.fields'));
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function saveCommune(ManageReferentials $manage, ?Model $item, array $form): Commune
    {
        $zone = $this->currentZone();
        abort_if($zone === null, 404);

        $data = $this->validated($form, ReferentialRules::commune($this->itemId, (int) $zone->getKey()));

        return $manage->saveCommune($zone, $item instanceof Commune ? $item : null, $data, $this->actor());
    }

    private function labelOf(Model $item): string
    {
        $label = $item instanceof Zone || $item instanceof Commune ? $item->name : $item->getAttribute('label');

        return is_string($label) ? $label : '';
    }

    /**
     * @return list<array{id: int, label: string, slug: string, active: bool, usage: int, communes: int, country: string, icon: string|null, text: string|null, role: string|null}>
     */
    private function zoneRows(): array
    {
        $zones = Zone::query()->inCountry($this->country)->ordered()->get();
        $ids = $zones->map(static fn (Zone $zone): int => (int) $zone->getKey())->values()->all();
        $usage = ReferentialUsage::zones($ids);
        $communes = ReferentialUsage::communeCounts($ids);

        return $zones->map(fn (Zone $zone): array => $this->row((int) $zone->getKey(), (string) $zone->name, (string) $zone->slug, (bool) $zone->is_active, $usage[(int) $zone->getKey()] ?? 0, communes: $communes[(int) $zone->getKey()] ?? 0, country: (string) $zone->country_code))->values()->all();
    }

    /**
     * @return list<array{id: int, label: string, slug: string, active: bool, usage: int, communes: int, country: string, icon: string|null, text: string|null, role: string|null}>
     */
    private function communeRows(): array
    {
        $zone = $this->currentZone();

        if ($zone === null) {
            return [];
        }

        $communes = Commune::query()->inZone($zone)->ordered()->get();
        $usage = ReferentialUsage::communes($communes->map(static fn (Commune $commune): int => (int) $commune->getKey())->values()->all());

        return $communes->map(fn (Commune $commune): array => $this->row((int) $commune->getKey(), (string) $commune->name, (string) $commune->slug, (bool) $commune->is_active, $usage[(int) $commune->getKey()] ?? 0, country: (string) $zone->country_code))->values()->all();
    }

    /**
     * @return list<array{id: int, label: string, slug: string, active: bool, usage: int, communes: int, country: string, icon: string|null, text: string|null, role: string|null}>
     */
    private function violenceRows(): array
    {
        $types = ViolenceType::query()->ordered()->get();
        $usage = ReferentialUsage::violenceTypes($types->values()->all());

        return $types->map(fn (ViolenceType $type): array => $this->row((int) $type->getKey(), (string) $type->label, (string) $type->slug, (bool) $type->is_active, $usage[(int) $type->getKey()] ?? 0, icon: (string) $type->icon, text: (string) $type->description))->values()->all();
    }

    /**
     * @return list<array{id: int, label: string, slug: string, active: bool, usage: int, communes: int, country: string, icon: string|null, text: string|null, role: string|null}>
     */
    private function needRows(): array
    {
        $needs = Need::query()->ordered()->get();
        $usage = ReferentialUsage::needs($needs->values()->all());

        return $needs->map(fn (Need $need): array => $this->row((int) $need->getKey(), (string) $need->label, (string) $need->slug, (bool) $need->is_active, $usage[(int) $need->getKey()] ?? 0, role: $need->routed_role?->label()))->values()->all();
    }

    /**
     * @return list<array{id: int, label: string, slug: string, active: bool, usage: int, communes: int, country: string, icon: string|null, text: string|null, role: string|null}>
     */
    private function populationRows(): array
    {
        $populations = Population::query()->ordered()->get();
        $usage = ReferentialUsage::populations($populations->values()->all());

        return $populations->map(fn (Population $population): array => $this->row((int) $population->getKey(), (string) $population->label, (string) $population->slug, (bool) $population->is_active, $usage[(int) $population->getKey()] ?? 0))->values()->all();
    }

    /**
     * @return array{id: int, label: string, slug: string, active: bool, usage: int, communes: int, country: string, icon: string|null, text: string|null, role: string|null}
     */
    private function row(int $id, string $label, string $slug, bool $active, int $usage, int $communes = 0, string $country = '', ?string $icon = null, ?string $text = null, ?string $role = null): array
    {
        return ['id' => $id, 'label' => $label, 'slug' => $slug, 'active' => $active, 'usage' => $usage, 'communes' => $communes, 'country' => $country, 'icon' => $icon, 'text' => $text, 'role' => $role];
    }
}
