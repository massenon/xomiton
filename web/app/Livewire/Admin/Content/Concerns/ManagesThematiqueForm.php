<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content\Concerns;

use App\Application\Admin\Content\ContentRules;
use App\Application\Admin\Content\ManageThematiques;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Models\Thematique;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Onglet Thématiques de /admin/contenu (P12-03) : modale de création / modification, publier / brouillon, suppression.
 */
trait ManagesThematiqueForm
{
    #[Locked]
    public ?int $thematiqueId = null;

    /** @var array<string, mixed> */
    public array $thematiqueForm = [];

    public mixed $thematiqueCover = null;

    public mixed $thematiqueOg = null;

    /** @return array<string, mixed> */
    protected function blankThematique(): array
    {
        return [
            'title' => '', 'category_id' => '', 'slug' => '', 'summary' => '', 'body' => '', 'content_warning' => '',
            'reading_minutes' => '3', 'status' => ContentStatus::Draft->value, 'meta_title' => '', 'meta_description' => '',
        ];
    }

    public function newThematique(): void
    {
        $this->authorize('create', Thematique::class);
        $this->resetThematiqueForm();
        $this->openModal('xmContentThematique');
    }

    public function editThematique(int $id): void
    {
        $thematique = Thematique::query()->findOrFail($id);
        $this->authorize('update', $thematique);
        $this->resetThematiqueForm();
        $this->thematiqueId = (int) $thematique->getKey();
        $this->thematiqueForm = [
            'title' => (string) $thematique->title, 'category_id' => (string) $thematique->category_id, 'slug' => (string) $thematique->slug,
            'summary' => (string) $thematique->summary, 'body' => (string) $thematique->body, 'content_warning' => (string) $thematique->content_warning,
            'reading_minutes' => (string) $thematique->reading_minutes,
            'status' => $thematique->status === ContentStatus::Published ? ContentStatus::Published->value : ContentStatus::Draft->value,
            'meta_title' => (string) $thematique->meta_title, 'meta_description' => (string) $thematique->meta_description,
        ];
        $this->openModal('xmContentThematique');
    }

    public function resetThematiqueForm(): void
    {
        $this->reset('thematiqueId', 'thematiqueCover', 'thematiqueOg');
        $this->thematiqueForm = $this->blankThematique();
        $this->resetErrorBag();
        unset($this->editedThematique);
    }

    #[Computed]
    public function editedThematique(): ?Thematique
    {
        return $this->thematiqueId === null ? null : Thematique::query()->find($this->thematiqueId);
    }

    public function saveThematique(ManageThematiques $manage): void
    {
        $thematique = $this->thematiqueId === null ? null : Thematique::query()->findOrFail($this->thematiqueId);
        $thematique === null ? $this->authorize('create', Thematique::class) : $this->authorize('update', $thematique);

        $data = $this->validateForm(
            'thematiqueForm',
            $this->blankToNull($this->thematiqueForm, ['slug', 'body', 'content_warning', 'meta_title', 'meta_description']),
            ContentRules::thematique($this->thematiqueId),
            (array) __('admin_content.thematiques.fields'),
        );

        try {
            $saved = $manage->save($thematique, $data, $this->thematiqueCover, $this->thematiqueOg, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('thematiqueForm.status', $refused->userMessage());
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->closeModal('xmContentThematique');
        $this->resetThematiqueForm();
        unset($this->kpis);
        $this->toast((string) __($saved->wasRecentlyCreated ? 'admin_content.thematiques.toasts.created' : 'admin_content.thematiques.toasts.saved', ['title' => $saved->title]));
    }

    public function toggleThematique(int $id, ManageThematiques $manage): void
    {
        $thematique = Thematique::query()->findOrFail($id);
        $this->authorize('publish', $thematique);
        $publish = $thematique->status !== ContentStatus::Published;

        try {
            $manage->setPublished($thematique, $publish, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->kpis);
        $this->toast((string) __($publish ? 'admin_content.toasts.published' : 'admin_content.toasts.unpublished', ['title' => $thematique->title]));
    }

    public function deleteThematique(int $id, ManageThematiques $manage): void
    {
        $thematique = Thematique::query()->findOrFail($id);
        $this->authorize('delete', $thematique);
        $manage->delete($thematique, $this->actor());
        unset($this->kpis);
        $this->toast((string) __('admin_content.toasts.deleted', ['title' => $thematique->title]), 'warning');
    }
}
