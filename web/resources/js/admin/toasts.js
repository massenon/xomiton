// Toasts de l'administration (P10-03, UI_UX_DESIGN_SYSTEM.md §4.6, NOTIFICATIONS_INSTRUCTION.md §5) : SweetAlert2 en
// mode toast, coin supérieur droit, 4,5 s, barre de progression, pause au survol, bouton de fermeture. Déclenchés par
// `$this->dispatch('toast', type: …, message: …)` (InteractsWithUi) via l'écouteur unique de public/xomiton-ui.js, et
// par un message flashé avant une redirection (<template data-xm-flash-toast>). Annonce : role="status" +
// aria-live="polite", role="alert" + aria-live="assertive" pour une erreur. Le texte est inséré comme texte (titleText).
// SweetAlert2 n'affiche qu'une fenêtre à la fois : un toast arrivé pendant une confirmation ouverte (wire:confirm,
// polling de la cloche ou des alertes) la fermerait et l'action serait annulée ; il attend donc sa fermeture.
import Swal from 'sweetalert2';
import { adminI18n } from './i18n.js';

export const TOAST_DELAY = 4500;

/** Intervalle de vérification de la file pendant qu'une fenêtre SweetAlert2 (hors toast) est ouverte. */
export const TOAST_QUEUE_RETRY = 300;

const queue = [];
let retry = null;

/** Vrai si une fenêtre SweetAlert2 autre qu'un toast (confirmation, saisie) est ouverte. */
export function swalBusy(swal = Swal) {
  if (typeof swal.isVisible !== 'function' || !swal.isVisible()) return false;
  const popup = typeof swal.getPopup === 'function' ? swal.getPopup() : null;
  return !(popup && popup.classList && popup.classList.contains('swal2-toast'));
}

function drain(swal) {
  if (swalBusy(swal)) {
    retry = setTimeout(() => drain(swal), TOAST_QUEUE_RETRY);
    return;
  }
  retry = null;
  queue.splice(0).forEach(([message, type]) => fireToast(message, type, swal));
}

/** Vide la file d'attente (tests). */
export function resetToastQueue() {
  queue.length = 0;
  if (retry) clearTimeout(retry);
  retry = null;
}

const ICONS = { success: 'success', info: 'info', primary: 'info', warning: 'warning', error: 'error', danger: 'error' };

export const toastIcon = (type) => ICONS[type] || 'success';

export function fireToast(message, type = 'success', swal = Swal) {
  if (!message) return null;
  if (swalBusy(swal)) {
    queue.push([message, type]);
    if (!retry) retry = setTimeout(() => drain(swal), TOAST_QUEUE_RETRY);
    return null;
  }
  const icon = toastIcon(type);
  const urgent = icon === 'error';
  return swal.fire({
    toast: true,
    position: 'top-end',
    icon,
    titleText: String(message),
    showConfirmButton: false,
    showCloseButton: true,
    closeButtonAriaLabel: adminI18n().close,
    timer: TOAST_DELAY,
    timerProgressBar: true,
    customClass: { popup: 'xm-swal-toast' },
    didOpen: (popup) => {
      popup.setAttribute('role', urgent ? 'alert' : 'status');
      popup.setAttribute('aria-live', urgent ? 'assertive' : 'polite');
      popup.addEventListener('mouseenter', swal.stopTimer);
      popup.addEventListener('mouseleave', swal.resumeTimer);
      popup.addEventListener('focusin', swal.stopTimer);
      popup.addEventListener('focusout', swal.resumeTimer);
    },
  });
}

/** Branche les toasts : afficheur de window.XM et message flashé de la page. */
export function bindToasts(doc = document, swal = Swal) {
  window.XM = window.XM || {};
  window.XM.toastHandler = (detail) => fireToast(detail && detail.message, detail && detail.type, swal);
  const flash = doc.querySelector('template[data-xm-flash-toast]');
  if (flash) {
    const text = (flash.content ? flash.content.textContent : flash.textContent) || '';
    flash.remove();
    if (text.trim()) fireToast(text.trim(), flash.dataset.type || 'success', swal);
  }
}
