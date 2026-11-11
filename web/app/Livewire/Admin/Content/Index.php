<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content;

use App\Application\Admin\Content\ContentRules;
use App\Application\Admin\Content\ManageCategories;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Livewire\Admin\Content\Concerns\ManagesCategoryForm;
use App\Livewire\Admin\Content\Concerns\ManagesFaqForm;
use App\Livewire\Admin\Content\Concerns\ManagesResourceForm;
use App\Livewire\Admin\Content\Concerns\ManagesThematiqueForm;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Resource;
use App\Models\Thematique;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Contenu `/admin/contenu` (P12-03 ; prototype admin/contenu.php) : onglets Ressources · Thématiques · FAQ · Catégories,
 * KPI, tableaux (recherche sur les colonnes en clair, tri, filtres, pagination serveur), modales de création / modification,
 * publier / brouillon, téléversement par MediaUploader (illustration, fichier, image Open Graph 1200 × 630, sous-titres),
 * méta SEO validées en longueur et unicité, historique des slugs (301), FAQ de 60 mots au plus. Chaque onglet exige la
 * permission de son contenu (`resources.manage`, `thematiques.manage`, `faqs.manage`) ; chaque écriture passe par
 * App\Application\Admin\Content (policies rejouées, journal, invalidation du plan du site).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ManagesCategoryForm;
    use ManagesFaqForm;
    use ManagesResourceForm;
    use ManagesThematiqueForm;
    use ValidatesForm;
    use WithDataTable;
    use WithFileUploads;

    public const TABS = ['ressources' => 'resources.manage', 'thematiques' => 'thematiques.manage', 'faq' => 'faqs.manage', 'categories' => 'thematiques.manage'];

    #[SafeUrl(as: 'onglet', except: 'ressources')]
    public string $tab = 'ressources';

    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    #[SafeUrl(as: 'cat', except: '')]
    public string $category = '';

    #[SafeUrl(as: 'type', except: '')]
    public string $kind = '';

    public function mount(): void
    {
        $tabs = $this->tabs();
        abort_if($tabs === [], 403);

        if (! in_array($this->tab, $tabs, true)) {
            $this->tab = $tabs[0];
        }

        $this->resetResourceForm();
        $this->resetThematiqueForm();
        $this->resetFaqForm();
        $this->resetCategoryForm();
    }

    /**
     * Onglets ouverts à ce compte.
     *
     * @return list<string>
     */
    public function tabs(): array
    {
        $user = auth()->user();

        return array_values(array_keys(array_filter(self::TABS, static fn (string $permission): bool => $user instanceof User && $user->can($permission))));
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, $this->tabs(), true)) {
            return;
        }

        $this->tab = $tab;
        $this->reset('search', 'sort', 'direction', 'status', 'category', 'kind');
        $this->clearSelection();
        $this->resetPage();
    }

    // --- WithDataTable -----------------------------------------------------------------------------------------------

    protected function sortableColumns(): array
    {
        return match ($this->tab) {
            'faq' => ['position' => 'position', 'question' => 'question'],
            'categories' => ['position' => 'position', 'label' => 'label'],
            default => ['updated' => 'updated_at', 'title' => 'title', 'views' => 'views_count'],
        };
    }

    protected function searchableColumns(): array
    {
        return match ($this->tab) {
            'faq' => ['question', 'answer'],
            'categories' => ['label', 'slug'],
            default => ['title', 'summary', 'slug'],
        };
    }

    protected function defaultSort(): array
    {
        return match ($this->tab) {
            'faq', 'categories' => ['position', 'asc'],
            default => ['updated', 'desc'],
        };
    }

    protected function filterProperties(): array
    {
        return match ($this->tab) {
            'ressources' => ['status', 'category', 'kind'],
            'thematiques' => ['status', 'category'],
            'faq' => ['status', 'category'],
            default => ['kind'],
        };
    }

    // --- Données -----------------------------------------------------------------------------------------------------

    /**
     * @return list<array{label: string, value: string, icon: string, variant: string}>
     */
    #[Computed]
    public function kpis(): array
    {
        $format = $this->formatter();

        return [
            ['label' => __('admin_content.kpi.resources'), 'value' => $format->number(Resource::query()->published()->count()), 'icon' => 'folder2-open', 'variant' => 'success'],
            ['label' => __('admin_content.kpi.drafts'), 'value' => $format->number(Resource::query()->where('status', '!=', ContentStatus::Published)->count() + Thematique::query()->where('status', '!=', ContentStatus::Published)->count()), 'icon' => 'pencil-square', 'variant' => 'warning'],
            ['label' => __('admin_content.kpi.thematiques'), 'value' => $format->number(Thematique::query()->published()->count()), 'icon' => 'journal-text', 'variant' => 'violet'],
            ['label' => __('admin_content.kpi.faqs'), 'value' => $format->number(Faq::query()->published()->count()), 'icon' => 'question-circle', 'variant' => ''],
        ];
    }

    /**
     * Santé éditoriale de la FAQ : questions affichées sur l'accueil (3 à 6) et catégories de thématique hors des bornes
     * publiques (3 à 5 questions publiées, sinon la fiche n'affiche ni section ni FAQPage).
     *
     * @return array{home: int, home_ok: bool, categories: list<array{label: string, count: int}>}
     */
    #[Computed]
    public function faqHealth(): array
    {
        $home = Faq::query()->onHome()->count();
        $counts = Faq::query()->published()->whereNotNull('category_id')->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id');
        $outside = Category::query()->ofKind(Category::KIND_THEMATIQUE)->active()->ordered()->get(['id', 'label'])
            ->map(fn (Category $category): array => ['label' => (string) $category->label, 'count' => (int) ($counts[$category->getKey()] ?? 0)])
            ->filter(fn (array $row): bool => ! ContentRules::faqBoundsOk($row['count']))
            ->values()->all();

        return ['home' => $home, 'home_ok' => $home >= 3 && $home <= 6, 'categories' => $outside];
    }

    /**
     * @return Collection<int, Category>
     */
    #[Computed]
    public function categoryOptions(): Collection
    {
        return Category::query()->ordered()->get(['id', 'kind', 'label', 'slug']);
    }

    /**
     * @return Collection<int, Thematique>
     */
    #[Computed]
    public function thematiqueOptions(): Collection
    {
        return Thematique::query()->orderBy('title')->get(['id', 'title']);
    }

    public function render(): View
    {
        $rows = match ($this->tab) {
            'thematiques' => $this->paginateQuery($this->thematiqueQuery()),
            'faq' => $this->paginateQuery($this->faqQuery()),
            'categories' => $this->paginateQuery($this->categoryQuery()),
            default => $this->paginateQuery($this->resourceQuery()),
        };
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.content.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $this->formatter(),
            'tabs' => $this->tabs(),
            'kinds' => ResourceKind::cases(),
            'usage' => $this->tab === 'categories' ? $this->categoryUsage($rows->getCollection()) : [],
        ])->layout('components.layouts.admin', [
            'title' => __('admin_content.title'),
            'adminPage' => 'contenu',
        ]);
    }

    /** @return Builder<resource> */
    private function resourceQuery(): Builder
    {
        return Resource::query()->with('category')
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status === 'publie' ? ContentStatus::Published : ContentStatus::Draft))
            ->when($this->category !== '', fn (Builder $q) => $q->where('category_id', (int) $this->category))
            ->when(ResourceKind::tryFrom($this->kind) !== null, fn (Builder $q) => $q->where('kind', $this->kind));
    }

    /** @return Builder<Thematique> */
    private function thematiqueQuery(): Builder
    {
        return Thematique::query()->with('category')
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status === 'publie' ? ContentStatus::Published : ContentStatus::Draft))
            ->when($this->category !== '', fn (Builder $q) => $q->where('category_id', (int) $this->category));
    }

    /** @return Builder<Faq> */
    private function faqQuery(): Builder
    {
        return Faq::query()->with('category')
            ->withCount(['feedback as helpful_count' => fn (Builder $q) => $q->where('helpful', true), 'feedback as unhelpful_count' => fn (Builder $q) => $q->where('helpful', false)])
            ->when($this->status !== '', fn (Builder $q) => $q->where('is_published', $this->status === 'publie'))
            ->when($this->category !== '', fn (Builder $q) => $q->where('category_id', (int) $this->category));
    }

    /** @return Builder<Category> */
    private function categoryQuery(): Builder
    {
        return Category::query()->when(in_array($this->kind, Category::KINDS, true), fn (Builder $q) => $q->where('kind', $this->kind));
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return array<int, int>
     */
    private function categoryUsage(Collection $categories): array
    {
        return $categories->mapWithKeys(fn (Category $category): array => [(int) $category->getKey() => ManageCategories::usage($category)])->all();
    }

    /**
     * Valeurs vides du formulaire → null (les règles `nullable` ne les nettoient pas).
     *
     * @param  array<string, mixed>  $form
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    protected function blankToNull(array $form, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($form[$key]) && is_string($form[$key]) && trim($form[$key]) === '') {
                $form[$key] = null;
            }
        }

        return $form;
    }

    protected function actor(): User
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
