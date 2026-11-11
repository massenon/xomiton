{{--
    Barre de recherche et de filtres d'un tableau d'administration (P10-02, UI_UX_DESIGN_SYSTEM.md §4.4, ADR-33) :
    recherche en premier (loupe, `wire:model.live.debounce.300ms="search"`, URL ?q=), filtres de l'écran dans le slot
    (selects `form-select-sm` en `wire:model.live`, libellés visually-hidden, propriétés #[Url] : ?st=…), bouton
    « Filtrer » (envoi clavier) et « Réinitialiser » quand un filtre est actif. Méthodes du trait WithDataTable.
    Props :
      id          : préfixe des identifiants (défaut xmFilters)
      search      : afficher la recherche (défaut true) ; placeholder : texte d'exemple (« Nom ou slug… »)
      searchHelp  : aide sous la recherche (défaut : colonnes en clair seulement, ADR-33)
      active      : nombre de filtres actifs ($this->activeFilterCount())
    Slot : colonnes de filtres supplémentaires (<div class="col-6 col-md-2">…</div>).
--}}
@props(['id' => 'xmFilters', 'search' => true, 'placeholder' => null, 'searchHelp' => null, 'active' => 0])
<form {{ $attributes->class('xm-filters') }} role="search" aria-label="{{ __('admin.filters.title') }}" wire:submit="applyFilters" novalidate>
    <div class="row g-2 align-items-start">
        @if ($search)
            <div class="col-12 col-lg">
                <label class="visually-hidden" for="{{ $id }}Search">{{ __('admin.filters.search') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-transparent"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input class="form-control" type="search" id="{{ $id }}Search" wire:model.live.debounce.300ms="search" maxlength="{{ \App\Support\ListingQuery::SEARCH_MAX }}"
                           placeholder="{{ $placeholder ?? __('admin.filters.search_placeholder') }}" aria-describedby="{{ $id }}SearchHelp" autocomplete="off" enterkeyhint="search">
                </div>
                <div class="form-text small mt-1" id="{{ $id }}SearchHelp">{{ $searchHelp ?? __('admin.filters.search_help') }}</div>
            </div>
        @endif
        {{ $slot }}
        <div class="col-12 col-lg-auto d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-sm btn-primary-soft"><i class="bi bi-funnel me-1" aria-hidden="true"></i>{{ __('admin.filters.apply') }}</button>
            @if ($active > 0)
                <button type="button" class="btn btn-sm btn-link px-1" wire:click="resetFilters"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('admin.filters.reset') }}</button>
            @endif
        </div>
    </div>
    <p @class(['small text-secondary mb-0 mt-2', 'visually-hidden' => $active === 0]) role="status" data-xm-filters-count>{{ $active > 0 ? trans_choice('admin.filters.active', $active, ['count' => $active]) : '' }}</p>
</form>
