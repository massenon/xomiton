{{--
    Modale d'un écran Livewire (P10-02, UI_UX_DESIGN_SYSTEM.md §4.3, Q22 : modale Bootstrap partout) : x-modal montée en
    permanence (jamais derrière un @if), conservée par Livewire entre deux rendus (wire:ignore.self : la classe `show`
    posée par Bootstrap n'est pas effacée), corps défilant et pied fixe (Annuler à gauche, action principale à droite).
    Ouverture / fermeture depuis le composant : `$this->openModal('id')` / `$this->closeModal('id')` (InteractsWithUi →
    évènement `xm-modal`, resources/js/admin/modals.js). Champs en `wire:model.blur` (validation à la sortie du champ).
    Props : celles de x-modal (id, title, type, size, scrollable, centered, action, confirmWord, dirtyGuard, static, heading).
    Slots : défaut (corps), footer (boutons, ex. <button type="submit" form="…" class="btn btn-primary">).
--}}
@props(['id', 'title', 'type' => 'form', 'size' => 'lg', 'scrollable' => true, 'centered' => true, 'action' => null, 'confirmWord' => null, 'dirtyGuard' => false, 'static' => false, 'heading' => 'h2'])
<x-modal :id="$id" :title="$title" :type="$type" :size="$size" :scrollable="$scrollable" :centered="$centered" :action="$action" :confirm-word="$confirmWord"
         :dirty-guard="$dirtyGuard" :static="$static" :heading="$heading" :attributes="$attributes->merge(['wire:ignore.self' => true, 'data-xm-livewire-modal' => true])">
    {{ $slot }}
    @isset($footer)
        <x-slot:footer>{{ $footer }}</x-slot:footer>
    @endisset
</x-modal>
