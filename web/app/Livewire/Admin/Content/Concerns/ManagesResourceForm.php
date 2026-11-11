<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content\Concerns;

use App\Application\Admin\Content\ContentRules;
use App\Application\Admin\Content\ManageResources;
use App\Application\Admin\Content\ResourceUploads;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\Resource;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Onglet Ressources de /admin/contenu (P12-03) : modale de création / modification, publier / brouillon, suppression.
 * Les fichiers (illustration, image Open Graph, fichier, sous-titres) passent par ContentUploads (MediaUploader).
 */
trait ManagesResourceForm
{
    #[Locked]
    public ?int $resourceId = null;

    /** @var array<string, mixed> */
    public array $resourceForm = [];

    public mixed $resourceImage = null;

    public mixed $resourceOg = null;

    public mixed $resourceFile = null;

    public mixed $resourceSubtitles = null;

    /** @return array<string, mixed> */
    protected function blankResource(): array
    {
        return [
            'title' => '', 'kind' => ResourceKind::Sheet->value, 'category_id' => '', 'thematique_id' => '', 'slug' => '',
            'summary' => '', 'body' => '', 'external_url' => '', 'duration_minutes' => '', 'is_offline_available' => false,
            'transcript' => '', 'status' => ContentStatus::Draft->value, 'meta_title' => '', 'meta_description' => '',
        ];
    }

    public function newResource(): void
    {
        $this->authorize('create', Resource::class);
        $this->resetResourceForm();
        $this->resourceForm = $this->blankResource();
        $this->openModal('xmContentResource');
    }

    public function editResource(int $id): void
    {
        $resource = Resource::query()->findOrFail($id);
        $this->authorize('update', $resource);
        $this->resetResourceForm();
        $this->resourceId = (int) $resource->getKey();
        $this->resourceForm = [
            'title' => (string) $resource->title, 'kind' => $resource->kind->value, 'category_id' => (string) ($resource->category_id ?? ''),
            'thematique_id' => (string) ($resource->thematique_id ?? ''), 'slug' => (string) $resource->slug, 'summary' => (string) $resource->summary,
            'body' => (string) $resource->body, 'external_url' => (string) $resource->external_url,
            'duration_minutes' => (string) ($resource->duration_minutes ?? ''), 'is_offline_available' => (bool) $resource->is_offline_available,
            'transcript' => (string) $resource->transcript,
            'status' => $resource->status === ContentStatus::Published ? ContentStatus::Published->value : ContentStatus::Draft->value,
            'meta_title' => (string) $resource->meta_title, 'meta_description' => (string) $resource->meta_description,
        ];
        $this->openModal('xmContentResource');
    }

    public function resetResourceForm(): void
    {
        $this->reset('resourceId', 'resourceImage', 'resourceOg', 'resourceFile', 'resourceSubtitles');
        $this->resourceForm = $this->blankResource();
        $this->resetErrorBag();
        unset($this->editedResource);
    }

    #[Computed]
    public function editedResource(): ?Resource
    {
        return $this->resourceId === null ? null : Resource::query()->find($this->resourceId);
    }

    public function saveResource(ManageResources $manage): void
    {
        $resource = $this->resourceId === null ? null : Resource::query()->findOrFail($this->resourceId);
        $resource === null ? $this->authorize('create', Resource::class) : $this->authorize('update', $resource);

        $data = $this->validateForm(
            'resourceForm',
            $this->blankToNull($this->resourceForm, ['category_id', 'thematique_id', 'slug', 'body', 'external_url', 'duration_minutes', 'transcript', 'meta_title', 'meta_description']),
            ContentRules::resource($this->resourceId),
            (array) __('admin_content.resources.fields'),
        );

        try {
            $saved = $manage->save($resource, $data, new ResourceUploads(
                image: $this->resourceImage,
                og: $this->resourceOg,
                file: $this->resourceFile,
                subtitles: $this->resourceSubtitles,
            ), $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('resourceForm.status', $refused->userMessage());
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->closeModal('xmContentResource');
        $this->resetResourceForm();
        unset($this->kpis);
        $this->toast((string) __($saved->wasRecentlyCreated ? 'admin_content.resources.toasts.created' : 'admin_content.resources.toasts.saved', ['title' => $saved->title]));
    }

    public function toggleResource(int $id, ManageResources $manage): void
    {
        $resource = Resource::query()->findOrFail($id);
        $this->authorize('publish', $resource);
        $publish = $resource->status !== ContentStatus::Published;

        try {
            $manage->setPublished($resource, $publish, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->kpis);
        $this->toast((string) __($publish ? 'admin_content.toasts.published' : 'admin_content.toasts.unpublished', ['title' => $resource->title]));
    }

    public function deleteResource(int $id, ManageResources $manage): void
    {
        $resource = Resource::query()->findOrFail($id);
        $this->authorize('delete', $resource);
        $manage->delete($resource, $this->actor());
        unset($this->kpis);
        $this->toast((string) __('admin_content.toasts.deleted', ['title' => $resource->title]), 'warning');
    }
}
