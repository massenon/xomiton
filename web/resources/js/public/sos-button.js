// Bouton SOS (<x-sos-button>, P5-05 / P5-08) : composant Alpine xmSos, sorti de xomiton-ui.js pour être testé (Vitest).
// Maintien 2 s (pointeur ou touche Espace) → compte à rebours 5 s annulable → envoi (évènement `action`, Q39 : rien ne
// part pendant le décompte). Alternatives (signaler-et-sos.md l.19, FR-ALT-03) : double appui puis confirmation, Entrée
// puis confirmation, activation sans pointeur (lecteur d'écran, commande vocale : clic de `detail` 0) puis confirmation.
// Mode `managed` (page /sos, bouton flottant) : l'envoi est confié à l'hôte, qui reçoit `{ resolve }` dans l'évènement et
// rappelle `resolve({ state, title, text, call, link })` : `idle` referme le panneau, sinon le panneau montre le résultat.
// Bouton flottant (`floating`) : ouvrir le panneau ferme d'abord les modales et offcanvas Bootstrap ouverts, dont le
// piège de focus lutterait avec celui du panneau (le bouton reste au-dessus, z-index 1060, D8). Ouvrir le panneau
// place le focus sur son titre (`[data-sos-title]`, x-trap.noautofocus) : jamais sur « Annuler », qu'une touche Espace
// encore maintenue activerait au relâchement. Confirmation : `opts.confirm` (fonction, prop confirmBind) ou
// `texts.confirmText`. Aucun texte ici : tout vient de Blade (lang/fr) par `opts.texts`.
import { Modal, Offcanvas } from '../shared/bootstrap.js';

/** Appui bref (en millisecondes) : au-delà, c'est un maintien interrompu, pas un appui. */
export const TAP_MAX_MS = 350;

/** Délai maximal entre les deux appuis d'un double appui. */
export const DOUBLE_TAP_MS = 450;

const now = () => Date.now();

/** Ferme les modales et offcanvas Bootstrap ouverts. */
export function closeOverlays(root = typeof document === 'undefined' ? null : document) {
  if (!root) return;
  root.querySelectorAll('.modal.show').forEach((el) => Modal.getInstance(el)?.hide());
  root.querySelectorAll('.offcanvas.show').forEach((el) => Offcanvas.getInstance(el)?.hide());
}

