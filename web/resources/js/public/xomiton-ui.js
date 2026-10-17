// Composants Alpine de Xomiton. Chargé après Alpine (livré avec Livewire 3).
// Inscription : document.addEventListener('alpine:init', ...) — aucun état n'est stocké hors de la page.
document.addEventListener('alpine:init', () => {
  window.Alpine.data('xmSos', (action) => ({
    holding: false, confirming: false, counting: false, left: 5, message: '', hold: null, tick: null,
    start() { this.holding = true; this.hold = setTimeout(() => { this.holding = false; this.begin(); }, 2000); },
    cancelHold() { clearTimeout(this.hold); this.holding = false; },
    begin() {
      this.confirming = false; this.counting = true; this.left = 5;
      navigator.vibrate && navigator.vibrate(80);
      this.tick = setInterval(() => { if (--this.left <= 0) this.send(); }, 1000);
    },
    abort() { clearInterval(this.tick); this.counting = this.confirming = false; this.message = ''; },
    send() {
      clearInterval(this.tick); this.counting = false;
      this.message = 'Ton alerte est partie';
      if (action) this.$dispatch(action); // l'envoi réel est fait côté Livewire/serveur
    },
  }));

});
