{{--
    État vide d'un tableau d'administration (P10-02, UX_GUIDELINES §3, UI_UX_DESIGN_SYSTEM.md §4.2) : x-empty-state
    compact. Avec des filtres actifs : « Aucun élément ne correspond aux filtres. » + « Réinitialiser les filtres »
    (resetFilters du trait WithDataTable) ; sinon la phrase de l'écran et son action (slot action, « + Nouveau… »).
    Props : title (phrase sans filtre), icon (défaut inbox), filtered (filtres actifs).
    Slots : défaut (explication), action.
--}}
@props(['title' => null, 'icon' => 'inbox', 'filtered' => false])
@if ($filtered)
    <x-empty-state compact icon="funnel" :title="__('admin.datatable.empty_filtered')" :attributes="$attributes">
        <x-slot:action><button type="button" class="btn btn-sm btn-light border" wire:click="resetFilters"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ __('admin.filters.reset_all') }}</button></x-slot:action>
    </x-empty-state>
@elseif (isset($action))
    <x-empty-state compact :icon="$icon" :title="$title ?? __('admin.datatable.empty')" :attributes="$attributes">{{ $slot }}<x-slot:action>{{ $action }}</x-slot:action></x-empty-state>
@else
    <x-empty-state compact :icon="$icon" :title="$title ?? __('admin.datatable.empty')" :attributes="$attributes">{{ $slot }}</x-empty-state>
@endif
