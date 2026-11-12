{{--
    Outils CCC (P12-04) : composant App\Livewire\Admin\Ccc\Index, gabarit admin.
    Ordre : introduction · KPI · onglets (Manuel, Boîtes à images, BD SSR, Conversations en BD, Lois) · cartes · modales (image, outil, planches, suppression).
    Un outil masqué disparaît des écrans membre et pair ; une BD ou une boîte à images se crée, se renomme, se supprime et gère
    ses planches (ajout, retrait, légende, texte alternatif obligatoire, ordre par boutons « Monter » / « Descendre » au clavier).
    Onglet Manuel : modules (création, titre, description, durée, ordre, suppression) et modale « Chapitres » (partiel modules-modals).
    Focus : à l'ouverture d'une modale il va sur le premier champ (data-xm-autofocus), à la fermeture il revient au bouton qui l'a ouverte.
--}}
@php
    $tabMeta = [
        'manuel' => ['label' => __('admin_ccc.tabs.manuel'), 'icon' => 'journal-richtext'],
        'boite_images' => ['label' => __('admin_ccc.tabs.boite_images'), 'icon' => 'images'],
        'bd' => ['label' => __('admin_ccc.tabs.bd'), 'icon' => 'chat-square-text'],
        'conversations' => ['label' => __('admin_ccc.tabs.conversations'), 'icon' => 'chat-square-quote'],
        'lois' => ['label' => __('admin_ccc.tabs.lois'), 'icon' => 'bank'],
    ];
    $visibleTabs = [];
    foreach ($tabs as $key) {
        $visibleTabs[$key] = $tabMeta[$key] + ['count' => $this->counts[$key]];
    }
    $format = \App\Support\LocaleFormatter::make();
    $fallbackImage = 'assets/images/xomiton/covers/og-default.jpg';
    $pagedKinds = [\App\Enums\CccKind::Comic, \App\Enums\CccKind::ImageBox];
    // Famille des textes de la modale ouverte (outil ouvert, sinon onglet courant) : `comic` ou `imagebox`.
    $copy = fn (string $key, array $replace = []) => __('admin_ccc.'.($this->modalKind() === \App\Enums\CccKind::ImageBox ? 'imagebox' : 'comic').'.'.$key, $replace);
    $tabKind = \App\Enums\CccKind::tryFrom($tab);
    $tabCopy = $tabKind === \App\Enums\CccKind::ImageBox ? 'imagebox' : 'comic';
    $isManualTab = $tab === 'manuel';
    $moduleTotal = $isManualTab ? count($this->tools) : 0;
