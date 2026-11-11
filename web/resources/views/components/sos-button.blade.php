{{--
    Bouton SOS (COMPOSANTS §x-sos-button ; .xm-sos rouge, Montserrat 800, social-xomiton.css ; Q21 b : interaction du design
    system, écart volontaire au prototype sos.php consigné dans design-system/DECISIONS.md) :
    maintien 2 s (anneau de progression, data-holding="true") → compte à rebours 5 s annulable (« Annuler » 52 px, haut
    contraste) → envoi (évènement Alpine `action`). Alternatives : Espace maintenue 2 s ; double appui, Entrée ou
    activation sans pointeur (lecteur d'écran) puis confirmation. Retour : vibration courte, annonce aria-live="assertive".
    Composant Alpine xmSos : resources/js/public/sos-button.js. Position fixe z-index 1060 (D8, FRONT_END_LAYOUT §7 :
    au-dessus des modales 1055 ; les toasts, à 1090, sont en haut à droite et ne le masquent jamais).
    Props :
      action  : nom de l'évènement déclenché à l'envoi (ex. 'sos-send') ; null = démonstration
      fixed   : true (défaut) → position fixe en bas à droite (bouton flottant) ; false → bouton en ligne
      id      : identifiant du bouton (défaut xmSos)
      size    : md (64 px, défaut) | lg (96 px, page /sos)
      managed : true → l'hôte envoie et rend compte (resolve) ; le panneau montre « Envoi… » puis son résultat
      panel   : float (défaut, au-dessus du bouton) | inline (sous le bouton, grand décompte : page /sos)
      confirm : texte de la confirmation (double appui, Entrée) : ce que l'envoi transmet vraiment (défaut : générique,
                sans promettre de position ni de message ; bouton flottant : sans position)
      confirmBind : expression JavaScript évaluée dans la portée Alpine parente à chaque affichage de la confirmation
                (page /sos : selon la case « Partager ma position ») ; texte développeur, jamais de guillemet double
    Panneau : x-trap sans focus automatique ni retour (noautofocus, noreturn) ; le focus va sur le titre, jamais sur
    « Annuler » : une touche Espace encore maintenue ne l'active pas au relâchement.
--}}
@props(['action' => null, 'fixed' => true, 'id' => 'xmSos', 'size' => 'md', 'managed' => false, 'panel' => 'float', 'confirm' => null, 'confirmBind' => null])
@php
    $inline = $panel === 'inline';
    $config = [
        'action' => $action,
        'hold' => (int) config('xomiton.alerts.sos_hold_ms', 2000),
        'countdown' => (int) config('xomiton.alerts.sos_countdown_seconds', 5),
        'managed' => (bool) $managed,
        'floating' => (bool) $fixed,
        'texts' => [
            'sent' => __('components.sos.sent'),
            'sentText' => __('components.sos.sent_text'),
            'countdown' => __('components.sos.countdown'),
            'countdownTitle' => __('components.sos.countdown_title'),
            'confirmTitle' => __('components.sos.confirm_title'),
            'confirmText' => $confirm ?? __('components.sos.confirm_text'),
            'sending' => __('components.sos.sending'),
            'cancelled' => __('components.sos.cancelled'),
        ],
    ];
    $data = \Illuminate\Support\Js::from($config)->toHtml();
@endphp
<div x-data="xmSos({!! $confirmBind ? 'Object.assign('.$data.', { confirm: () => ('.$confirmBind.') })' : $data !!})"
     {{ $attributes->class(['xm-sos-wrap no-print', 'xm-sos-wrap--fixed' => $fixed, 'xm-sos-wrap--inline' => $inline, 'position-relative d-inline-block' => ! $fixed && ! $inline]) }}>
    <button type="button" @class(['xm-sos', 'xm-sos--lg' => $size === 'lg']) id="{{ $id }}" x-bind:data-holding="holding ? 'true' : 'false'" data-holding="false"
            aria-describedby="{{ $id }}Help" aria-haspopup="dialog" x-bind:aria-expanded="open ? 'true' : 'false'" aria-expanded="false"
            x-on:pointerdown.prevent="start()" x-on:pointerup="release()" x-on:pointerleave="cancelHold()" x-on:pointercancel="cancelHold()" x-on:click="activate($event)"
            x-on:keydown.space.prevent="startKey()" x-on:keyup.space.prevent="cancelHold()" x-on:keydown.enter.prevent="ask()" x-on:contextmenu.prevent>{{ __('components.sos.label') }}</button>
    <span id="{{ $id }}Help" class="visually-hidden">{{ __('components.sos.help') }}</span>

    <div x-show="open" x-cloak x-trap.noscroll.noautofocus.noreturn="open" x-on:keydown.escape.window="escape()"
         @class(['card shadow-lg xm-sos-panel', 'xm-sos-panel--inline' => $inline]) role="alertdialog" aria-modal="true" aria-labelledby="{{ $id }}Title" aria-describedby="{{ $id }}Desc">
        <div class="card-body">
            <h2 id="{{ $id }}Title" class="h6 mb-1" tabindex="-1" data-sos-title x-text="panelTitle()"></h2>
            <p id="{{ $id }}Desc" class="small text-secondary mb-2" x-text="panelText()"></p>
            <p class="xm-sos-count fw-bold text-center text-danger my-2" x-show="counting" x-text="left" aria-hidden="true"></p>
            <p class="text-center my-2" x-show="sending"><span class="spinner-border text-danger" aria-hidden="true"></span></p>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-dark btn-lg flex-fill" x-show="confirming || counting" x-on:click="abort()">{{ __('components.sos.cancel') }}</button>
                <button type="button" class="btn btn-secondary btn-lg flex-fill" x-show="confirming" x-on:click="begin()"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ __('components.sos.send') }}</button>
                <a class="btn btn-primary flex-fill" x-show="done && result && result.call" x-bind:href="result && result.call ? result.call.href : null"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i><span x-text="result && result.call ? result.call.label : ''"></span></a>
                <a class="btn btn-light flex-fill" x-show="done && result && result.link" x-bind:href="result && result.link ? result.link.href : null" x-text="result && result.link ? result.link.label : ''"></a>
                <button type="button" class="btn btn-primary flex-fill" x-show="done && ! managed" x-on:click="reset()">{{ __('components.sos.safe') }}</button>
                <button type="button" class="btn btn-light flex-fill" x-show="done && managed" x-on:click="reset()">{{ __('common.close') }}</button>
            </div>
        </div>
    </div>
    <p class="visually-hidden" aria-live="assertive" aria-atomic="true" x-text="message"></p>
</div>
