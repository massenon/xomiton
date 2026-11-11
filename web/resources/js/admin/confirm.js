// Confirmations de l'administration (P10-03, UI_UX_DESIGN_SYSTEM.md §4.3 et §4.6) : chaque `wire:confirm="Message"`
// s'ouvre dans une modale SweetAlert2 rouge (icône d'avertissement, bouton de confirmation btn-danger, « Annuler » à
// gauche et focus dessus) au lieu du confirm() du navigateur ; `wire:confirm.prompt="Question|MOT"` exige la saisie du
// mot (confirmation renforcée). Mécanisme : Livewire pose el.__livewire_confirm(action, instead) à l'initialisation
// de la directive ; notre crochet `directive.init`, enregistré après le sien, le remplace par la version asynchrone.
import Swal from 'sweetalert2';
import { adminI18n } from './i18n.js';

/** Message et mot attendu d'une directive wire:confirm (mêmes règles que Livewire : « \n », « Question|MOT »). */
export function parseConfirm(expression, modifiers = []) {
  let message = String(expression || '').replaceAll('\\n', '\n');
  let expected = null;
  if (modifiers.includes('prompt')) {
    const [question, word] = message.split('|');
    message = question || '';
    expected = word ? word.trim() : null;
  }
  return { message, expected };
}

export function confirmDialog({ message, expected = null }, swal = Swal) {
  const t = adminI18n();
  const options = {
    icon: 'warning',
    titleText: t.confirmTitle,
    text: message || t.confirmTitle,
    showCancelButton: true,
    confirmButtonText: t.confirm,
    cancelButtonText: t.cancel,
    focusCancel: true,
    reverseButtons: true,
    buttonsStyling: false,
    customClass: { popup: 'xm-swal xm-swal--danger', confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light me-2', actions: 'gap-2' },
  };
  // Depuis une modale Bootstrap ouverte (x-admin.modal) : la confirmation s'affiche dans la modale, sinon le piège du
  // focus de Bootstrap reprendrait le focus à SweetAlert2.
  const openModal = typeof document !== 'undefined' ? document.querySelector('.modal.show') : null;
  if (openModal) options.target = openModal;
  if (expected) {
    options.input = 'text';
    options.inputLabel = t.prompt.replace(':word', expected);
    options.inputAttributes = { autocomplete: 'off', autocapitalize: 'off', spellcheck: 'false', 'aria-label': t.promptLabel || t.prompt.replace(':word', expected) };
    options.inputValidator = (value) => ((value || '').trim() === expected ? undefined : t.mismatch || t.prompt.replace(':word', expected));
  }
  return swal.fire(options).then((result) => Boolean(result && result.isConfirmed));
}

/** Remplace le confirm() natif de wire:confirm par la modale (à appeler avant le démarrage de Livewire). */
export function bindConfirm(win = window, swal = Swal) {
  let done = false;
  const register = () => {
    if (done || !win.Livewire || typeof win.Livewire.hook !== 'function') return;
    done = true;
    win.Livewire.hook('directive.init', ({ el, directive }) => {
      if (!directive || directive.value !== 'confirm') return;
      const parsed = parseConfirm(directive.expression, directive.modifiers || []);
      el.__livewire_confirm = (action, instead) => {
        confirmDialog(parsed, swal).then((ok) => (ok ? action() : instead()));
      };
    });
  };
  if (win.Livewire) register();
  else win.document.addEventListener('livewire:init', register, { once: true });
}