@endphp
<div class="xm-ccc-admin">
    <p class="text-secondary mb-4 xm-prose">{{ __('admin_ccc.intro') }}</p>

    <x-admin.kpi id="xmCccKpis" :items="[
        ['label' => __('admin_ccc.kpi.total'), 'value' => $format->number($this->kpis['total']), 'icon' => 'collection-play'],
        ['label' => __('admin_ccc.kpi.published'), 'value' => $format->number($this->kpis['published']), 'icon' => 'eye', 'variant' => 'success'],
        ['label' => __('admin_ccc.kpi.hidden'), 'value' => $format->number($this->kpis['hidden']), 'icon' => 'eye-slash', 'variant' => 'warning'],
        ['label' => __('admin_ccc.kpi.images'), 'value' => $format->number($this->kpis['images']), 'icon' => 'image', 'variant' => 'violet'],
    ]" />

    <x-admin.tab-nav :tabs="$visibleTabs" :current="$tab" :label="__('admin_ccc.tabs_label')" />

    @if ($tab === 'conversations')
        {{-- P20 : conversations en BD (publier, ordonner, bulles, bibliothèque de scènes). --}}
        <livewire:admin.ccc.conversations :key="'ccc-conversations'" />
    @elseif ($tab === 'lois')
        @php($lawTotal = count($this->laws))
        <section aria-labelledby="xmCccLawsTitle" x-data x-on:xm-ccc-focus.window="$nextTick(() => setTimeout(() => document.getElementById($event.detail.id)?.focus(), 30))">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <h2 class="h6 fw-bold mb-0" id="xmCccLawsTitle" tabindex="-1">{{ __('admin_ccc.laws_title') }}</h2>
                <button type="button" class="btn btn-sm btn-primary" wire:click="openLawCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.law.new') }}</button>
            </div>
            <p class="small text-secondary">{{ __('admin_ccc.law.intro') }}</p>
            <div class="visually-hidden" role="status" aria-live="polite">{{ $announce }}</div>
            <div class="row g-3" role="list" aria-label="{{ __('admin_ccc.law.list_label') }}">
                @forelse ($this->laws as $law)
                    <div class="col-md-6 col-xl-4" role="listitem" wire:key="law-{{ $law->id }}">
                        <div @class(['card h-100 xm-admin-card', 'opacity-75' => ! $law->is_published])>
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <x-badge color="neutral" soft>{{ $law->category }}</x-badge>
                                    <x-badge :color="$law->is_published ? 'success' : 'neutral'" soft>{{ $law->is_published ? __('admin_ccc.published') : __('admin_ccc.hidden') }}</x-badge>
                                </div>
                                <h3 class="h6">{{ $law->title }}</h3>
                                @if ($law->subtitle)<p class="small text-secondary mb-1">{{ $law->subtitle }}</p>@endif
                                <p class="small text-secondary mb-0">{{ __('admin_ccc.law.order', ['position' => $loop->iteration, 'total' => $lawTotal]) }}</p>
                            </div>
                            <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="xmLaw{{ $law->id }}" @checked($law->is_published) wire:click="toggleLaw({{ $law->id }})">
                                    <label class="form-check-label small" for="xmLaw{{ $law->id }}">{{ __('admin_ccc.published') }}<span class="visually-hidden"> — {{ $law->title }}</span></label>
                                </div>
                                <div class="ms-auto d-flex flex-wrap gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="xmLawUp{{ $law->id }}" wire:click="moveLaw({{ $law->id }}, -1)" @disabled($loop->first)><i class="bi bi-arrow-up me-1" aria-hidden="true"></i>{{ __('admin_ccc.law.up') }}<span class="visually-hidden"> {{ $law->title }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="xmLawDown{{ $law->id }}" wire:click="moveLaw({{ $law->id }}, 1)" @disabled($loop->last)><i class="bi bi-arrow-down me-1" aria-hidden="true"></i>{{ __('admin_ccc.law.down') }}<span class="visually-hidden"> {{ $law->title }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openLawEdit({{ $law->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_ccc.law.edit_open') }}<span class="visually-hidden"> {{ $law->title }}</span></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openLawDelete({{ $law->id }})"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_ccc.law.delete_open') }}<span class="visually-hidden"> {{ $law->title }}</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><x-admin.empty icon="bank" :title="__('admin_ccc.empty_laws')" /></div>
                @endforelse
            </div>
        </section>
    @else
        <section aria-labelledby="xmCccToolsTitle" x-data x-on:xm-ccc-focus.window="$nextTick(() => setTimeout(() => document.getElementById($event.detail.id)?.focus(), 30))">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 fw-bold mb-0" id="xmCccToolsTitle" tabindex="-1">{{ __('admin_ccc.tabs.'.$tab) }}</h2>
                @if ($isManualTab)
                    <button type="button" class="btn btn-sm btn-primary" wire:click="openModuleCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.new') }}</button>
                @elseif (in_array($tabKind, $pagedKinds, true))
                    <button type="button" class="btn btn-sm btn-primary" wire:click="openCreate"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.'.$tabCopy.'.new') }}</button>
                @endif
            </div>
            @if ($isManualTab)
                <p class="small text-secondary">{{ __('admin_ccc.module.intro') }}</p>
                <div class="visually-hidden" role="status" aria-live="polite">{{ $announce }}</div>
            @endif
            <div class="row g-3" @if ($isManualTab) role="list" aria-label="{{ __('admin_ccc.module.list_label') }}" @endif>
                @forelse ($this->tools as $tool)
                    <div class="col-md-6 col-xl-4" wire:key="tool-{{ $tool->id }}" @if ($isManualTab) role="listitem" @endif>
                        <div @class(['card h-100 xm-admin-card', 'opacity-75' => ! $tool->is_published])>
                            <div class="ratio ratio-16x9 bg-body-secondary">
                                <img src="{{ \App\Support\MediaUrl::resolve($tool->image_path, $fallbackImage) }}" class="object-fit-cover" alt="" loading="lazy" width="640" height="360">
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <x-badge :color="$tool->kind->badge()" soft>{{ $tool->kind->label() }}</x-badge>
                                    <x-badge :color="$tool->is_published ? 'success' : 'neutral'" soft>{{ $tool->is_published ? __('admin_ccc.published') : __('admin_ccc.hidden') }}</x-badge>
                                </div>
                                <h3 class="h6">{{ $tool->title }}</h3>
                                <p class="small text-secondary mb-0">
                                    @if ($tool->kind === \App\Enums\CccKind::Manual)
                                        {{ trans_choice('admin_ccc.chapters', count((array) $tool->chapters), ['count' => count((array) $tool->chapters)]) }}@if ($tool->duration_minutes) · {{ __('admin_ccc.minutes', ['count' => $tool->duration_minutes]) }}@endif · {{ __('admin_ccc.form.key', ['key' => $tool->key]) }}
                                        @if ($tool->description)<br>{{ $tool->description }}@endif
                                        <br>{{ __('admin_ccc.module.order', ['position' => $loop->iteration, 'total' => $moduleTotal]) }}
                                    @elseif (in_array($tool->kind, $pagedKinds, true))
                                        {{ trans_choice('admin_ccc.pages', $tool->pagesCount(), ['count' => $tool->pagesCount()]) }} · {{ __('admin_ccc.form.key', ['key' => $tool->key]) }}
                                        @if ($tool->description)<br>{{ $tool->description }}@endif
                                    @else
                                        {{ $tool->description }}
                                    @endif
                                </p>
                            </div>
                            <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="xmTool{{ $tool->id }}" @checked($tool->is_published) wire:click="toggle({{ $tool->id }})">
                                    <label class="form-check-label small" for="xmTool{{ $tool->id }}">{{ __('admin_ccc.published') }}<span class="visually-hidden"> — {{ $tool->title }}</span></label>
                                </div>
                                @if ($tool->kind === \App\Enums\CccKind::Comic)
                                    {{-- P19-04 : BD servie aussi sur la page publique indexable /bd/{key} (Q19). --}}
                                    <div class="form-check form-switch mb-0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="xmToolPublic{{ $tool->id }}" @checked($tool->is_public) wire:click="togglePublic({{ $tool->id }})" aria-describedby="xmToolPublicHelp{{ $tool->id }}">
                                        <label class="form-check-label small" for="xmToolPublic{{ $tool->id }}">{{ __('admin_ccc.public.label') }}<span class="visually-hidden"> — {{ $tool->title }}</span></label>
                                        <span class="visually-hidden" id="xmToolPublicHelp{{ $tool->id }}">{{ __('admin_ccc.public.help') }}</span>
                                    </div>
                                    @if ($tool->is_public && $tool->is_published)
                                        <a class="small" href="{{ route('bd.show', ['key' => $tool->key]) }}" target="_blank" rel="noopener">{{ __('admin_ccc.public.view') }}<span class="visually-hidden"> {{ $tool->title }} {{ __('microcopy.a11y.opens_new_tab') }}</span></a>
                                    @endif
                                @endif
                                <div class="ms-auto d-flex flex-wrap gap-1">
                                    @if ($isManualTab)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="xmModuleUp{{ $tool->id }}" wire:click="moveModule({{ $tool->id }}, -1)" @disabled($loop->first)><i class="bi bi-arrow-up me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.up') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="xmModuleDown{{ $tool->id }}" wire:click="moveModule({{ $tool->id }}, 1)" @disabled($loop->last)><i class="bi bi-arrow-down me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.down') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openModuleChapters({{ $tool->id }})"><i class="bi bi-list-ol me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.chapters_open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openModuleEdit({{ $tool->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.edit_open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                    @endif
                                    @if (in_array($tool->kind, $pagedKinds, true))
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openPages({{ $tool->id }})"><i class="bi bi-card-text me-1" aria-hidden="true"></i>{{ __('admin_ccc.planches.open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openEdit({{ $tool->id }})"><i class="bi bi-pencil me-1" aria-hidden="true"></i>{{ __('admin_ccc.form.edit_open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openImage({{ $tool->id }})"><i class="bi bi-image me-1" aria-hidden="true"></i>{{ __('admin_ccc.image.open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                    @if ($isManualTab)
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openModuleDelete({{ $tool->id }})"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_ccc.module.delete_open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                    @endif
                                    @if (in_array($tool->kind, $pagedKinds, true))
                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="openDelete({{ $tool->id }})"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_ccc.delete.open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><x-admin.empty icon="collection-play" :title="__('admin_ccc.empty')" /></div>
                @endforelse
            </div>
        </section>
    @endif

    <x-admin.modal id="xmCccImage" size="md" heading="h2" :title="__('admin_ccc.image.title', ['title' => $this->openedTool?->title ?? ''])" x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccToolsTitle'))?.focus()">
        <form id="xmCccImageForm" wire:submit="saveImage" novalidate>
            <label class="form-label fw-semibold" for="xmCccImageFile">{{ __('admin_ccc.image.label') }}</label>
            <input class="form-control @error('image') is-invalid @enderror" type="file" id="xmCccImageFile" data-xm-autofocus accept="image/jpeg,image/png,image/webp" wire:model="image" aria-describedby="xmCccImageHelp">
            <div class="form-text" id="xmCccImageHelp">{{ __('admin_ccc.image.help') }}</div>
            @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div wire:loading wire:target="image" class="small text-secondary" role="status">{{ __('admin_content.uploading') }}</div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmCccImageForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveImage,image"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('admin_ccc.image.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Nouvelle BD ou boîte à images / titre --}}
    <x-admin.modal id="xmCccPaged" size="lg" heading="h2" :title="$toolId === null ? $copy('create_title') : $copy('edit_title', ['title' => $this->openedTool?->title ?? ''])" x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccToolsTitle'))?.focus()">
        <form id="xmCccPagedForm" wire:submit="saveTool" novalidate>
            @error('tool')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmCccPagedTitle">{{ __('admin_ccc.form.title_label') }}</label>
                <input class="form-control @error('title') is-invalid @enderror" type="text" id="xmCccPagedTitle" data-xm-autofocus wire:model="title" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::TITLE_MAX }}" required autocomplete="off"
                       aria-describedby="xmCccPagedTitleHelp @error('title') xmCccPagedTitleError @enderror" @error('title') aria-invalid="true" @enderror>
                <div class="form-text" id="xmCccPagedTitleHelp">{{ $copy('title_help', ['max' => \App\Application\Admin\Content\ManageCccTools::TITLE_MAX]) }}</div>
                @error('title')<div class="invalid-feedback d-block" id="xmCccPagedTitleError">{{ $message }}</div>@enderror
            </div>
            @if ($toolId === null)
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="xmCccPagedCover">{{ __('admin_ccc.form.cover_label') }}</label>
                    <input class="form-control @error('cover') is-invalid @enderror" type="file" id="xmCccPagedCover" accept="image/jpeg,image/png,image/webp" wire:model="cover" wire:key="cover-{{ $fileInputKey }}"
                           aria-describedby="xmCccPagedCoverHelp @error('cover') xmCccPagedCoverError @enderror" @error('cover') aria-invalid="true" @enderror>
                    <div class="form-text" id="xmCccPagedCoverHelp">{{ __('admin_ccc.form.cover_help') }}</div>
                    @error('cover')<div class="invalid-feedback d-block" id="xmCccPagedCoverError">{{ $message }}</div>@enderror
                    <div wire:loading wire:target="cover" class="small text-secondary" role="status">{{ __('admin_content.uploading') }}</div>
                </div>
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="xmCccPagedPublished" wire:model="published"
                           aria-describedby="xmCccPagedPublishedHelp @error('published') xmCccPagedPublishedError @enderror" @error('published') aria-invalid="true" @enderror>
                    <label class="form-check-label fw-semibold" for="xmCccPagedPublished">{{ $copy('published_label') }}</label>
                    <div class="form-text" id="xmCccPagedPublishedHelp">{{ $copy('published_help') }}</div>
                    @error('published')<div class="invalid-feedback d-block" id="xmCccPagedPublishedError">{{ $message }}</div>@enderror
                </div>
                <h3 class="h6 fw-bold" id="xmCccDraftTitle">{{ __('admin_ccc.form.draft_title') }}</h3>
                <p class="small text-secondary">{{ __('admin_ccc.form.draft_help') }}</p>
                @foreach ($draftPages as $i => $row)
                    <fieldset class="border rounded p-3 mb-3" wire:key="draft-{{ $i }}">
                        <legend class="h6 float-none w-auto px-1 mb-2">{{ __('admin_ccc.form.draft_legend', ['number' => $i + 1]) }}</legend>
                        <div class="mb-3">
                            <label class="form-label" for="xmCccDraftFile{{ $i }}">{{ __('admin_ccc.planches.image') }}</label>
                            <input class="form-control @error('draftPages.'.$i.'.file') is-invalid @enderror" type="file" id="xmCccDraftFile{{ $i }}" accept="image/jpeg,image/png,image/webp" wire:model="draftPages.{{ $i }}.file" required
                                   @error('draftPages.'.$i.'.file') aria-invalid="true" aria-describedby="xmCccDraftFileError{{ $i }}" @enderror>
                            @error('draftPages.'.$i.'.file')<div class="invalid-feedback d-block" id="xmCccDraftFileError{{ $i }}">{{ $message }}</div>@enderror
                            <div wire:loading wire:target="draftPages.{{ $i }}.file" class="small text-secondary" role="status">{{ __('admin_content.uploading') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="xmCccDraftCaption{{ $i }}">{{ __('admin_ccc.planches.caption') }}</label>
                            <input class="form-control @error('draftPages.'.$i.'.caption') is-invalid @enderror" type="text" id="xmCccDraftCaption{{ $i }}" wire:model="draftPages.{{ $i }}.caption" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::CAPTION_MAX }}" autocomplete="off"
                                   @error('draftPages.'.$i.'.caption') aria-invalid="true" aria-describedby="xmCccDraftCaptionError{{ $i }}" @enderror>
                            @error('draftPages.'.$i.'.caption')<div class="invalid-feedback d-block" id="xmCccDraftCaptionError{{ $i }}">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="xmCccDraftAlt{{ $i }}">{{ __('admin_ccc.planches.alt') }}</label>
                            <input class="form-control @error('draftPages.'.$i.'.alt') is-invalid @enderror" type="text" id="xmCccDraftAlt{{ $i }}" wire:model="draftPages.{{ $i }}.alt" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::ALT_MAX }}" required autocomplete="off"
                                   aria-describedby="xmCccDraftAltHelp{{ $i }} @error('draftPages.'.$i.'.alt') xmCccDraftAltError{{ $i }} @enderror" @error('draftPages.'.$i.'.alt') aria-invalid="true" @enderror>
                            <div class="form-text" id="xmCccDraftAltHelp{{ $i }}">{{ __('admin_ccc.planches.alt_help', ['max' => \App\Application\Admin\Content\ManageCccTools::ALT_MAX]) }}</div>
                            @error('draftPages.'.$i.'.alt')<div class="invalid-feedback d-block" id="xmCccDraftAltError{{ $i }}">{{ $message }}</div>@enderror
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeDraftPage({{ $i }})"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.form.draft_remove') }}<span class="visually-hidden"> {{ $i + 1 }}</span></button>
                    </fieldset>
                @endforeach
                @if (count($draftPages) < \App\Livewire\Admin\Ccc\Index::DRAFT_PAGES_MAX)
                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="addDraftPage"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.form.draft_add') }}</button>
                @endif
            @else
                <p class="small text-secondary mb-0">{{ __('admin_ccc.form.key', ['key' => $this->openedTool?->key ?? '']) }}</p>
            @endif
        </form>
        <x-slot:footer>
            <button type="submit" form="xmCccPagedForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveTool,cover,draftPages"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $toolId === null ? $copy('create') : __('admin_ccc.form.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Planches d'une BD ou d'une boîte à images : liste ordonnée, ajout, retrait, légende, texte alternatif --}}
    <x-admin.modal id="xmCccPages" size="xl" heading="h2" :title="__('admin_ccc.planches.title', ['title' => $this->openedTool?->title ?? ''])" x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccToolsTitle'))?.focus()" x-on:xm-ccc-focus.window="$nextTick(() => setTimeout(() => document.getElementById($event.detail.id)?.focus(), 30))">
        <p class="small text-secondary">{{ __('admin_ccc.planches.intro', ['max' => \App\Application\Admin\Content\ManageCccTools::ALT_MAX]) }}</p>
        <div class="visually-hidden" role="status" aria-live="polite">{{ $announce }}</div>
        @error('pages')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
        @php($pageCount = count($this->openedPages))
        <form id="xmCccPagesForm" wire:submit="savePages" novalidate>
            <ol class="list-unstyled mb-0" id="xmCccPagesList" tabindex="-1" aria-label="{{ __('admin_ccc.planches.list_label') }}">
                @forelse ($this->openedPages as $index => $page)
                    <li class="mb-3" wire:key="page-{{ $index }}">
                        <fieldset class="border rounded p-3">
                            <legend class="h6 float-none w-auto px-1 mb-2">{{ __('admin_ccc.planches.legend', ['number' => $index + 1, 'total' => $pageCount]) }}</legend>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <img src="{{ \App\Support\MediaUrl::resolve($page['image'], $fallbackImage) }}" class="img-fluid rounded border" alt="" loading="lazy" width="320" height="240">
                                </div>
                                <div class="col-md-9">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold" for="xmCccPageCaption{{ $index }}">{{ __('admin_ccc.planches.caption') }}</label>
                                        <input class="form-control @error('pageEdits.'.$index.'.caption') is-invalid @enderror" type="text" id="xmCccPageCaption{{ $index }}" wire:model.blur="pageEdits.{{ $index }}.caption" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::CAPTION_MAX }}" autocomplete="off"
                                               @error('pageEdits.'.$index.'.caption') aria-invalid="true" aria-describedby="xmCccPageCaptionError{{ $index }}" @enderror>
                                        @error('pageEdits.'.$index.'.caption')<div class="invalid-feedback d-block" id="xmCccPageCaptionError{{ $index }}">{{ $message }}</div>@enderror
                                    </div>
                                    <div>
                                        <label class="form-label fw-semibold" for="xmCccPageAlt{{ $index }}">{{ __('admin_ccc.planches.alt') }}</label>
                                        <input class="form-control @error('pageEdits.'.$index.'.alt') is-invalid @enderror" type="text" id="xmCccPageAlt{{ $index }}" wire:model.blur="pageEdits.{{ $index }}.alt" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::ALT_MAX }}" required autocomplete="off"
                                               aria-describedby="xmCccPageAltHelp{{ $index }} @error('pageEdits.'.$index.'.alt') xmCccPageAltError{{ $index }} @enderror" @error('pageEdits.'.$index.'.alt') aria-invalid="true" @enderror>
                                        <div class="form-text" id="xmCccPageAltHelp{{ $index }}">{{ __('admin_ccc.planches.alt_help', ['max' => \App\Application\Admin\Content\ManageCccTools::ALT_MAX]) }}</div>
                                        @error('pageEdits.'.$index.'.alt')<div class="invalid-feedback d-block" id="xmCccPageAltError{{ $index }}">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="xmCccPageUp{{ $index }}" wire:click="movePage({{ $index }}, -1)" @disabled($index === 0)><i class="bi bi-arrow-up me-1" aria-hidden="true"></i>{{ __('admin_ccc.planches.up') }}<span class="visually-hidden"> {{ __('admin_ccc.planches.legend', ['number' => $index + 1, 'total' => $pageCount]) }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="xmCccPageDown{{ $index }}" wire:click="movePage({{ $index }}, 1)" @disabled($index === $pageCount - 1)><i class="bi bi-arrow-down me-1" aria-hidden="true"></i>{{ __('admin_ccc.planches.down') }}<span class="visually-hidden"> {{ __('admin_ccc.planches.legend', ['number' => $index + 1, 'total' => $pageCount]) }}</span></button>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" id="xmCccPageRemove{{ $index }}" wire:click="askRemovePage({{ $index }})"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_ccc.planches.remove') }}<span class="visually-hidden"> {{ __('admin_ccc.planches.legend', ['number' => $index + 1, 'total' => $pageCount]) }}</span></button>
                            </div>
                            @if ($removeIndex === $index)
                                <div class="alert alert-warning mt-3 mb-0" role="alert" x-data x-init="$nextTick(() => $el.querySelector('[data-xm-confirm]')?.focus())">
                                    <p class="mb-2">{{ __('admin_ccc.planches.remove_confirm', ['number' => $index + 1]) }}</p>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-danger" data-xm-confirm wire:click="removePage" wire:loading.attr="disabled" wire:target="removePage">{{ __('admin_ccc.planches.remove_yes') }}</button>
                                        <button type="button" class="btn btn-sm btn-light" wire:click="cancelRemovePage">{{ __('admin_ccc.planches.remove_no') }}</button>
                                    </div>
                                </div>
                            @endif
                        </fieldset>
                    </li>
                @empty
                    <li class="text-secondary mb-3">{{ __('admin_ccc.planches.empty') }}</li>
                @endforelse
            </ol>
        </form>

        <form id="xmCccAddPageForm" class="border rounded p-3 bg-body-tertiary" wire:submit="addPage" novalidate>
            <h3 class="h6 fw-bold">{{ __('admin_ccc.planches.add_title') }}</h3>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmCccNewImage">{{ __('admin_ccc.planches.image') }}</label>
                <input class="form-control @error('newImage') is-invalid @enderror" type="file" id="xmCccNewImage" accept="image/jpeg,image/png,image/webp" wire:model="newImage" wire:key="new-image-{{ $fileInputKey }}" required
                       aria-describedby="xmCccNewImageHelp @error('newImage') xmCccNewImageError @enderror" @error('newImage') aria-invalid="true" @enderror>
                <div class="form-text" id="xmCccNewImageHelp">{{ __('admin_ccc.planches.image_help') }}</div>
                @error('newImage')<div class="invalid-feedback d-block" id="xmCccNewImageError">{{ $message }}</div>@enderror
                <div wire:loading wire:target="newImage" class="small text-secondary" role="status">{{ __('admin_content.uploading') }}</div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmCccNewCaption">{{ __('admin_ccc.planches.caption') }}</label>
                <input class="form-control @error('newCaption') is-invalid @enderror" type="text" id="xmCccNewCaption" wire:model="newCaption" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::CAPTION_MAX }}" autocomplete="off"
                       @error('newCaption') aria-invalid="true" aria-describedby="xmCccNewCaptionError" @enderror>
                @error('newCaption')<div class="invalid-feedback d-block" id="xmCccNewCaptionError">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmCccNewAlt">{{ __('admin_ccc.planches.alt') }}</label>
                <input class="form-control @error('newAlt') is-invalid @enderror" type="text" id="xmCccNewAlt" wire:model="newAlt" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::ALT_MAX }}" required autocomplete="off"
                       aria-describedby="xmCccNewAltHelp @error('newAlt') xmCccNewAltError @enderror" @error('newAlt') aria-invalid="true" @enderror>
                <div class="form-text" id="xmCccNewAltHelp">{{ __('admin_ccc.planches.alt_help', ['max' => \App\Application\Admin\Content\ManageCccTools::ALT_MAX]) }}</div>
                @error('newAlt')<div class="invalid-feedback d-block" id="xmCccNewAltError">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-outline-primary" wire:loading.attr="disabled" wire:target="addPage,newImage"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.planches.add') }}</button>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmCccPagesForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="savePages" @disabled($pageCount === 0)><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.planches.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Suppression d'une BD ou d'une boîte à images --}}
    <x-admin.modal id="xmCccDelete" type="delete" size="md" heading="h2" :title="$copy('delete_title', ['title' => $this->openedTool?->title ?? ''])" static x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccToolsTitle'))?.focus()">
        <p>{{ $copy('delete_text') }}</p>
        @error('delete')<div class="alert alert-danger mb-0" role="alert">{{ $message }}</div>@enderror
        <x-slot:footer>
            <button type="button" class="btn btn-danger" wire:click="deleteTool" wire:loading.attr="disabled" wire:target="deleteTool"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('components.modal.delete_action') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    @include('livewire.admin.ccc.modules-modals')

    {{-- Texte de loi : création et modification --}}
    @php($lawMax = ['title' => \App\Application\Admin\Content\ManageLaws::TITLE_MAX, 'subtitle' => \App\Application\Admin\Content\ManageLaws::SUBTITLE_MAX, 'category' => \App\Application\Admin\Content\ManageLaws::CATEGORY_MAX, 'summary' => \App\Application\Admin\Content\ManageLaws::SUMMARY_MAX, 'url' => \App\Application\Admin\Content\ManageLaws::URL_MAX])
    <x-admin.modal id="xmCccLaw" size="lg" heading="h2" :title="$lawId === null ? __('admin_ccc.law.create_title') : __('admin_ccc.law.edit_title', ['title' => $this->openedLaw?->title ?? ''])" x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccLawsTitle'))?.focus()">
        <form id="xmCccLawForm" wire:submit="saveLaw" novalidate>
            @error('law')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmLawTitle">{{ __('admin_ccc.law.fields.title') }}</label>
                <input class="form-control @error('lawTitle') is-invalid @enderror" type="text" id="xmLawTitle" data-xm-autofocus wire:model="lawTitle" maxlength="{{ $lawMax['title'] }}" required autocomplete="off"
                       @error('lawTitle') aria-invalid="true" aria-describedby="xmLawTitleError" @enderror>
                @error('lawTitle')<div class="invalid-feedback d-block" id="xmLawTitleError">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmLawSubtitle">{{ __('admin_ccc.law.fields.subtitle') }}</label>
                <input class="form-control @error('lawSubtitle') is-invalid @enderror" type="text" id="xmLawSubtitle" wire:model="lawSubtitle" maxlength="{{ $lawMax['subtitle'] }}" autocomplete="off"
                       aria-describedby="xmLawSubtitleHelp @error('lawSubtitle') xmLawSubtitleError @enderror" @error('lawSubtitle') aria-invalid="true" @enderror>
                <div class="form-text" id="xmLawSubtitleHelp">{{ __('admin_ccc.law.subtitle_help', ['max' => $lawMax['subtitle']]) }}</div>
                @error('lawSubtitle')<div class="invalid-feedback d-block" id="xmLawSubtitleError">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmLawCategory">{{ __('admin_ccc.law.fields.category') }}</label>
                <input class="form-control @error('lawCategory') is-invalid @enderror" type="text" id="xmLawCategory" wire:model="lawCategory" list="xmLawCategories" maxlength="{{ $lawMax['category'] }}" required autocomplete="off"
                       aria-describedby="xmLawCategoryHelp @error('lawCategory') xmLawCategoryError @enderror" @error('lawCategory') aria-invalid="true" @enderror>
                <datalist id="xmLawCategories">
                    @foreach ($this->lawCategories as $category)<option value="{{ $category }}"></option>@endforeach
                </datalist>
                <div class="form-text" id="xmLawCategoryHelp">{{ __('admin_ccc.law.category_help', ['max' => $lawMax['category']]) }}</div>
                @error('lawCategory')<div class="invalid-feedback d-block" id="xmLawCategoryError">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmLawSummary">{{ __('admin_ccc.law.fields.summary') }}</label>
                <textarea class="form-control @error('lawSummary') is-invalid @enderror" id="xmLawSummary" rows="4" wire:model="lawSummary" maxlength="{{ $lawMax['summary'] }}" required
                          aria-describedby="xmLawSummaryHelp @error('lawSummary') xmLawSummaryError @enderror" @error('lawSummary') aria-invalid="true" @enderror></textarea>
                <div class="form-text" id="xmLawSummaryHelp">{{ __('admin_ccc.law.summary_help', ['max' => $lawMax['summary']]) }}</div>
                @error('lawSummary')<div class="invalid-feedback d-block" id="xmLawSummaryError">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmLawSimple">{{ __('admin_ccc.law.fields.simple_summary') }}</label>
                <textarea class="form-control @error('lawSimple') is-invalid @enderror" id="xmLawSimple" rows="3" wire:model="lawSimple" maxlength="{{ $lawMax['summary'] }}"
                          aria-describedby="xmLawSimpleHelp @error('lawSimple') xmLawSimpleError @enderror" @error('lawSimple') aria-invalid="true" @enderror></textarea>
                <div class="form-text" id="xmLawSimpleHelp">{{ __('admin_ccc.law.simple_help') }}</div>
                @error('lawSimple')<div class="invalid-feedback d-block" id="xmLawSimpleError">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="xmLawUrl">{{ __('admin_ccc.law.fields.official_url') }}</label>
                <input class="form-control @error('lawUrl') is-invalid @enderror" type="url" id="xmLawUrl" wire:model="lawUrl" maxlength="{{ $lawMax['url'] }}" autocomplete="off" inputmode="url"
                       aria-describedby="xmLawUrlHelp @error('lawUrl') xmLawUrlError @enderror" @error('lawUrl') aria-invalid="true" @enderror>
                <div class="form-text" id="xmLawUrlHelp">{{ __('admin_ccc.law.url_help') }}</div>
                @error('lawUrl')<div class="invalid-feedback d-block" id="xmLawUrlError">{{ $message }}</div>@enderror
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="xmLawPublished" wire:model="lawPublished"
                       aria-describedby="xmLawPublishedHelp @error('lawPublished') xmLawPublishedError @enderror" @error('lawPublished') aria-invalid="true" @enderror>
                <label class="form-check-label fw-semibold" for="xmLawPublished">{{ __('admin_ccc.law.published_label') }}</label>
                <div class="form-text" id="xmLawPublishedHelp">{{ __('admin_ccc.law.published_help') }}</div>
                @error('lawPublished')<div class="invalid-feedback d-block" id="xmLawPublishedError">{{ $message }}</div>@enderror
            </div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmCccLawForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveLaw"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ $lawId === null ? __('admin_ccc.law.create') : __('admin_ccc.law.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Suppression d'un texte de loi --}}
    <x-admin.modal id="xmCccLawDelete" type="delete" size="md" heading="h2" :title="__('admin_ccc.law.delete_title', ['title' => $this->openedLaw?->title ?? ''])" static x-data="{ opener: null }" x-on:show-bs-modal.dot="opener = document.activeElement" x-on:shown-bs-modal.dot="$el.querySelector('[data-xm-autofocus]')?.focus()" x-on:hidden-bs-modal.dot="$wire.resetModal(); (opener && document.body.contains(opener) ? opener : document.getElementById('xmCccLawsTitle'))?.focus()">
        <p>{{ __('admin_ccc.law.delete_text') }}</p>
        <x-slot:footer>
            <button type="button" class="btn btn-danger" wire:click="deleteLaw" wire:loading.attr="disabled" wire:target="deleteLaw"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('components.modal.delete_action') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
