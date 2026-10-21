{{-- Modale ressource (P12-03) : champs selon le type (fichier, sous-titres, transcription), méta SEO, téléversements. --}}
@php
    $resKind = \App\Enums\ResourceKind::tryFrom((string) ($resourceForm['kind'] ?? '')) ?? \App\Enums\ResourceKind::Sheet;
    $editedResource = $this->editedResource;
    $metaTitleLength = mb_strlen((string) ($resourceForm['meta_title'] ?? ''));
    $metaDescriptionLength = mb_strlen((string) ($resourceForm['meta_description'] ?? ''));
@endphp
<x-admin.modal id="xmContentResource" size="xl" heading="h2" dirty-guard :title="$resourceId ? __('admin_content.resources.edit_title') : __('admin_content.resources.new')"
               x-data x-on:hidden-bs-modal.dot="$wire.resetResourceForm()">
    <form id="xmContentResourceForm" wire:submit="saveResource" novalidate>
        @error('resourceForm.status')<div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div>{{ $message }}</div></div>@enderror
        <div class="row g-3">
            <div class="col-12">
                <x-field name="resourceForm.title" :label="__('admin_content.resources.fields.title')" wire:model.blur="resourceForm.title" maxlength="200" autocomplete="off" />
            </div>
            <div class="col-md-4">
                <x-field name="resourceForm.kind" type="select" :label="__('admin_content.resources.fields.kind')" wire:model.live="resourceForm.kind"
                         :options="collect(\App\Enums\ResourceKind::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()" />
            </div>
            <div class="col-md-4">
                <x-field name="resourceForm.category_id" type="select" optional :label="__('admin_content.resources.fields.category_id')" wire:model.blur="resourceForm.category_id"
                         :options="['' => __('admin_content.none')] + $this->categoryOptions->whereIn('kind', ['resource', 'thematique'])->pluck('label', 'id')->all()" />
            </div>
            <div class="col-md-4">
                <x-field name="resourceForm.thematique_id" type="select" optional :label="__('admin_content.resources.fields.thematique_id')" wire:model.blur="resourceForm.thematique_id"
                         :options="['' => __('admin_content.none')] + $this->thematiqueOptions->pluck('title', 'id')->all()" />
            </div>
            <div class="col-12">
                <x-field name="resourceForm.summary" type="textarea" :label="__('admin_content.resources.fields.summary')" wire:model.blur="resourceForm.summary" rows="3" maxlength="500" />
            </div>
            <div class="col-12">
                <x-field name="resourceForm.body" type="textarea" optional :label="__('admin_content.resources.fields.body')" wire:model.blur="resourceForm.body" rows="8" :hint="__('admin_content.html_help')" />
            </div>

            @if ($resKind === \App\Enums\ResourceKind::Link)
                <div class="col-12">
                    <x-field name="resourceForm.external_url" type="url" :label="__('admin_content.resources.fields.external_url')" wire:model.blur="resourceForm.external_url" maxlength="500" inputmode="url" placeholder="https://" :hint="__('admin_content.resources.external_help')" />
                </div>
            @endif

            @if ($resKind->hasFile())
                <div class="col-12">
                    <label class="form-label fw-semibold" for="xmResourceFile">{{ __('admin_content.resources.fields.file') }}</label>
                    <input class="form-control @error('resourceFile') is-invalid @enderror" type="file" id="xmResourceFile" wire:model="resourceFile" aria-describedby="xmResourceFileHelp">
                    <div class="form-text" id="xmResourceFileHelp">{{ __('admin_content.resources.file_help.'.$resKind->value) }}
                        @if ($editedResource?->file_path) · {{ __('admin_content.resources.current_file', ['size' => $editedResource->file_size ? \App\Support\LocaleFormatter::make()->fileSize((int) $editedResource->file_size) : '—']) }}@endif</div>
                    @error('resourceFile')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div wire:loading wire:target="resourceFile" class="small text-secondary" role="status">{{ __('admin_content.uploading') }}</div>
                </div>
            @endif

            @if (in_array($resKind, [\App\Enums\ResourceKind::Video, \App\Enums\ResourceKind::Audio], true))
                <div class="col-12">
                    <div class="alert alert-info small mb-3" role="note"><i class="bi bi-badge-cc me-1" aria-hidden="true"></i>{{ __('admin_content.resources.accessibility_help') }}</div>
                    <label class="form-label fw-semibold" for="xmResourceSubtitles">{{ __('admin_content.resources.fields.subtitles') }}</label>
                    <input class="form-control @error('resourceSubtitles') is-invalid @enderror" type="file" id="xmResourceSubtitles" accept=".vtt,text/vtt" wire:model="resourceSubtitles" aria-describedby="xmResourceSubtitlesHelp">
                    <div class="form-text" id="xmResourceSubtitlesHelp">{{ __('admin_content.resources.subtitles_help') }}
                        @if ($editedResource?->subtitles_path) · <x-badge color="success" soft icon="check2">{{ __('admin_content.resources.has_subtitles') }}</x-badge>@endif</div>
                    @error('resourceSubtitles')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <x-field name="resourceForm.transcript" type="textarea" optional :label="__('admin_content.resources.fields.transcript')" wire:model.blur="resourceForm.transcript" rows="6" :hint="__('admin_content.resources.transcript_help')" />
                </div>
            @endif

            <div class="col-md-6">
                <x-field name="resourceForm.duration_minutes" type="number" optional :label="__('admin_content.resources.fields.duration_minutes')" wire:model.blur="resourceForm.duration_minutes" inputmode="numeric" />
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <x-field name="resourceForm.is_offline_available" type="switch" :label="__('admin_content.resources.fields.is_offline_available')" wire:model="resourceForm.is_offline_available" :hint="__('admin_content.resources.offline_help')" />
            </div>

            <div class="col-12">
                <label class="form-label fw-semibold" for="xmResourceImage">{{ __('admin_content.resources.fields.image') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
                <input class="form-control @error('resourceImage') is-invalid @enderror" type="file" id="xmResourceImage" accept="image/jpeg,image/png,image/webp" wire:model="resourceImage" aria-describedby="xmResourceImageHelp">
                <div class="form-text" id="xmResourceImageHelp">{{ __('admin_content.image_help') }}</div>
                @error('resourceImage')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>

            <fieldset class="col-12 border rounded p-3">
                <legend class="h6 fw-bold float-none w-auto px-2 mb-0">{{ __('admin_content.seo.title') }}</legend>
                <div class="row g-3">
                    <div class="col-12">
                        <x-field name="resourceForm.slug" optional :label="__('admin_content.seo.slug')" wire:model.blur="resourceForm.slug" maxlength="120" autocomplete="off" :hint="__('admin_content.seo.slug_help')" />
                    </div>
                    <div class="col-12">
                        <x-field name="resourceForm.meta_title" optional :label="__('admin_content.seo.meta_title')" wire:model.blur="resourceForm.meta_title" maxlength="50" autocomplete="off"
                                 :hint="__('admin_content.seo.meta_title_help', ['count' => $metaTitleLength])" />
                    </div>
                    <div class="col-12">
                        <x-field name="resourceForm.meta_description" type="textarea" optional :label="__('admin_content.seo.meta_description')" wire:model.blur="resourceForm.meta_description" rows="3" maxlength="160"
                                 :hint="__('admin_content.seo.meta_description_help', ['count' => $metaDescriptionLength])" />
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="xmResourceOg">{{ __('admin_content.seo.og_image') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
                        <input class="form-control @error('resourceOg') is-invalid @enderror" type="file" id="xmResourceOg" accept="image/jpeg,image/png,image/webp" wire:model="resourceOg" aria-describedby="xmResourceOgHelp">
                        <div class="form-text" id="xmResourceOgHelp">{{ __('admin_content.seo.og_help') }}</div>
                        @error('resourceOg')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    @if ($resourceId && ($history = app(\App\Application\Admin\Content\SlugHistory::class)->history('resource', $resourceId)) !== [])
                        <div class="col-12 small text-secondary">{{ __('admin_content.seo.history') }} @foreach ($history as $old)<code>{{ $old }}</code> @endforeach</div>
                    @endif
                </div>
            </fieldset>

            <div class="col-md-6">
                <x-field name="resourceForm.status" type="select" :label="__('admin_content.columns.status')" wire:model="resourceForm.status"
                         :options="['brouillon' => __('admin_content.status.draft'), 'publie' => __('admin_content.status.published')]" :hint="__('admin_content.status_help')" />
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="xmContentResourceForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveResource,resourceImage,resourceOg,resourceFile,resourceSubtitles"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_content.save') }}</button>
    </x-slot:footer>
</x-admin.modal>