export function xmSos(opts = {}) {
  return {
    holding: false,
    confirming: false,
    counting: false,
    sending: false,
    done: false,
    result: null,
    left: 0,
    message: '',
    _hold: null,
    _tick: null,
    _keyHeld: false,
    _downAt: 0,
    _lastTap: 0,
    holdMs: opts.hold ?? 2000,
    seconds: opts.countdown ?? 5,
    action: opts.action ?? null,
    managed: Boolean(opts.managed),
    floating: Boolean(opts.floating),
    texts: {
      sent: '',
      sentText: '',
      countdown: '',
      countdownTitle: '',
      confirmTitle: '',
      confirmText: '',
      sending: '',
      cancelled: '',
      ...(opts.texts || {}),
    },

    get open() {
      return this.confirming || this.counting || this.sending || this.done;
    },

    start() {
      if (this.open) return;
      this.holding = true;
      this._downAt = now();
      clearTimeout(this._hold);
      this._hold = setTimeout(() => {
        this.holding = false;
        this._keyHeld = false;
        this._lastTap = 0;
        this.begin();
      }, this.holdMs);
    },
    startKey() {
      if (this._keyHeld) return; // la touche maintenue répète keydown
      this._keyHeld = true;
      this.start();
    },
    /** Relâchement du pointeur : un appui bref compte pour le double appui. */
    release() {
      const tap = this.holding && now() - this._downAt < TAP_MAX_MS;
      this.cancelHold();
      if (!tap || this.open) return;
      const at = now();
      if (this._lastTap && at - this._lastTap <= DOUBLE_TAP_MS) {
        this._lastTap = 0;
        this.ask();
      } else {
        this._lastTap = at;
      }
    },
    /** Clic sans pointeur (detail 0) : lecteur d'écran, commande vocale. Entrée et Espace sont déjà interceptés. */
    activate(event) {
      if (event && event.detail === 0) this.ask();
    },
    cancelHold() {
      clearTimeout(this._hold);
      this.holding = false;
      this._keyHeld = false;
    },
    ask() {
      if (this.counting || this.sending || this.done) return;
      this.cancelHold();
      if (this.floating) closeOverlays();
      this.confirming = true;
      this.focusPanel();
    },
    begin() {
      if (this.floating) closeOverlays();
      this.confirming = false;
      this.done = false;
      this.result = null;
      this.counting = true;
      this.left = this.seconds;
      this.message = this.countdownText();
      this.focusPanel();
      if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate(80);
      clearInterval(this._tick);
      this._tick = setInterval(() => {
        this.left -= 1;
        if (this.left <= 0) this.send();
        else this.message = this.countdownText();
      }, 1000);
    },
    countdownText() {
      return String(this.texts.countdown || '').replace(':s', String(this.left));
    },
    /** Ce que l'envoi transmet vraiment (position, zone) : fourni par l'hôte, relu à chaque affichage. */
    confirmText() {
      const text = typeof opts.confirm === 'function' ? opts.confirm() : null;
      return typeof text === 'string' && text !== '' ? text : this.texts.confirmText;
    },
    abort() {
      clearInterval(this._tick);
      this.cancelHold();
      this.counting = false;
      this.confirming = false;
      this.message = this.texts.cancelled;
      this.focusButton();
    },
    send() {
      clearInterval(this._tick);
      this.counting = false;
      if (typeof navigator !== 'undefined' && navigator.vibrate) navigator.vibrate([60, 40, 60]);
      if (this.managed) {
        // L'hôte envoie (API, dans la requête : D7) et rend compte par resolve().
        this.sending = true;
        this.message = this.texts.sending;
        this.$dispatch(this.action, { resolve: (result) => this.resolve(result) });
        return;
      }
      this.done = true;
      this.message = this.texts.sent;
      if (this.action) this.$dispatch(this.action);
    },
    resolve(result = {}) {
      this.sending = false;
      if (!result || result.state === 'idle') {
        this.done = false;
        this.result = null;
        this.message = (result && result.message) || '';
        return;
      }
      this.done = true;
      this.result = { title: '', text: '', call: null, link: null, ...result };
      this.message = [this.result.title, this.result.text].filter(Boolean).join(' ');
    },
    panelTitle() {
      if (this.done) return (this.result && this.result.title) || this.texts.sent;
      if (this.counting) return this.texts.countdownTitle;
      if (this.sending) return this.texts.sending;
      return this.texts.confirmTitle;
    },
    panelText() {
      if (this.done) return this.result ? this.result.text : this.texts.sentText;
      if (this.counting) return this.countdownText();
      if (this.sending) return '';
      return this.confirmText();
    },
    escape() {
      if (this.confirming || this.counting) this.abort();
      else if (this.done) this.reset();
    },
    reset() {
      this.done = false;
      this.result = null;
      this.message = '';
      this.focusButton();
    },
    focusButton() {
      this.$nextTick(() => {
        const b = this.$root.querySelector('.xm-sos');
        if (b) b.focus();
      });
    },
    /** Focus sur le titre du panneau (tabindex -1) : rien d'activable sous une touche encore enfoncée. */
    focusPanel() {
      if (!this.$nextTick) return;
      this.$nextTick(() => {
        const title = this.$root && this.$root.querySelector('[data-sos-title]');
        if (title) title.focus();
      });
    },
    destroy() {
      clearTimeout(this._hold);
      clearInterval(this._tick);
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmSos', xmSos);
  });
}
