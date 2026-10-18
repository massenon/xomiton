// Composants Alpine de Xomiton. Chargé après Alpine (livré avec Livewire 3).
// Inscription : document.addEventListener('alpine:init', ...) — aucun état n'est stocké hors de la page.
document.addEventListener('alpine:init', () => {
  // Bouton SOS (Q21 b) : maintien 2 s (pointeur ou Espace) ; double appui, Entrée ou activation sans pointeur (lecteur
  // d'écran : clic de detail 0) → confirmation. Focus du panneau sur son titre : jamais sur « Annuler », qu'une touche
  // Espace encore maintenue activerait. Référence complète (mode managed, textes lang/fr) : web/resources/js/public/sos-button.js.
  window.Alpine.data('xmSos', (action) => ({
    holding: false, confirming: false, counting: false, left: 5, message: '', hold: null, tick: null, keyHeld: false, downAt: 0, lastTap: 0,
    start() {
      if (this.confirming || this.counting) return;
      this.holding = true; this.downAt = Date.now();
      this.hold = setTimeout(() => { this.holding = false; this.keyHeld = false; this.lastTap = 0; this.begin(); }, 2000);
    },
    startKey() { if (!this.keyHeld) { this.keyHeld = true; this.start(); } },
    release() {
      const tap = this.holding && Date.now() - this.downAt < 350;
      this.cancelHold();
      if (!tap || this.confirming || this.counting) return;
      const at = Date.now();
      if (this.lastTap && at - this.lastTap <= 450) { this.lastTap = 0; this.ask(); } else { this.lastTap = at; }
    },
    activate(event) { if (event && event.detail === 0) this.ask(); },
    cancelHold() { clearTimeout(this.hold); this.holding = false; this.keyHeld = false; },
    ask() { if (this.counting) return; this.cancelHold(); this.confirming = true; this.focusTitle(); },
    begin() {
      this.confirming = false; this.counting = true; this.left = 5;
      this.focusTitle();
      navigator.vibrate && navigator.vibrate(80);
      this.tick = setInterval(() => { if (--this.left <= 0) this.send(); }, 1000);
    },
    focusTitle() { this.$nextTick(() => { const t = this.$root.querySelector('[data-sos-title]'); if (t) t.focus(); }); },
    abort() {
      clearInterval(this.tick); this.cancelHold(); this.counting = this.confirming = false; this.message = '';
      this.$nextTick(() => { const b = this.$root.querySelector('.xm-sos'); if (b) b.focus(); });
    },
    send() {
      clearInterval(this.tick); this.counting = false;
      this.message = 'Ton alerte est partie';
      if (action) this.$dispatch(action); // l'envoi réel est fait côté Livewire/serveur
    },
  }));

});
