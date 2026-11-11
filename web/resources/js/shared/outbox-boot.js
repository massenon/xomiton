// Amorce de la boîte d'envoi (P5-10, ADR-26), importée par public.js : quelques lignes seulement dans le paquet public
// (budget < 60 Ko gzip) ; shared/outbox.js (IndexedDB) et public/outbox-notices.js (avis) sont chargés à la demande :
//  - tout de suite quand la page porte un formulaire [data-outbox] (/signaler, /sos, bouton SOS flottant) ou qu'un envoi
//    attend (`xm.outbox.pending` en localStorage, tenu par outbox.js) ;
//  - sinon au premier envoi remis (`window.XM.outbox.add`, sos.js ; évènement `xm:outbox` ; `xm:outbox-submit` de
//    signaler.js).
// Copie servie par le service worker (`<html data-xm-offline-copy>`, jeton CSRF périmé, pour toute la vie du document,
// même après le retour du réseau) ou réseau coupé : l'envoi natif d'un formulaire [data-outbox][data-outbox-endpoint]
// que son module n'a pas retenu part par la boîte d'envoi (API + Idempotency-Key), jamais par la route web (419). Une
// copie porte la clé `idempotency_key` de son préchargement, la même à chaque affichage : elle est remplacée par une clé
// neuve au démarrage (un signalement par affichage, deux affichages = deux signalements). Compte (`data-account` de la
// balise, '' sans compte ; une copie est toujours la version sans compte) et durée de vie (`data-ttl-days`) transmis à
// la boîte d'envoi. Bandeau #xmOfflineBar : `body.xm-is-offline` sur une copie (jusqu'au retour du réseau) ou hors
// connexion.
const PENDING_KEY = 'xm.outbox.pending'; // = PENDING_KEY de shared/outbox.js
const FORM = 'form[data-outbox][data-outbox-endpoint]';

const meta = (doc) => doc.querySelector('meta[name="xm-offline"]');

const readTexts = (doc) => {
  try {
    const node = meta(doc);
    return node ? JSON.parse(node.getAttribute('data-texts') || '{}') : {};
  } catch {
    return {};
  }
};

/** Empreinte du compte de la page ('' : sans compte), null sans balise (aucun tri par compte). */
const readAccount = (doc) => {
  const node = meta(doc);
  return node && node.hasAttribute('data-account') ? node.getAttribute('data-account') || '' : null;
};

/** Clé neuve ; une copie n'existe qu'en contexte sécurisé (service worker), où crypto.randomUUID est présent. */
const freshKey = (win) => (win.crypto && typeof win.crypto.randomUUID === 'function' ? win.crypto.randomUUID() : '');

/** Copie en cache : chaque formulaire reçoit une clé neuve (sinon tous les affichages partageraient la même). */
export function renewCopyKeys(doc, win = window) {
  doc.querySelectorAll(`${FORM} input[name="idempotency_key"]`).forEach((input) => { input.value = freshKey(win); });
}

const pendingCount = (storage) => {
  try {
    return Number(storage && storage.getItem(PENDING_KEY)) || 0;
  } catch {
    return 0;
  }
};

/** Entrée d'un formulaire : point d'API, clé du champ `idempotency_key` (gardée à chaque essai), corps lu tout de suite. */
export function formEntry(form, type = '') {
  const data = new FormData(form);
  const key = data.get('idempotency_key');
  return {
    type: type || form.dataset.outbox || 'default',
    url: form.dataset.outboxEndpoint,
    method: 'POST',
    formData: data,
    ...(typeof key === 'string' && key !== '' ? { id: key, idempotencyKey: key } : {}),
  };
}

export function bootOutbox(opts = {}) {
  const win = opts.window || window;
  const doc = opts.document || document;
  const storage = opts.storage === undefined ? (() => { try { return win.localStorage; } catch { return null; } })() : opts.storage;
  const importer = opts.import || (() => Promise.all([import('./outbox.js'), import('../public/outbox-notices.js')]));
  const texts = readTexts(doc);
  const account = readAccount(doc);
  const ttlDays = Number(meta(doc) && meta(doc).getAttribute('data-ttl-days')) || undefined;
  const seen = new Set();
  // Pour toute la vie du document : le retour du réseau ne rend pas son jeton CSRF à une copie.
  const copy = doc.documentElement.hasAttribute('data-xm-offline-copy');
  let loading = null;
  const ac = new AbortController();
  const on = { signal: ac.signal };

  const load = () => (loading ||= importer().then(([core, notices]) => {
    const box = core.getOutbox(account === null ? { ttlDays } : { account: () => account, ttlDays });
    notices.mountOutboxNotices(texts);
    box.start();
    return { box, core };
  }));
  const quiet = (promise) => promise.catch(() => null);

  const add = (entry) => {
    if (entry && (entry.uuid || entry.id)) seen.add(String(entry.id || entry.uuid));
    return quiet(load().then(({ box, core }) => {
      const { formData, ...rest } = entry;
      if (account !== null && typeof rest.account !== 'string') rest.account = account;
      return box.add(formData ? { ...rest, body: core.formBody(formData) } : rest);
    }));
  };

  win.XM = win.XM || {};
  win.XM.outbox = {
    add,
    flush: (options) => quiet(load().then(({ box }) => box.flush(options))),
    count: () => quiet(load().then(({ box }) => box.count())),
  };

  // sos.js remet l'alerte par XM.outbox.add puis annonce `xm:outbox` : l'évènement seul sert aux modules qui n'appellent pas add.
  win.addEventListener('xm:outbox', (event) => {
    const detail = event.detail || {};
    const id = String(detail.id || detail.uuid || '');
    if (id !== '' && seen.has(id)) return;
    add(detail);
  }, on);

  doc.addEventListener('xm:outbox-submit', (event) => {
    const form = event.detail && event.detail.form;
    if (!form || !form.matches || !form.matches(FORM)) return;
    event.preventDefault();
    add(formEntry(form, event.detail.type));
  }, on);

  doc.addEventListener('submit', (event) => {
    const form = event.target;
    if (event.defaultPrevented || !form || !form.matches || !form.matches(FORM)) return;
    if (!copy && win.navigator.onLine !== false) return;
    event.preventDefault();
    add(formEntry(form));
    // Composant Alpine du formulaire (xmReport) : l'envoi en cours s'arrête, l'état hors ligne s'affiche.
    const root = form.closest('[x-data]');
    const state = root && win.Alpine && typeof win.Alpine.$data === 'function' ? win.Alpine.$data(root) : null;
    if (state && 'sending' in state) state.sending = false;
    if (state && 'offline' in state) state.offline = true;
  }, on);

  // Bandeau : sur une copie jusqu'au retour du réseau, puis selon l'état du réseau (l'envoi reste détourné).
  let copyBar = copy;
  const bar = () => doc.body && doc.body.classList.toggle('xm-is-offline', copyBar || win.navigator.onLine === false);
  const ready = () => {
    if (copy) renewCopyKeys(doc, win);
    bar();
  };
  win.addEventListener('online', () => { copyBar = false; bar(); }, on);
  win.addEventListener('offline', bar, on);
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', ready, on);
  else ready();

  if (pendingCount(storage) > 0 || doc.querySelector('[data-outbox]')) quiet(load());

  return { load, destroy: () => ac.abort() };
}

// Démarrage automatique dans le navigateur (pas sous Vitest, qui appelle bootOutbox avec ses doublures).
if (typeof window !== 'undefined' && typeof document !== 'undefined' && !window.__xmOutboxBoot && import.meta.env.MODE !== 'test') {
  window.__xmOutboxBoot = true;
  bootOutbox();
}
