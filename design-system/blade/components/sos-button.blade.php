{{-- Maintien 2 s (pointeur ou Espace) -> compte à rebours 5 s annulable -> envoi. Alternatives : double appui, Entrée ou activation sans pointeur (lecteur d'écran), puis confirmation. Panneau : focus sur son titre (x-trap.noautofocus), jamais sur « Annuler ». z-index 1060 : au-dessus des modales (D8, Q21 b). Version complète (envoi géré par l'hôte, textes lang/fr) : web/resources/views/components/sos-button.blade.php. --}}
@props(['action' => null])  {{-- $action : méthode Livewire ou nom d'évènement Alpine déclenché à l'envoi --}}
<div x-data="xmSos(@js($action))" class="position-fixed" style="right:16px;bottom:16px;z-index:1060">
    <button type="button" class="xm-sos" x-bind:data-holding="holding" aria-describedby="sos-help" aria-haspopup="dialog"
            @pointerdown.prevent="start()" @pointerup="release()" @pointerleave="cancelHold()" @pointercancel="cancelHold()" @click="activate($event)"
            @keydown.space.prevent="startKey()" @keyup.space.prevent="cancelHold()" @keydown.enter.prevent="ask()" @contextmenu.prevent>SOS</button>
    <span id="sos-help" class="visually-hidden">{{ __('Maintiens le bouton 2 secondes. Sinon, appuie deux fois ou sur Entrée, puis confirme. Tu pourras annuler pendant 5 secondes.') }}</span>

    <div x-show="confirming || counting" x-cloak class="xm-card position-absolute bottom-100 end-0 mb-3" style="width:min(300px,calc(100vw - 32px))" role="alertdialog" aria-modal="true" aria-labelledby="sos-t" x-trap.noautofocus.noreturn="confirming || counting" @keydown.escape.window="abort()">
        <h2 id="sos-t" class="h6" tabindex="-1" data-sos-title x-text="counting ? @js(__('Envoi dans')) + ' ' + left + ' s' : @js(__('Envoyer une alerte SOS ?'))"></h2>
        <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-dark flex-fill" @click="abort()">{{ __('Annuler') }}</button>
            <button type="button" class="btn btn-secondary flex-fill" x-show="! counting" @click="begin()">{{ __('Envoyer') }}</button>
        </div>
    </div>
    <p class="visually-hidden" aria-live="assertive" x-text="message"></p>
</div>
