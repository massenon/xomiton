{{--
    Modales des modules du manuel du pair animateur (onglet « Manuel » de /admin/ccc, partiel de index.blade.php) :
    module (création, titre, description, durée), chapitres (liste ordonnée, ajout, texte, retrait, ordre au clavier) et suppression.
    Chaque champ : étiquette, aide et erreur reliées par aria-describedby, aria-invalid sur erreur ; l'ordre se change par de vrais
    boutons « Monter » / « Descendre » (annonce aria-live, focus conservé).
--}}
@php
    $chapterCount = count($this->openedChapters);
@endphp

{{-- Module : création et modification --}}
<x-admin.modal id="xmCccModule" size="lg" heading="h2" :title="$moduleId === null ? __('admin_ccc.module.create_title') : __('admin_ccc.module.edit_title', ['title' => $this->openedModule?->title ?? ''])" x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccToolsTitle'))?.focus()">
    <form id="xmCccModuleForm" wire:submit="saveModule" novalidate>
        @error('module')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        <div class="mb-3">
            <label class="form-label fw-semibold" for="xmModuleTitle">{{ __('admin_ccc.module.fields.title') }}</label>
            <input class="form-control @error('moduleTitle') is-invalid @enderror" type="text" id="xmModuleTitle" data-xm-autofocus wire:model="moduleTitle" maxlength="{{ \App\Application\Admin\Content\ManageCccModules::TITLE_MAX }}" required autocomplete="off"
                   aria-describedby="xmModuleTitleHelp @error('moduleTitle') xmModuleTitleError @enderror" @error('moduleTitle') aria-invalid="true" @enderror>
            <div class="form-text" id="xmModuleTitleHelp">{{ __('admin_ccc.module.title_help', ['max' => \App\Application\Admin\Content\ManageCccModules::TITLE_MAX]) }}</div>
            @error('moduleTitle')<div class="invalid-feedback d-block" id="xmModuleTitleError">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="xmModuleDescription">{{ __('admin_ccc.module.fields.description') }}</label>
            <textarea class="form-control @error('moduleDescription') is-invalid @enderror" id="xmModuleDescription" rows="3" wire:model="moduleDescription" maxlength="{{ \App\Application\Admin\Content\ManageCccModules::DESCRIPTION_MAX }}"
                      aria-describedby="xmModuleDescriptionHelp @error('moduleDescription') xmModuleDescriptionError @enderror" @error('moduleDescription') aria-invalid="true" @enderror></textarea>
            <div class="form-text" id="xmModuleDescriptionHelp">{{ __('admin_ccc.module.description_help', ['max' => \App\Application\Admin\Content\ManageCccModules::DESCRIPTION_MAX]) }}</div>
            @error('moduleDescription')<div class="invalid-feedback d-block" id="xmModuleDescriptionError">{{ $message }}</div>@enderror
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="xmModuleDuration">{{ __('admin_ccc.module.fields.duration') }}</label>
            <input class="form-control @error('moduleDuration') is-invalid @enderror" type="number" id="xmModuleDuration" wire:model="moduleDuration" min="{{ \App\Application\Admin\Content\ManageCccModules::DURATION_MIN }}" max="{{ \App\Application\Admin\Content\ManageCccModules::DURATION_MAX }}" step="1" inputmode="numeric" autocomplete="off"
                   aria-describedby="xmModuleDurationHelp @error('moduleDuration') xmModuleDurationError @enderror" @error('moduleDuration') aria-invalid="true" @enderror>
            <div class="form-text" id="xmModuleDurationHelp">{{ __('admin_ccc.module.duration_help', ['min' => \App\Application\Admin\Content\ManageCccModules::DURATION_MIN, 'max' => \App\Application\Admin\Content\ManageCccModules::DURATION_MAX]) }}</div>
            @error('moduleDuration')<div class="invalid-feedback d-block" id="xmModuleDurationError">{{ $message }}</div>@enderror
        </div>
        @if ($moduleId !== null)
            <p class="small text-secondary mb-0">{{ __('admin_ccc.module.key', ['key' => $this->openedModule?->key ?? '']) }}</p>
        @endif
    </form>
    <x-slot:footer>
        <button type="submit" form="xmCccModuleForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveModule"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $moduleId === null ? __('admin_ccc.module.create') : __('admin_ccc.module.save') }}</button>
    </x-slot:footer>
</x-admin.modal>

