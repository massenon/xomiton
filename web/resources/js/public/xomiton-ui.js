// Comportements d'interface des composants x-* (prototype assets/js/xomiton.js et xomiton-admin.js, portés sur Bootstrap 5.3 ESM).
// Chargé par les paquets public (Alpine démarré après cet import) et administration (Alpine fourni par Livewire).
// Aucun état n'est stocké hors de la page : le marquage « lu » et le tri réels viennent du serveur (Livewire, API).
import { Tab, Toast } from '../shared/bootstrap.js';
import { bindDismissible } from '../shared/dismissible.js';
import './sos-button.js';

const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn());
window.XM = window.XM || {};

/* ---------- Toasts (<x-toast>) : Bootstrap Toast, coin supérieur droit, 4,5 s, aria-live ---------- */
const TOAST_DELAY = 4500;
const showToast = (el) => {
  const t = new Toast(el, { delay: TOAST_DELAY });
  el.addEventListener('hidden.bs.toast', () => el.remove());
  t.show();
  return t;
};
window.XM.toast = (message, type = 'primary') => {
  let wrap = document.getElementById('xmToastWrap');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.id = 'xmToastWrap';
    wrap.className = 'toast-container position-fixed top-0 end-0 p-3';
    document.body.appendChild(wrap);
  }
  const urgent = type === 'danger';
  const el = document.createElement('div');
  el.className = `toast align-items-center text-bg-${type} border-0`;
  el.setAttribute('role', urgent ? 'alert' : 'status');
  el.setAttribute('aria-live', urgent ? 'assertive' : 'polite');
  el.setAttribute('aria-atomic', 'true');
  el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
  el.querySelector('.toast-body').textContent = message;
  el.querySelector('.btn-close').setAttribute('aria-label', wrap.dataset.xmCloseLabel || 'Fermer');
  wrap.appendChild(el);
  return showToast(el);
};
window.addEventListener('toast', (e) => {
  const d = e.detail || {};
  if (d.message) window.XM.toast(d.message, d.type || 'primary');
});
ready(() => document.querySelectorAll('[data-xm-toast-autoshow]').forEach(showToast));

/* ---------- Bandeaux refermables mémorisés pour la session (annonce de maintenance, P13-08) ---------- */
ready(() => bindDismissible(document));

/* ---------- Modales (<x-modal>) : retour du focus au déclencheur, mot de confirmation, garde « formulaire modifié » ---------- */
const syncConfirmWord = (modal) => {
  const word = modal.dataset.xmConfirmWord;
  if (!word) return;
  const input = modal.querySelector('[data-xm-confirm-input]');
  const ok = !!input && input.value.trim() === word;
  modal.querySelectorAll('[data-xm-modal-action]').forEach((b) => { b.disabled = !ok; });
};
document.addEventListener('show.bs.modal', (e) => {
  const modal = e.target;
  modal._xmOpener = e.relatedTarget || document.activeElement;
  modal._xmDirty = false;
  syncConfirmWord(modal);
});
document.addEventListener('hide.bs.modal', (e) => {
  const modal = e.target;
  if (modal.dataset.xmDirtyGuard !== undefined && modal._xmDirty && !window.confirm(modal.dataset.xmDirtyGuard)) e.preventDefault();
});
document.addEventListener('hidden.bs.modal', (e) => {
  const modal = e.target;
  const input = modal.querySelector('[data-xm-confirm-input]');
  if (input) { input.value = ''; syncConfirmWord(modal); }
  if (modal._xmOpener && typeof modal._xmOpener.focus === 'function' && document.contains(modal._xmOpener)) modal._xmOpener.focus();
});
document.addEventListener('input', (e) => {
  const modal = e.target.closest && e.target.closest('.modal');
  if (!modal) return;
  if (e.target.hasAttribute('data-xm-confirm-input')) syncConfirmWord(modal);
  else if (modal.dataset.xmDirtyGuard !== undefined) modal._xmDirty = true;
});

/* ---------- Onglets (<x-tabs>) : onglet actif mémorisé dans l'URL (?tab=) ---------- */
ready(() => {
  document.querySelectorAll('[data-xm-tabs]').forEach((root) => {
    const param = root.dataset.xmTabs || 'tab';
    const wanted = new URLSearchParams(location.search).get(param);
    if (wanted) {
      const btn = Array.from(root.querySelectorAll('[data-xm-tab]')).find((b) => b.dataset.xmTab === wanted);
      if (btn && !btn.classList.contains('active')) Tab.getOrCreateInstance(btn).show();
    }
    root.addEventListener('shown.bs.tab', (e) => {
      const key = e.target.dataset.xmTab;
      if (!key) return;
      const url = new URL(location.href);
      url.searchParams.set(param, key);
      history.replaceState(null, '', url);
    });
  });
});

/* ---------- Cloche (<x-bell>) : filtres Non lus / Lues / Toutes, compteur, « Tout marquer comme lu » (état local, en attente du polling Livewire) ---------- */
ready(() => {
  document.querySelectorAll('[data-xm-bell]').forEach((bell) => {
    let tab = 'unread';
    const items = () => Array.from(bell.querySelectorAll('[data-notif-id]'));
    const refresh = () => {
      let unread = 0;
      let shown = 0;
      items().forEach((a) => {
        const read = a.dataset.notifRead === '1';
        if (!read) unread += 1;
        a.classList.toggle('xm-notif-unread', !read);
        const show = tab === 'all' || (tab === 'unread' ? !read : read);
        a.classList.toggle('d-none', !show);
        if (show) shown += 1;
      });
      const empty = bell.querySelector('[data-xm-bell-empty]');
      if (empty) empty.classList.toggle('d-none', shown > 0);
      const count = bell.querySelector('[data-xm-bell-count]');
      if (count && count.firstChild) { count.firstChild.nodeValue = String(unread); count.classList.toggle('d-none', unread === 0); }
    };
    bell.querySelectorAll('[data-xm-bell-tab]').forEach((b) => b.addEventListener('click', () => {
      tab = b.dataset.xmBellTab;
      bell.querySelectorAll('[data-xm-bell-tab]').forEach((x) => {
        const on = x === b;
        x.classList.toggle('active', on);
        x.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      refresh();
    }));
    const readAll = bell.querySelector('[data-xm-bell-read-all]');
    if (readAll) readAll.addEventListener('click', () => { items().forEach((a) => { a.dataset.notifRead = '1'; }); refresh(); });
    items().forEach((a) => a.addEventListener('click', () => { a.dataset.notifRead = '1'; }));
    refresh();
  });
});

/* ---------- SOS (<x-sos-button>) : composant Alpine xmSos, module ./sos-button.js (P5-05, P5-08) ---------- */
