// Boîte d'envoi commune hors connexion (P5-10, ADR-26, ADR-32), sans framework : pages Blade (chargée à la demande par
// shared/outbox-boot.js) et, plus tard, store Pinia `outbox` (P6-05) et collecte (P9-04, P9-07) : une seule implémentation.
// File IndexedDB (`idb`) d'entrées { id, type, url, method, body, headers, priority, createdAt, idempotencyKey,
// attempts, nextAttemptAt, lastStatus, account } ; `body` : chaîne JSON ou { form: [[nom, valeur | Blob]] } (multipart,
// preuves). Contenu en clair sur l'appareil (récit, contact, position, preuves) : chaque entrée expire après `ttlDays`
// jours (xomiton.alerts.draft_days, comme le brouillon de /signaler) et est purgée au passage suivant (Q65).
// Compte : `account` = empreinte du compte de la page qui a mis en file ('' : sans compte, page servie depuis le cache
// comprise ; null : inconnu), envoyée dans X-Xm-Outbox-Account : le serveur (BindOutboxAccount) traite l'envoi comme
// anonyme si la session du rejeu n'est pas ce compte. Un signalement d'un compte attend une page du même compte ; un SOS
// part toujours (RG-01).
// Rejeu au retour du réseau (`online`), toutes les 5 min et au retour sur l'onglet, SOS d'abord (priorité puis date),
// avec la même Idempotency-Key à chaque essai : un rejeu renvoie la réponse d'origine, jamais de doublon (ADR-32).
// Sanctum SPA : X-XSRF-TOKEN repris du cookie à chaque envoi (jamais stocké), un passage par /sanctum/csrf-cookie sur
// 419 ; le `_token` d'un formulaire n'est jamais gardé (une page en cache porte un jeton périmé). Réponses : 2xx → retirée
// (envoyée) ; 202 → nouvel essai après Retry-After ; réseau coupé, 408, 425, 429, 5xx → gardée, recul croissant ;
// 419 → cookie XSRF neuf puis nouvel essai ; 422 → retirée (refusée), sauf un SOS renvoyé une fois sans les champs
// refusés sous la clé dérivée de l'ancienne (shared/sos-retry.js, la même que public/sos.js : jamais deux SOS ; RG-01 :
// un SOS part toujours) ; autre 4xx → retirée (refusée). Évènements window : `xm:outbox:queued` { id, type, pending } et
// `xm:outbox:flushed` { sent, failed, pending } (résultats { id, type, state, status, data }). Aucun texte ici.
import { openDB } from 'idb';
import { derivedKey, strip } from './sos-retry.js';

export const OUTBOX_DB = 'xomiton-outbox';
export const OUTBOX_STORE = 'entries';
export const OUTBOX_VERSION = 1;
export const FLUSH_INTERVAL = 5 * 60 * 1000;
export const PENDING_KEY = 'xm.outbox.pending';
export const LOCK_NAME = 'xm-outbox';
export const EVENTS = { queued: 'xm:outbox:queued', flushed: 'xm:outbox:flushed' };
export const PRIORITIES = { sos: 0, signalement: 10, default: 50 };
export const ENDPOINTS = { sos: '/api/v1/sos', signalement: '/api/v1/signalements' };
export const ACCOUNT_HEADER = 'X-Xm-Outbox-Account'; // = App\Application\Offline\OutboxAccount::HEADER
export const GUEST = 'guest'; // = OutboxAccount::GUEST
export const TTL_DAYS = 7; // = xomiton.alerts.draft_days (repli quand la page ne le transmet pas)
const DAY_MS = 24 * 60 * 60 * 1000;
const MAX_BACKOFF = { sos: 30 * 1000, default: FLUSH_INTERVAL };

export function readCookie(name, source = typeof document === 'undefined' ? '' : document.cookie) {
  const row = String(source || '').split('; ').find((part) => part.startsWith(`${name}=`));
  return row ? decodeURIComponent(row.slice(name.length + 1)) : null;
}

