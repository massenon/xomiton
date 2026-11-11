{{--
    Barre d'actions groupées (P10-02, UI_UX_DESIGN_SYSTEM.md §4.2) : visible dès qu'au moins une ligne est cochée
    (cases `wire:model.live="selected"` du trait WithDataTable), compteur annoncé, choix de l'action
    (`wire:model.live="bulkAction"` : le serveur connaît l'action avant le clic), « Appliquer » inactif tant qu'aucune
    action n'est choisie, puis confirmation `wire:confirm` (modale rouge SweetAlert2 de P10-03) nommant l'action et le
    nombre d'éléments, et « Désélectionner ». Le message de confirmation est lu à l'initialisation du bouton : sa clé
    `wire:key` change avec l'action et le nombre, Livewire remplace donc le bouton à chaque changement.
    Props :
      count    : nombre de lignes sélectionnées (count($selected))
      actions  : [valeur => libellé] (lang admin.bulk.actions.*)
      selected : action choisie ($bulkAction du trait)
      apply    : méthode Livewire appelée (défaut applyBulk) ; confirm : message de confirmation (défaut admin.bulk.confirm)
      id       : préfixe des identifiants (défaut xmBulk)
--}}
@props(['count' => 0, 'actions' => [], 'selected' => '', 'apply' => 'applyBulk', 'confirm' => null, 'id' => 'xmBulk'])
@php($label = is_string($selected) && $selected !== '' ? ($actions[$selected] ?? null) : null)
@if ($count > 0)
    <div {{ $attributes->class('xm-bulk-bar d-flex flex-wrap align-items-center gap-2') }} role="region" aria-label="{{ __('admin.bulk.action') }}">
        <span class="fw-semibold small" role="status">{{ trans_choice('admin.bulk.selected', $count, ['count' => $count]) }}</span>
        <label class="visually-hidden" for="{{ $id }}Action">{{ __('admin.bulk.action') }}</label>
        <select class="form-select form-select-sm w-auto" id="{{ $id }}Action" wire:model.live="bulkAction">
            <option value="">{{ __('admin.bulk.choose') }}</option>
            @foreach ($actions as $value => $actionLabel)
                <option value="{{ $value }}">{{ $actionLabel }}</option>
            @endforeach
        </select>
        @if ($label === null)
            <button type="button" class="btn btn-sm btn-primary" wire:key="{{ $id }}Apply-none" disabled>{{ __('admin.bulk.apply') }}</button>
        @else
            <button type="button" class="btn btn-sm btn-primary" wire:key="{{ $id }}Apply-{{ $selected }}-{{ $count }}" wire:click="{{ $apply }}"
                    wire:confirm="{{ $confirm ?? trans_choice('admin.bulk.confirm', $count, ['action' => $label, 'count' => $count]) }}">{{ __('admin.bulk.apply') }}</button>
        @endif
        <button type="button" class="btn btn-sm btn-link px-1" wire:click="clearSelection">{{ __('admin.bulk.clear') }}</button>
    </div>
@endif
