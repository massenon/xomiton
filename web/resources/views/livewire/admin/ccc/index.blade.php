{{--
    Outils CCC (P12-04) : composant App\Livewire\Admin\Ccc\Index, gabarit admin.
    Ordre : introduction · KPI · onglets (Manuel, Boîtes à images, BD SSR, Lois) · cartes · modales (image, planches).
    Un outil masqué disparaît des écrans membre et pair ; l'image remplacée garde un texte alternatif par planche.
--}}
@php
    $tabMeta = [
        'manuel' => ['label' => __('admin_ccc.tabs.manuel'), 'icon' => 'journal-richtext'],
        'boite_images' => ['label' => __('admin_ccc.tabs.boite_images'), 'icon' => 'images'],
        'bd' => ['label' => __('admin_ccc.tabs.bd'), 'icon' => 'chat-square-text'],
        'lois' => ['label' => __('admin_ccc.tabs.lois'), 'icon' => 'bank'],
    ];
    $visibleTabs = [];
    foreach ($tabs as $key) {
        $visibleTabs[$key] = $tabMeta[$key] + ['count' => $this->counts[$key]];
    }
    $format = \App\Support\LocaleFormatter::make();
    $fallbackImage = 'assets/images/xomiton/covers/og-default.jpg';
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

    @if ($tab === 'lois')
        <section aria-labelledby="xmCccLawsTitle">
            <h2 class="h6 fw-bold mb-3" id="xmCccLawsTitle">{{ __('admin_ccc.laws_title') }}</h2>
            <div class="row g-3">
                @forelse ($this->laws as $law)
                    <div class="col-md-6 col-xl-4" wire:key="law-{{ $law->id }}">
                        <div @class(['card h-100 xm-admin-card', 'opacity-75' => ! $law->is_published])>
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2 mb-2">
                                    <x-badge color="neutral" soft>{{ $law->category }}</x-badge>
                                    <x-badge :color="$law->is_published ? 'success' : 'neutral'" soft>{{ $law->is_published ? __('admin_ccc.published') : __('admin_ccc.hidden') }}</x-badge>
                                </div>
                                <h3 class="h6">{{ $law->title }}</h3>
                                @if ($law->subtitle)<p class="small text-secondary mb-0">{{ $law->subtitle }}</p>@endif
                            </div>
                            <div class="card-footer">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="xmLaw{{ $law->id }}" @checked($law->is_published) wire:click="toggleLaw({{ $law->id }})">
                                    <label class="form-check-label small" for="xmLaw{{ $law->id }}">{{ __('admin_ccc.published') }}<span class="visually-hidden"> — {{ $law->title }}</span></label>
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
        <section aria-labelledby="xmCccToolsTitle">
            <h2 class="h6 fw-bold mb-3" id="xmCccToolsTitle">{{ __('admin_ccc.tabs.'.$tab) }}</h2>
            <div class="row g-3">
                @forelse ($this->tools as $tool)
                    <div class="col-md-6 col-xl-4" wire:key="tool-{{ $tool->id }}">
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
                                        {{ trans_choice('admin_ccc.chapters', count((array) $tool->chapters), ['count' => count((array) $tool->chapters)]) }}@if ($tool->duration_minutes) · {{ __('admin_ccc.minutes', ['count' => $tool->duration_minutes]) }}@endif
                                    @elseif ($tool->kind === \App\Enums\CccKind::Comic)
                                        {{ trans_choice('admin_ccc.pages', $tool->pagesCount(), ['count' => $tool->pagesCount()]) }}
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
                                <div class="ms-auto d-flex flex-wrap gap-1">
                                    @if ($tool->kind === \App\Enums\CccKind::Comic)
                                        <button type="button" class="btn btn-sm btn-outline-primary" wire:click="openAlts({{ $tool->id }})"><i class="bi bi-card-text me-1" aria-hidden="true"></i>{{ __('admin_ccc.alts.open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="openImage({{ $tool->id }})"><i class="bi bi-image me-1" aria-hidden="true"></i>{{ __('admin_ccc.image.open') }}<span class="visually-hidden"> {{ $tool->title }}</span></button>
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

    <x-admin.modal id="xmCccImage" size="md" heading="h2" :title="__('admin_ccc.image.title', ['title' => $this->openedTool?->title ?? ''])" x-data x-on:hidden-bs-modal.dot="$wire.resetModal()">
        <form id="xmCccImageForm" wire:submit="saveImage" novalidate>
            <label class="form-label fw-semibold" for="xmCccImageFile">{{ __('admin_ccc.image.label') }}</label>
            <input class="form-control @error('image') is-invalid @enderror" type="file" id="xmCccImageFile" accept="image/jpeg,image/png,image/webp" wire:model="image" aria-describedby="xmCccImageHelp">
            <div class="form-text" id="xmCccImageHelp">{{ __('admin_ccc.image.help') }}</div>
            @error('image')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div wire:loading wire:target="image" class="small text-secondary" role="status">{{ __('admin_content.uploading') }}</div>
        </form>
        <x-slot:footer>
            <button type="submit" form="xmCccImageForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveImage,image"><i class="bi bi-upload me-1" aria-hidden="true"></i>{{ __('admin_ccc.image.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    <x-admin.modal id="xmCccAlts" size="lg" heading="h2" :title="__('admin_ccc.alts.title', ['title' => $this->openedTool?->title ?? ''])" x-data x-on:hidden-bs-modal.dot="$wire.resetModal()">
        <form id="xmCccAltsForm" wire:submit="saveAlts" novalidate>
            <p class="small text-secondary">{{ __('admin_ccc.alts.help', ['max' => \App\Application\Admin\Content\ManageCccTools::ALT_MAX]) }}</p>
            @error('alts')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
            @foreach ($this->openedTool?->pages ?? [] as $index => $page)
                <div class="mb-3" wire:key="alt-{{ $index }}">
                    <label class="form-label fw-semibold" for="xmCccAlt{{ $index }}">{{ __('admin_ccc.alts.page', ['number' => $index + 1]) }}</label>
                    @if (! empty($page['caption']))<div class="small text-secondary mb-1">{{ $page['caption'] }}</div>@endif
                    <input class="form-control" type="text" id="xmCccAlt{{ $index }}" wire:model.blur="alts.{{ $index }}" maxlength="{{ \App\Application\Admin\Content\ManageCccTools::ALT_MAX }}" autocomplete="off">
                </div>
            @endforeach
        </form>
        <x-slot:footer>
            <button type="submit" form="xmCccAltsForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveAlts"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_ccc.alts.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