/** UUID v4 (Idempotency-Key, identifiant d'entrée) ; crypto.randomUUID absent hors contexte sécurisé. */
export function uuid(cryptoImpl = globalThis.crypto) {
  if (cryptoImpl && typeof cryptoImpl.randomUUID === 'function') return cryptoImpl.randomUUID();
  const bytes = new Uint8Array(16);
  if (cryptoImpl && typeof cryptoImpl.getRandomValues === 'function') cryptoImpl.getRandomValues(bytes);
  else for (let i = 0; i < 16; i += 1) bytes[i] = Math.floor(Math.random() * 256);
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

const isBlob = (value) => typeof Blob !== 'undefined' && value instanceof Blob;
const isFormBody = (body) => Boolean(body && typeof body === 'object' && Array.isArray(body.form));

/**
 * Corps multipart d'un formulaire : paires [nom, valeur], sans `_token` (jeton CSRF de la page, périmé au rejeu) ni
 * champ fichier vide. `except` : noms à écarter en plus.
 */
export function formBody(formData, except = []) {
  const skip = new Set(['_token', ...except]);
  const form = [];
  for (const [name, value] of formData.entries()) {
    if (skip.has(name)) continue;
    if (isBlob(value) && value.size === 0 && !value.name) continue;
    form.push([name, value]);
  }
  return { form };
}

/** Corps prêt pour fetch : FormData neuf à chaque essai, chaîne JSON telle quelle. */
export function requestBody(body) {
  if (isFormBody(body)) {
    const data = new FormData();
    for (const [name, value] of body.form) {
      if (isBlob(value)) data.append(name, value); // nom du fichier, ou « blob » (nom par défaut du navigateur)
      else data.append(name, value == null ? '' : String(value));
    }
    return data;
  }
  if (body == null) return undefined;
  return typeof body === 'string' ? body : JSON.stringify(body);
}

/**
 * Entrée normalisée. Accepte la forme de P5-10 ({ url, method, body, headers, priority, idempotencyKey, … }) et celle
 * remise par sos.js ({ uuid, type, payload, created_at }) : URL déduite du type, charge utile en JSON.
 */
export function createEntry(input = {}, now = Date.now()) {
  const type = typeof input.type === 'string' && input.type !== '' ? input.type : 'default';
  const url = input.url || ENDPOINTS[type];
  if (typeof url !== 'string' || url === '') throw new TypeError('outbox: url manquante');
  let body = input.body;
  if (body === undefined && input.payload !== undefined) body = JSON.stringify(input.payload);
  if (body !== undefined && body !== null && typeof body === 'object' && !isFormBody(body)) body = JSON.stringify(body);
  const idempotencyKey = input.idempotencyKey || input.uuid || input.id || uuid();
  const headers = { Accept: 'application/json', ...(typeof body === 'string' ? { 'Content-Type': 'application/json' } : {}) };
  Object.entries(input.headers || {}).forEach(([name, value]) => {
    // Jamais de jeton stocké : XSRF et Idempotency-Key sont posés à l'envoi.
    if (!/^(x-xsrf-token|x-csrf-token|idempotency-key|cookie)$/i.test(name)) headers[name] = String(value);
  });
  const created = input.createdAt ?? (input.created_at ? Date.parse(input.created_at) : NaN);
  const account = typeof input.account === 'string' ? input.account : null;
  return {
    id: String(input.id || idempotencyKey),
    type,
    url,
    method: String(input.method || 'POST').toUpperCase(),
    body: body === undefined ? null : body,
    headers,
    priority: Number.isFinite(input.priority) ? input.priority : (PRIORITIES[type] ?? PRIORITIES.default),
    createdAt: Number.isFinite(created) ? created : now,
    idempotencyKey,
    attempts: 0,
    nextAttemptAt: 0,
    lastStatus: null,
    stripped: false,
    account,
  };
}

/** Ordre d'envoi : priorité (SOS d'abord), puis la plus ancienne. */
export function compareEntries(a, b) {
  return (a.priority - b.priority) || (a.createdAt - b.createdAt);
}

/** sent | processing | retry | csrf | invalid | failed */
export function classify(status) {
  if (status === 202) return 'processing';
  if (status >= 200 && status < 300) return 'sent';
  if (status === 419) return 'csrf';
  if (status === 422) return 'invalid';
  if (status === 0 || status === 408 || status === 425 || status === 429 || status >= 500) return 'retry';
  return 'failed';
}

/** Recul : 5 s, 10 s, 20 s… plafonné (30 s pour un SOS, 5 min sinon). */
export function backoff(attempts, type = 'default') {
  const max = MAX_BACKOFF[type] ?? MAX_BACKOFF.default;
  return Math.min(max, 5000 * 2 ** Math.max(0, attempts - 1));
}

/** Charge utile JSON sans les champs refusés (422 d'un SOS), comme public/sos.js (shared/sos-retry.js). */
export function stripFields(body, fields = []) {
  try {
    const payload = JSON.parse(body);
    return payload && typeof payload === 'object' ? JSON.stringify(strip(payload, fields)) : body;
  } catch {
    return body;
  }
}

/** Entrée de compte à garder : signalement (pas un SOS) d'un autre compte que celui de la page, ou page sans compte. */
export function heldFor(entry, current) {
  if (entry.type === 'sos' || typeof entry.account !== 'string' || entry.account === '') return false;
  return current !== entry.account;
}

/** Entrée expirée : mise en file il y a plus de `ttlMs`. */
export function expired(entry, now, ttlMs) {
  return Number.isFinite(entry.createdAt) && now - entry.createdAt > ttlMs;
}

const defaultOpen = () => openDB(OUTBOX_DB, OUTBOX_VERSION, {
  upgrade(db) {
    if (!db.objectStoreNames.contains(OUTBOX_STORE)) db.createObjectStore(OUTBOX_STORE, { keyPath: 'id' });
  },
});

/**
 * Boîte d'envoi. Tout est injectable (tests) : open (base idb), fetch, now, online, cookies, storage, locks, target
 * (cible des évènements), csrfUrl, account (empreinte du compte de la page, '' sans compte ; absent : aucun tri par
 * compte), ttlDays (durée de vie d'une entrée).
 */
export function createOutbox(opts = {}) {
  const open = opts.open || defaultOpen;
  const fetchImpl = opts.fetch || ((...args) => fetch(...args));
  const now = opts.now || (() => Date.now());
  const online = opts.online || (() => typeof navigator === 'undefined' || navigator.onLine !== false);
  const cookies = opts.cookies || (() => (typeof document === 'undefined' ? '' : document.cookie));
  const storage = opts.storage === undefined ? (typeof localStorage === 'undefined' ? null : localStorage) : opts.storage;
  const locks = opts.locks === undefined ? (typeof navigator !== 'undefined' ? navigator.locks : null) : opts.locks;
  const target = opts.target || (typeof window === 'undefined' ? null : window);
  const csrfUrl = opts.csrfUrl || '/sanctum/csrf-cookie';
  const account = typeof opts.account === 'function' ? opts.account : null;
  const ttlMs = (Number(opts.ttlDays) > 0 ? Number(opts.ttlDays) : TTL_DAYS) * DAY_MS;
  let dbPromise = null;
  let flushing = null;
  let timer = null;
  let stopper = null;

  const db = () => (dbPromise ||= open());
  const emit = (name, detail) => {
    if (target && typeof target.dispatchEvent === 'function') target.dispatchEvent(new CustomEvent(name, { detail }));
  };
  const remember = (pending) => {
    try {
      if (!storage) return;
      if (pending > 0) storage.setItem(PENDING_KEY, String(pending));
      else storage.removeItem(PENDING_KEY);
    } catch { /* navigation privée : la boîte reste lue au chargement des pages [data-outbox] */ }
  };

  async function list() {
    return (await (await db()).getAll(OUTBOX_STORE)).sort(compareEntries);
  }

  async function count() {
    return (await db()).count(OUTBOX_STORE);
  }

  async function get(id) {
    return (await db()).get(OUTBOX_STORE, id);
  }

  async function put(entry) {
    await (await db()).put(OUTBOX_STORE, entry);
  }

  async function remove(id) {
    await (await db()).delete(OUTBOX_STORE, id);
    remember(await count());
  }

  /** Retire les entrées expirées (contenu en clair sur l'appareil, jamais gardé au-delà de `ttlDays`). */
  async function purge() {
    const stale = (await (await db()).getAll(OUTBOX_STORE)).filter((entry) => expired(entry, now(), ttlMs));
    for (const entry of stale) await (await db()).delete(OUTBOX_STORE, entry.id);
    if (stale.length > 0) remember(await count());
    return stale.length;
  }

  /** Met en file (la même clé remplace l'entrée : jamais deux envois pour une alerte), puis tente l'envoi. */
  async function add(input) {
    const entry = createEntry(input, now());
    const existing = await get(entry.id);
    if (existing) Object.assign(entry, { attempts: existing.attempts, createdAt: existing.createdAt, stripped: existing.stripped });
    await put(entry);
    const pending = await count();
    remember(pending);
    emit(EVENTS.queued, { id: entry.id, type: entry.type, pending });
    if (opts.autoFlush !== false && online()) flush({ force: true }).catch(() => undefined);
    return entry;
  }

  async function send(entry) {
    const headers = () => {
      const h = { ...entry.headers, 'Idempotency-Key': entry.idempotencyKey, 'X-Requested-With': 'XMLHttpRequest' };
      if (typeof entry.account === 'string') h[ACCOUNT_HEADER] = entry.account === '' ? GUEST : entry.account;
      const xsrf = readCookie('XSRF-TOKEN', cookies());
      if (xsrf) h['X-XSRF-TOKEN'] = xsrf;
      return h;
    };
    const request = () => fetchImpl(entry.url, {
      method: entry.method, headers: headers(), body: requestBody(entry.body), credentials: 'same-origin', cache: 'no-store',
    });
    let response = await request();
    if (response.status === 419) {
      await fetchImpl(csrfUrl, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } });
      response = await request();
    }
    return response;
  }

  /** Un essai : { id, type, state, status, data?, errors?, retryAfter? }. */
  async function attempt(entry) {
    let response;
    try {
      response = await send(entry);
    } catch {
      return { id: entry.id, type: entry.type, state: 'retry', status: 0, network: true };
    }
    const status = response.status;
    const json = await response.json().catch(() => null);
    const state = classify(status);
    // 419 encore après un cookie neuf (cookies bloqués, session en cours de rotation) : nouvel essai plus tard, jamais perdu.
    const result = { id: entry.id, type: entry.type, state: state === 'csrf' ? 'retry' : state, status };
    if (json && json.data !== undefined) result.data = json.data;
    if (json && json.meta !== undefined) result.meta = json.meta;
    if (json && json.errors) result.errors = json.errors;
    if (state === 'processing') {
      const after = Number(response.headers && response.headers.get ? response.headers.get('Retry-After') : 0);
      result.retryAfter = Number.isFinite(after) && after > 0 ? after : 2;
    }
    return result;
  }

  async function run(force) {
    const report = { sent: [], failed: [], pending: 0 };
    await purge();
    if (!online()) {
      report.pending = await count();
      return report;
    }
    const entries = await list();
    for (const entry of entries) {
      if (!force && entry.nextAttemptAt > now()) continue;
      if (account && heldFor(entry, account())) continue; // attend une page du même compte
      let result = await attempt(entry);
      if (result.state === 'invalid' && entry.type === 'sos' && !entry.stripped && typeof entry.body === 'string') {
        // RG-01 : un SOS part toujours ; le corps change, donc la clé aussi (même clé + autre corps = 422 côté serveur),
        // dérivée de l'ancienne comme dans public/sos.js : si la page renvoie aussi cette alerte, une seule est créée.
        Object.assign(entry, { body: stripFields(entry.body, Object.keys(result.errors || {})), idempotencyKey: derivedKey(entry.idempotencyKey), stripped: true });
        await put(entry);
        result = await attempt(entry);
      }
      if (result.state === 'sent') {
        await remove(entry.id);
        report.sent.push(result);
      } else if (result.state === 'invalid' || result.state === 'failed') {
        await remove(entry.id);
        report.failed.push(result);
      } else {
        entry.attempts += 1;
        entry.lastStatus = result.status;
        entry.nextAttemptAt = now() + (result.retryAfter ? result.retryAfter * 1000 : backoff(entry.attempts, entry.type));
        await put(entry);
        if (result.network) break; // réseau coupé : les suivantes attendront le prochain passage
      }
    }
    report.pending = await count();
    remember(report.pending);
    if (entries.length > 0) emit(EVENTS.flushed, report);
    return report;
  }

  /** Planifie le prochain passage au plus tôt dû (202, recul court), en plus de l'intervalle de 5 min. */
  async function schedule() {
    if (!stopper) return;
    clearTimeout(timer);
    if (!online()) return; // l'évènement `online` relancera l'envoi
    const next = (await list()).reduce((min, entry) => Math.min(min, entry.nextAttemptAt || 0), Infinity);
    if (!Number.isFinite(next)) return;
    const delay = Math.max(1000, next - now());
    if (delay < FLUSH_INTERVAL) timer = setTimeout(() => { flush().catch(() => undefined); }, delay);
  }

  /** Un seul passage à la fois (et un seul onglet à la fois quand Web Locks existe). */
  function flush({ force = false } = {}) {
    if (flushing) return flushing;
    const job = () => run(force);
    flushing = (locks && typeof locks.request === 'function' ? locks.request(LOCK_NAME, job) : job())
      .finally(() => { flushing = null; schedule().catch(() => undefined); });
    return flushing;
  }

  /** Déclencheurs : retour du réseau (forcé), toutes les 5 min, retour sur l'onglet ; passage immédiat. */
  function start(win = target) {
    if (stopper) return stopper;
    const onOnline = () => { flush({ force: true }).catch(() => undefined); };
    const onVisible = () => { if (typeof document === 'undefined' || document.visibilityState === 'visible') flush().catch(() => undefined); };
    const interval = setInterval(() => { flush().catch(() => undefined); }, FLUSH_INTERVAL);
    if (win && win.addEventListener) win.addEventListener('online', onOnline);
    if (typeof document !== 'undefined') document.addEventListener('visibilitychange', onVisible);
    stopper = () => {
      clearInterval(interval);
      clearTimeout(timer);
      if (win && win.removeEventListener) win.removeEventListener('online', onOnline);
      if (typeof document !== 'undefined') document.removeEventListener('visibilitychange', onVisible);
      stopper = null;
    };
    flush({ force: true }).catch(() => undefined);
    return stopper;
  }

  function stop() {
    if (stopper) stopper();
  }

  return { add, list, count, get, remove, purge, flush, start, stop };
}

let shared = null;

/** Instance partagée de la page (outbox-boot.js, store Pinia P6-05). */
export function getOutbox(opts = {}) {
  shared ||= createOutbox(opts);
  return shared;
}
