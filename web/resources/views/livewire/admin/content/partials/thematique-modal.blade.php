{{-- Modale thématique (P12-03). --}}
@php
    $metaTitleLength = mb_strlen((string) ($thematiqueForm['meta_title'] ?? ''));
    $metaDescriptionLength = mb_strlen((string) ($thematiqueForm['meta_description'] ?? ''));
@endphp
<x-admin.modal id="xmContentThematique" size="xl" heading="h2" dirty-guard :title="$thematiqueId ? __('admin_content.thematiques.edit_title') : __('admin_content.thematiques.new')"
               x-data x-on:hidden-bs-modal.dot="$wire.resetThematiqueForm()">
    <form id="xmContentThematiqueForm" wire:submit="saveThematique" novalidate>
        @error('thematiqueForm.status')<div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div>{{ $message }}</div></div>@enderror
        <div class="row g-3">
            <div class="col-md-8">
                <x-field name="thematiqueForm.title" :label="__('admin_content.thematiques.fields.title')" wire:model.blur="thematiqueForm.title" maxlength="200" autocomplete="off" />
            </div>
            <div class="col-md-4">
                <x-field name="thematiqueForm.category_id" type="select" :label="__('admin_content.thematiques.fields.category_id')" wire:model.blur="thematiqueForm.category_id"
                         :options="['' => __('admin_content.choose')] + $this->categoryOptions->where('kind', 'thematique')->pluck('label', 'id')->all()" />
            </div>
            <div class="col-12">
                <x-field name="thematiqueForm.summary" type="textarea" :label="__('admin_content.thematiques.fields.summary')" wire:model.blur="thematiqueForm.summary" rows="3" maxlength="500" />
            </div>
            <div class="col-12">
                <x-field name="thematiqueForm.body" type="textarea" optional :label="__('admin_content.thematiques.fields.body')" wire:model.blur="thematiqueForm.body" rows="10" :hint="__('admin_content.html_help')" />
            </div>
            <div class="col-md-8">
                <x-field name="thematiqueForm.content_warning" optional :label="__('admin_content.thematiques.fields.content_warning')" wire:model.blur="thematiqueForm.content_warning" maxlength="255" :hint="__('admin_content.thematiques.warning_help')" />
            </div>
            <div class="col-md-4">
                <x-field name="thematiqueForm.reading_minutes" type="number" :label="__('admin_content.thematiques.fields.reading_minutes')" wire:model.blur="thematiqueForm.reading_minutes" inputmode="numeric" />
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold" for="xmThematiqueCover">{{ __('admin_content.thematiques.fields.cover') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
                <input class="form-control @error('thematiqueCover') is-invalid @enderror" type="file" id="xmThematiqueCover" accept="image/jpeg,image/png,image/webp" wire:model="thematiqueCover" aria-describedby="xmThematiqueCoverHelp">
                <div class="form-text" id="xmThematiqueCoverHelp">{{ __('admin_content.image_help') }}</div>
                @error('thematiqueCover')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <fieldset class="col-12 border rounded p-3">
                <legend class="h6 fw-bold float-none w-auto px-2 mb-0">{{ __('admin_content.seo.title') }}</legend>
                <div class="row g-3">
                    <div class="col-12">
                        <x-field name="thematiqueForm.slug" optional :label="__('admin_content.seo.slug')" wire:model.blur="thematiqueForm.slug" maxlength="120" autocomplete="off" :hint="__('admin_content.seo.slug_help')" />
                    </div>
                    <div class="col-12">
                        <x-field name="thematiqueForm.meta_title" optional :label="__('admin_content.seo.meta_title')" wire:model.blur="thematiqueForm.meta_title" maxlength="50" autocomplete="off"
                                 :hint="__('admin_content.seo.meta_title_help', ['count' => $metaTitleLength])" />
                    </div>
                    <div class="col-12">
                        <x-field name="thematiqueForm.meta_description" type="textarea" optional :label="__('admin_content.seo.meta_description')" wire:model.blur="thematiqueForm.meta_description" rows="3" maxlength="160"
                                 :hint="__('admin_content.seo.meta_description_help', ['count' => $metaDescriptionLength])" />
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold" for="xmThematiqueOg">{{ __('admin_content.seo.og_image') }} <span class="small text-secondary fw-normal">{{ __('components.field.optional') }}</span></label>
                        <input class="form-control @error('thematiqueOg') is-invalid @enderror" type="file" id="xmThematiqueOg" accept="image/jpeg,image/png,image/webp" wire:model="thematiqueOg" aria-describedby="xmThematiqueOgHelp">
                        <div class="form-text" id="xmThematiqueOgHelp">{{ __('admin_content.seo.og_help') }}</div>
                        @error('thematiqueOg')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    @if ($thematiqueId && ($history = app(\App\Application\Admin\Content\SlugHistory::class)->history('thematique', $thematiqueId)) !== [])
                        <div class="col-12 small text-secondary">{{ __('admin_content.seo.history') }} @foreach ($history as $old)<code>{{ $old }}</code> @endforeach</div>
                    @endif
                </div>
            </fieldset>
            <div class="col-md-6">
                <x-field name="thematiqueForm.status" type="select" :label="__('admin_content.columns.status')" wire:model="thematiqueForm.status"
                         :options="['brouillon' => __('admin_content.status.draft'), 'publie' => __('admin_content.status.published')]" :hint="__('admin_content.status_help')" />
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="xmContentThematiqueForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveThematique,thematiqueCover,thematiqueOg"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_content.save') }}</button>
    </x-slot:footer>
</x-admin.modal>