{{-- Chapitres d'un module : liste ordonnée, ajout, texte, retrait, ordre --}}
<x-admin.modal id="xmCccChapters" size="xl" heading="h2" :title="__('admin_ccc.module.chapters_title', ['title' => $this->openedModule?->title ?? ''])" x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccToolsTitle'))?.focus()" x-on:xm-ccc-focus.window="$nextTick(() => setTimeout(() => document.getElementById($event.detail.id)?.focus(), 30))">
    <p class="small text-secondary">{{ __('admin_ccc.module.chapters_intro', ['max' => \App\Application\Admin\Content\ManageCccModules::CHAPTER_MAX, 'count' => \App\Application\Admin\Content\ManageCccModules::CHAPTERS_MAX]) }}</p>
    <div class="visually-hidden" role="status" aria-live="polite">{{ $announce }}</div>
    @error('chapters')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    <form id="xmCccChaptersForm" wire:submit="saveChapters" novalidate>
        <ol class="list-unstyled mb-0" id="xmCccChaptersList" tabindex="-1" aria-label="{{ __('admin_ccc.module.chapters_list_label') }}">
            @forelse ($this->openedChapters as $index => $chapter)
                <li class="mb-3" wire:key="chapter-{{ $index }}">
                    <fieldset class="border rounded p-3">
                        <legend class="h6 float-none w-auto px-1 mb-2">{{ __('admin_ccc.module.chapter_legend', ['number' => $index + 1, 'total' => $chapterCount]) }}</legend>
                        <label class="form-label fw-semibold" for="xmCccChapterText{{ $index }}">{{ __('admin_ccc.module.chapter_label') }}</label>
                        <input class="form-control @error('chapterEdits.'.$index) is-invalid @enderror" type="text" id="xmCccChapterText{{ $index }}" wire:model.blur="chapterEdits.{{ $index }}" maxlength="{{ \App\Application\Admin\Content\ManageCccModules::CHAPTER_MAX }}" required autocomplete="off"
                               aria-describedby="xmCccChapterHelp{{ $index }} @error('chapterEdits.'.$index) xmCccChapterError{{ $index }} @enderror" @error('chapterEdits.'.$index) aria-invalid="true" @enderror>
                        <div class="form-text" id="xmCccChapterHelp{{ $index }}">{{ __('admin_ccc.module.chapter_help', ['max' => \App\Application\Admin\Content\ManageCccModules::CHAPTER_MAX]) }}</div>
                        @error('chapterEdits.'.$index)<div class="invalid-feedback d-block" id="xmCccChapterError{{ $index }}">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="xmCccChapterUp{{ $index }}" wire:click="moveChapter({{ $index }}, -1)" @disabled($index === 0)><i class="bi bi-arrow-up me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.chapter_up') }}<span class="visually-hidden"> {{ __('admin_ccc.module.chapter_legend', ['number' => $index + 1, 'total' => $chapterCount]) }}</span></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="xmCccChapterDown{{ $index }}" wire:click="moveChapter({{ $index }}, 1)" @disabled($index === $chapterCount - 1)><i class="bi bi-arrow-down me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.chapter_down') }}<span class="visually-hidden"> {{ __('admin_ccc.module.chapter_legend', ['number' => $index + 1, 'total' => $chapterCount]) }}</span></button>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="xmCccChapterRemove{{ $index }}" wire:click="askRemoveChapter({{ $index }})"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.chapter_remove') }}<span class="visually-hidden"> {{ __('admin_ccc.module.chapter_legend', ['number' => $index + 1, 'total' => $chapterCount]) }}</span></button>
                        </div>
                        @if ($removeChapterIndex === $index)
                            <div class="alert alert-warning mt-3 mb-0" role="alert" x-data x-init="$nextTick(() => $el.querySelector('[data-xm-confirm]')?.focus())">
                                <p class="mb-2">{{ __('admin_ccc.module.chapter_remove_confirm', ['number' => $index + 1]) }}</p>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-danger" data-xm-confirm wire:click="removeChapter" wire:loading.attr="disabled" wire:target="removeChapter">{{ __('admin_ccc.module.chapter_remove_yes') }}</button>
                                    <button type="button" class="btn btn-sm btn-light" wire:click="cancelRemoveChapter">{{ __('admin_ccc.module.chapter_remove_no') }}</button>
                                </div>
                            </div>
                        @endif
                    </fieldset>
                </li>
            @empty
                <li class="text-secondary mb-3">{{ __('admin_ccc.module.chapters_empty') }}</li>
            @endforelse
        </ol>
    </form>

    <form id="xmCccAddChapterForm" class="border rounded p-3 bg-body-tertiary" wire:submit="addChapter" novalidate>
        <h3 class="h6 fw-bold">{{ __('admin_ccc.module.chapter_add_title') }}</h3>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="xmCccNewChapter">{{ __('admin_ccc.module.chapter_new_label') }}</label>
            <input class="form-control @error('newChapter') is-invalid @enderror" type="text" id="xmCccNewChapter" data-xm-autofocus wire:model="newChapter" maxlength="{{ \App\Application\Admin\Content\ManageCccModules::CHAPTER_MAX }}" required autocomplete="off"
                   aria-describedby="xmCccNewChapterHelp @error('newChapter') xmCccNewChapterError @enderror" @error('newChapter') aria-invalid="true" @enderror>
            <div class="form-text" id="xmCccNewChapterHelp">{{ __('admin_ccc.module.chapter_help', ['max' => \App\Application\Admin\Content\ManageCccModules::CHAPTER_MAX]) }}</div>
            @error('newChapter')<div class="invalid-feedback d-block" id="xmCccNewChapterError">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn btn-outline-primary" wire:loading.attr="disabled" wire:target="addChapter"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.chapter_add') }}</button>
    </form>
    <x-slot:footer>
        <button type="submit" form="xmCccChaptersForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveChapters" @disabled($chapterCount === 0)><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.chapters_save') }}</button>
    </x-slot:footer>
</x-admin.modal>

{{-- Suppression d'un module --}}
<x-admin.modal id="xmCccModuleDelete" type="delete" size="md" heading="h2" :title="__('admin_ccc.module.delete_title', ['title' => $this->openedModule?->title ?? ''])" static x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccToolsTitle'))?.focus()">
    <p>{{ __('admin_ccc.module.delete_text') }}</p>
    @error('delete')<div class="alert alert-danger mb-0" role="alert">{{ $message }}</div>@enderror
    <x-slot:footer>
        <button type="button" class="btn btn-danger" wire:click="deleteModule" wire:loading.attr="disabled" wire:target="deleteModule"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('components.modal.delete_action') }}</button>
    </x-slot:footer>
</x-admin.modal>
