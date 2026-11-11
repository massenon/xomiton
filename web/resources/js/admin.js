// Paquet de l'administration (Livewire 3, ADR-18, P10-02) : Bootstrap + SweetAlert2 + écouteurs seulement (toasts,
// confirmations, modales, coquille). Alpine est fourni par Livewire : ne pas le réimporter. Chart.js, Quill, Choices et
// flatpickr ne sont **jamais** importés ici : chaque écran les charge à la demande (admin/lazy.js, `XM.admin.load()`),
// contrôle au build par scripts/check-budgets.mjs.
// Bootstrap complet : composants partagés avec le public (shared/bootstrap.js) + ceux propres aux écrans Livewire.
import './shared/bootstrap.js';
import 'bootstrap/js/src/button.js';
import 'bootstrap/js/src/carousel.js';
import 'bootstrap/js/src/popover.js';
import 'bootstrap/js/src/scrollspy.js';
import 'bootstrap/js/src/tooltip.js';
import Swal from 'sweetalert2';
import './shared/theme.js';
import './public/xomiton-ui.js';
import { bindToasts, fireToast } from './admin/toasts.js';
import { bindConfirm, confirmDialog } from './admin/confirm.js';
import { bindModals } from './admin/modals.js';
import { bindShell, bindPageShell, bindSessionExpiry } from './admin/shell.js';
import { bindNavigation, currentGeneration, registerTeardown } from './admin/navigation.js';
import { Offcanvas } from './shared/bootstrap.js';
import { load, preload, LIBRARIES } from './admin/lazy.js';

window.Swal = Swal;
window.XM = window.XM || {};
// Scripts d'écran à la demande (resources/js/admin/screens/<nom>.js, un chunk chacun) : un élément `data-xm-screen="nom"`
// de la page déclenche l'import de son script, dont l'export par défaut monte l'écran (graphiques, sons…).
const screens = import.meta.glob(['./admin/screens/*.js', '!./admin/screens/*.test.js']);
const screen = (name) => {
  const loader = screens[`./admin/screens/${name}.js`];
  return loader ? loader().then((m) => (typeof m.default === 'function' ? m.default() : null)) : Promise.reject(new Error(`Écran inconnu : ${name}`));
};
window.XM.admin = { load, preload, screen, libraries: LIBRARIES, toast: fireToast, confirm: confirmDialog };

// Avant le démarrage de Livewire (livewire:init, à DOMContentLoaded) : wire:confirm passe par la modale rouge.
bindConfirm();
bindModals();
bindSessionExpiry();

const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn());

// Écrans de la page : l'export par défaut d'un script d'écran peut renvoyer son démontage (écouteurs retirés, graphiques
// détruits), appelé sur `livewire:navigating` avant que Livewire ne remplace le <body> (navigation sans rechargement).
const mountScreens = () => {
  preload();
  const gen = currentGeneration();
  document.querySelectorAll('[data-xm-screen]').forEach((el) => {
    screen(el.dataset.xmScreen).then((teardown) => registerTeardown(teardown, gen)).catch(() => {});
  });
};

// Ce qui ne survit pas à l'échange du <body> : tiroir de la barre latérale (< 992 px, son fond disparaît avec le <body>) et toast.
const closeOverlays = () => {
  const sidebar = document.getElementById('xmSidebar');
  const offcanvas = sidebar ? Offcanvas.getInstance(sidebar) : null;
  if (offcanvas) offcanvas.hide();
  if (Swal.isVisible()) Swal.close();
};

bindNavigation({
  closeOverlays,
  mount: () => {
    bindToasts();
    bindPageShell();
    mountScreens();
  },
});

ready(() => {
  bindToasts();
  bindShell();
  mountScreens();
});
