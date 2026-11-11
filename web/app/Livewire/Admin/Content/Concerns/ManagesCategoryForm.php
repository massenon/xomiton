<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content\Concerns;

use App\Application\Admin\Content\ContentRules;
use App\Application\Admin\Content\ManageCategories;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Models\Category;
use Livewire\Attributes\Locked;

/**
 * Onglet Catégories de /admin/contenu (P12-03) : modale, ordre, actif / inactif, suppression (jamais d'une catégorie utilisée).
 * Les catégories sont gérées avec les thématiques : permission `thematiques.manage`.
 */
trait ManagesCategoryForm
{
    #[Locked]
    public ?int $categoryId = null;

    /** @var array<string, mixed> */
    public array $categoryForm = [];

    /** @return array<string, mixed> */
    protected function blankCategory(): array
    {
        return ['kind' => Category::KIND_THEMATIQUE, 'slug' => '', 'label' => '', 'icon' => '', 'color' => '', 'is_active' => true];
    }

    private function authorizeCategories(): void
    {
        abort_unless($this->actor()->can('thematiques.manage'), 403);
    }

    public function newCategory(): void
    {
        $this->authorizeCategories();
        $this->resetCategoryForm();
        $this->openModal('xmContentCategory');
    }

    public function editCategory(int $id): void
    {
        $this->authorizeCategories();
        $category = Category::query()->findOrFail($id);
        $this->resetCategoryForm();
        $this->categoryId = (int) $category->getKey();
        $this->categoryForm = [
            'kind' => $category->kind, 'slug' => (string) $category->slug, 'label' => (string) $category->label,
            'icon' => (string) $category->icon, 'color' => (string) $category->color, 'is_active' => (bool) $category->is_active,
        ];
        $this->openModal('xmContentCategory');
    }

    public function resetCategoryForm(): void
    {
        $this->reset('categoryId');
        $this->categoryForm = $this->blankCategory();
        $this->resetErrorBag();
    }

    public function saveCategory(ManageCategories $manage): void
    {
        $this->authorizeCategories();
        $category = $this->categoryId === null ? null : Category::query()->findOrFail($this->categoryId);

        // Type et slug sont figés après la création : le formulaire modifié à la main ne les change pas.
        if ($category !== null) {
            $this->categoryForm['kind'] = $category->kind;
            $this->categoryForm['slug'] = $category->slug;
        }

        $form = $this->blankToNull($this->categoryForm, ['icon', 'color']);
        $data = $this->validateForm('categoryForm', $form, ContentRules::category($this->categoryId, (string) ($form['kind'] ?? '')), (array) __('admin_content.categories.fields'));

        $saved = $manage->save($category, $data, $this->actor());
        $this->closeModal('xmContentCategory');
        $this->resetCategoryForm();
        unset($this->kpis);
        $this->toast((string) __($saved->wasRecentlyCreated ? 'admin_content.categories.toasts.created' : 'admin_content.categories.toasts.saved', ['label' => $saved->label]));
    }

    public function moveCategory(int $id, string $direction, ManageCategories $manage): void
    {
        $this->authorizeCategories();
        $manage->move(Category::query()->findOrFail($id), $direction === 'up' ? -1 : 1, $this->actor());
    }

    public function toggleCategory(int $id, ManageCategories $manage): void
    {
        $this->authorizeCategories();
        $category = Category::query()->findOrFail($id);
        $manage->save($category, [...$category->only(['label', 'icon', 'color']), 'is_active' => ! $category->is_active], $this->actor());
        unset($this->kpis);
        $this->toast((string) __($category->is_active ? 'admin_content.categories.toasts.activated' : 'admin_content.categories.toasts.deactivated', ['label' => $category->label]));
    }

    public function deleteCategory(int $id, ManageCategories $manage): void
    {
        $this->authorizeCategories();
        $category = Category::query()->findOrFail($id);

        try {
            $manage->delete($category, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->kpis);
        $this->toast((string) __('admin_content.categories.toasts.deleted', ['label' => $category->label]), 'warning');
    }
}
