// Avis de la boîte d'envoi sur les pages Blade (P5-10), chargés à la demande avec shared/outbox.js par outbox-boot.js.
// Textes neutres reçus de Blade (`<meta name="xm-offline" data-texts>`, lang/fr/offline.php) : aucun texte en dur.
//  - région `#xmOutboxNotices` (aria-live="polite") créée vide dès le montage en tête de <main> : les avis qui s'y
//    ajoutent sont annoncés par les lecteurs d'écran ;
//  - signalement mis en file : toast ; envoyé au rejeu : avis persistant avec le code de suivi (attribué par le serveur,
//    affiché une seule fois, boutons « Copier » et « Imprimer », comme la page de confirmation de P5-04) et brouillon
//    /signaler effacé ; refusé : avis avec un lien vers le formulaire (le brouillon reste) ;
//  - SOS envoyé au rejeu : toast, sauf sur la page /sos ou avec le bouton flottant ouvert, qui l'annoncent eux-mêmes ;
//    refusé : avis d'alerte avec le lien vers les contacts d'urgence.
import { openDB } from 'idb';

// Brouillon de resources/js/public/signaler.js (DRAFT_DB, DRAFT_STORE, DRAFT_KEY) : même base, même version.
const DRAFT_DB = 'xomiton-drafts';
const DRAFT_STORE = 'drafts';
const DRAFT_KEY = 'signaler';

export function clearReportDraft(open = () => openDB(DRAFT_DB, 1, {
  upgrade(db) {
    if (!db.objectStoreNames.contains(DRAFT_STORE)) db.createObjectStore(DRAFT_STORE);
  },
})) {
  return open().then((db) => db.delete(DRAFT_STORE, DRAFT_KEY)).catch(() => undefined);
}

const el = (doc, tag, className, text) => {
  const node = doc.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;
  return node;
};

/** Région des avis, vide et déjà annoncée comme région live avant le premier avis. */
export function container(doc) {
  let wrap = doc.getElementById('xmOutboxNotices');
  if (wrap) return wrap;
  wrap = el(doc, 'div', 'container pt-3 xm-outbox-notices');
  wrap.id = 'xmOutboxNotices';
  wrap.setAttribute('aria-live', 'polite');
  wrap.setAttribute('aria-relevant', 'additions');
  const main = doc.getElementById('contenu') || doc.querySelector('main') || doc.body;
  main.insertBefore(wrap, main.firstChild);
  return wrap;
}

/** Avis persistant (x-alert) : { type, title, lines, code, link }. */
export function renderNotice(doc, texts, { type = 'success', title = '', lines = [], code = '', link = null } = {}) {
  const urgent = type === 'danger' || type === 'warning';
  const icons = { success: 'check-circle', warning: 'exclamation-triangle', danger: 'exclamation-octagon' };
  const notice = el(doc, 'div', `alert alert-${type} d-flex align-items-start gap-2 alert-dismissible fade show`);
  notice.setAttribute('role', urgent ? 'alert' : 'status');
  const icon = el(doc, 'i', `bi bi-${icons[type] || 'info-circle'} flex-shrink-0`);
  icon.setAttribute('aria-hidden', 'true');
  const body = el(doc, 'div', 'flex-grow-1');
  if (title) body.appendChild(el(doc, 'p', 'fw-bold mb-1', title));
  lines.filter(Boolean).forEach((line, i) => {
    if (i === 1 && code) {
      const value = el(doc, 'p', 'fs-4 fw-bold font-monospace mb-1 user-select-all', code);
      value.dataset.xmOutboxCode = '';
      body.appendChild(value);
      const copy = el(doc, 'button', 'btn btn-sm btn-light border mb-2', texts.copy || '');
      copy.type = 'button';
      copy.addEventListener('click', () => {
        const clip = doc.defaultView && doc.defaultView.navigator && doc.defaultView.navigator.clipboard;
        if (clip && clip.writeText) clip.writeText(code).then(() => { copy.textContent = texts.copied || copy.textContent; }).catch(() => undefined);
      });
      const print = el(doc, 'button', 'btn btn-sm btn-light border mb-2 ms-2', texts.print || '');
      print.type = 'button';
      print.addEventListener('click', () => {
        if (doc.defaultView && typeof doc.defaultView.print === 'function') doc.defaultView.print();
      });
      body.append(copy, print);
    }
    body.appendChild(el(doc, 'p', 'mb-1', line));
  });
  if (link && link.href) {
    const a = el(doc, 'a', 'alert-link', link.label || link.href);
    a.href = link.href;
    body.appendChild(a);
  }
  const close = el(doc, 'button', 'btn-close');
  close.type = 'button';
  close.setAttribute('aria-label', texts.close || '');
  close.addEventListener('click', () => notice.remove());
  notice.append(icon, body, close);
  container(doc).appendChild(notice);
  return notice;
}

const toast = (win, message, type) => {
  if (message && win.XM && typeof win.XM.toast === 'function') win.XM.toast(message, type);
};

export function mountOutboxNotices(texts = {}, opts = {}) {
  const win = opts.window || window;
  const doc = opts.document || document;
  const clearDraft = opts.clearDraft || clearReportDraft;
  if (win.__xmOutboxNotices) return win.__xmOutboxNotices;
  const ac = new AbortController();
  const on = { signal: ac.signal };
  win.__xmOutboxNotices = () => { ac.abort(); delete win.__xmOutboxNotices; };
  const sosOnScreen = () => Boolean(doc.querySelector('[x-data^="xmSosPage"], [x-data^="xmSosFab"]'));
  if (doc.getElementById('contenu') || doc.querySelector('main') || doc.body) container(doc);

  win.addEventListener('xm:outbox:queued', (event) => {
    if (event.detail && event.detail.type === 'signalement') toast(win, texts.queued, 'secondary');
  }, on);

  win.addEventListener('xm:outbox:flushed', (event) => {
    const { sent = [], failed = [] } = event.detail || {};
    sent.forEach((result) => {
      if (result.type === 'signalement') {
        const code = (result.data && (result.data.tracking_code_display || result.data.tracking_code)) || '';
        renderNotice(doc, texts, { type: 'success', title: texts.report_sent_title, lines: [texts.report_sent, texts.report_keep], code });
        clearDraft();
      } else if (result.type === 'sos' && !sosOnScreen()) {
        toast(win, texts.sos_sent, 'success');
      }
    });
    failed.forEach((result) => {
      if (result.type === 'signalement') {
        renderNotice(doc, texts, { type: 'warning', title: texts.report_failed_title, lines: [texts.report_failed], link: { href: texts.report_url, label: texts.report_resume } });
      } else if (result.type === 'sos') {
        renderNotice(doc, texts, { type: 'danger', title: texts.sos_failed_title, lines: [texts.sos_failed], link: { href: texts.urgences_url, label: texts.sos_contacts } });
      }
    });
  }, on);
  return win.__xmOutboxNotices;
}
