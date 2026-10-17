{{--
    Bouton SOS (COMPOSANTS §x-sos-button ; .xm-sos 64 px rouge, Montserrat 800, social-xomiton.css) :
    maintien 2 s (anneau de progression, data-holding="true") → compte à rebours 5 s annulable → envoi (évènement Alpine `action`).
    Alternatives accessibles : Espace maintenue 2 s, ou Entrée puis confirmation. Retour : vibration courte, « Ton alerte est partie. » en aria-live="assertive".
    Composant Alpine xmSos : resources/js/public/xomiton-ui.js. Position fixe z-index 1040 (comme .xm-sos-fab du prototype : au-dessus du contenu, sous les modales).
    Props :
      action : nom de l'évènement déclenché à l'envoi (ex. 'sos-send', écouté par Livewire ou Vue) ; null = démonstration
      fixed  : true (défaut) → position fixe en bas à droite ; false → bouton en ligne (barre d'onglets, catalogue)
      id     : identifiant du bouton (défaut xmSos)
--}}
@props(['action' => null, 'fixed' => true, 'id' => 'xmSos'])
<div x-data="xmSos(@js(['action' => $action, 'hold' => 2000, 'countdown' => 5, 'texts' => ['sent' => __('components.sos.sent'), 'countdown' => __('components.sos.countdown'), 'cancelled' => __('components.sos.cancelled')]]))"
     {{ $attributes->class(['xm-sos-wrap no-print', 'xm-sos-wrap--fixed' => $fixed, 'position-relative d-inline-block' => ! $fixed]) }}>
    <button type="button" class="xm-sos" id="{{ $id }}" x-bind:data-holding="holding ? 'true' : 'false'" data-holding="false"
            aria-describedby="{{ $id }}Help" aria-haspopup="dialog" x-bind:aria-expanded="(confirming || counting || done) ? 'true' : 'false'" aria-expanded="false"
            x-on:pointerdown.prevent="start()" x-on:pointerup="cancelHold()" x-on:pointerleave="cancelHold()" x-on:pointercancel="cancelHold()"
            x-on:keydown.space.prevent="startKey()" x-on:keyup.space.prevent="cancelHold()" x-on:keydown.enter.prevent="ask()" x-on:contextmenu.prevent>{{ __('components.sos.label') }}</button>
    <span id="{{ $id }}Help" class="visually-hidden">{{ __('components.sos.help') }}</span>

    <div x-show="confirming || counting || done" x-cloak x-trap.noscroll="confirming || counting || done" x-on:keydown.escape.window="if (confirming || counting) abort()"
         class="card shadow-lg xm-sos-panel" role="alertdialog" aria-modal="true" aria-labelledby="{{ $id }}Title" aria-describedby="{{ $id }}Desc">
        <div class="card-body">
            <h2 id="{{ $id }}Title" class="h6 mb-1" x-text="done ? @js(__('components.sos.sent')) : (counting ? @js(__('components.sos.countdown_title')) : @js(__('components.sos.confirm_title')))"></h2>
            <p id="{{ $id }}Desc" class="small text-secondary mb-2" x-text="done ? @js(__('components.sos.sent_text')) : (counting ? countdownText() : @js(__('components.sos.confirm_text')))"></p>
            <p class="xm-sos-count display-4 fw-bold text-center text-danger my-2" x-show="counting" x-text="left" aria-hidden="true"></p>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light flex-fill" x-show="! done" x-on:click="abort()">{{ __('components.sos.cancel') }}</button>
                <button type="button" class="btn btn-secondary flex-fill" x-show="confirming" x-on:click="begin()"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('components.sos.send') }}</button>
                <button type="button" class="btn btn-primary flex-fill" x-show="done" x-on:click="reset()">{{ __('components.sos.safe') }}</button>
            </div>
        </div>
    </div>
    <p class="visually-hidden" aria-live="assertive" aria-atomic="true" x-text="message"></p>
</div>
