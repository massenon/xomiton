{{-- Maintien 2 s -> compte à rebours 5 s annulable -> envoi. Alternatives : Entrée puis confirmation. --}}
@props(['action' => null])  {{-- $action : méthode Livewire ou nom d'évènement Alpine déclenché à l'envoi --}}
<div x-data="xmSos(@js($action))" class="position-fixed" style="right:16px;bottom:16px;z-index:1040">
    <button type="button" class="xm-sos" x-bind:data-holding="holding" aria-describedby="sos-help"
            @pointerdown.prevent="start()" @pointerup="cancelHold()" @pointerleave="cancelHold()"
            @keydown.enter.prevent="confirming = true">SOS</button>
    <span id="sos-help" class="visually-hidden">{{ __('Maintiens 2 secondes, ou appuie sur Entrée pour confirmer. Tu pourras annuler pendant 5 secondes.') }}</span>

    <div x-show="confirming || counting" x-cloak class="xm-card position-absolute bottom-100 end-0 mb-3" style="width:min(300px,calc(100vw - 32px))" role="alertdialog" aria-labelledby="sos-t" x-trap="confirming || counting">
        <h2 id="sos-t" class="h6" x-text="counting ? @js(__('Envoi dans')) + ' ' + left + ' s' : @js(__('Envoyer une alerte SOS ?'))"></h2>
        <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn btn-light flex-fill" @click="abort()">{{ __('Annuler') }}</button>
            <button type="button" class="btn btn-secondary flex-fill" x-show="! counting" @click="begin()">{{ __('Envoyer') }}</button>
        </div>
    </div>
    <p class="visually-hidden" aria-live="assertive" x-text="message"></p>
</div>
