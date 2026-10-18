// Envoi d'un SOS depuis le web (P5-05 page /sos, P5-08 bouton flottant) : composants Alpine xmSosPage et xmSosFab.
// Tout passe par l'API (ADR-26) : POST /api/v1/sos avec Idempotency-Key (une clé par alerte, gardée pour chaque nouvel
// essai : jamais de doublon), GET / cancel / safe avec le jeton X-Sos-Token d'un SOS sans compte (Q52 b), gardé dans une
// variable de fermeture (jamais d'état Alpine ni de stockage local). Sanctum SPA : X-XSRF-TOKEN repris du cookie, un
// passage par /sanctum/csrf-cookie sur 419. Réponses : 201 / 200 rejeu → envoyé ; 202 → nouvel essai après Retry-After ;
// 422 → un seul nouvel envoi sans les champs refusés (RG-01 : un SOS part toujours) sous une nouvelle clé ; réseau
// coupé ou 5xx → « hors ligne », nouvel essai au retour du réseau puis avec recul (5 s → 60 s) tant que la page reste
// ouverte, et remise à la boîte d'envoi commune si elle existe (`window.XM.outbox`, P5-10) + évènement `xm:outbox`.
// Suivi : `x-alerts.notified` rappelle `onUpdate` à chaque sondage (statut, contact principal, annulation, sécurité).
// Bouton flottant : une seule alerte à la fois (un nouvel appui pendant l'attente du réseau remontre l'état « en
// attente », jamais une 2e alerte) ; SOS non rattaché au compte (réponse avec `sos_token`) : pas de navigation vers le
// suivi, qui ne le montrerait pas. Chargé à la demande par public.js (`[x-data^="xmSosPage"]`, `[x-data^="xmSosFab"]`).
// Aucun texte ici : tout vient de Blade (lang/fr) par `opts.texts`.

export const MIN_RETRY = 5;
export const MAX_RETRY = 60;
export const SHARE_KEY = 'xm.sos.share_gps';

const offline = () => typeof navigator !== 'undefined' && navigator.onLine === false;

export function readCookie(name, source = typeof document === 'undefined' ? '' : document.cookie) {
  const row = String(source || '').split('; ').find((part) => part.startsWith(`${name}=`));
  return row ? decodeURIComponent(row.slice(name.length + 1)) : null;
}

/** UUID v4 pour l'en-tête Idempotency-Key (crypto.randomUUID absent hors contexte sécurisé). */
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

export function retryDelay(current) {
  return Math.min(MAX_RETRY, Math.max(MIN_RETRY, current * 2));
}

/** Charge utile de POST sos : champs vides retirés ; position seulement si elle est partagée. */
export function buildPayload({ share = null, position = null, zoneId = null, communeId = null, phone = '', message = '' } = {}) {
  const payload = {};
  if (share !== null) payload.share_location = Boolean(share);
  if (share && position) {
    payload.lat = Number(Number(position.lat).toFixed(6));
    payload.lng = Number(Number(position.lng).toFixed(6));
    if (Number.isFinite(Number(position.accuracy))) payload.accuracy = Math.min(65535, Math.max(0, Math.round(Number(position.accuracy))));
  }
  const zone = Number(zoneId);
  if (zoneId !== null && zoneId !== '' && Number.isInteger(zone) && zone > 0) {
    payload.zone_id = zone;
    const commune = Number(communeId);
    if (communeId !== null && communeId !== '' && Number.isInteger(commune) && commune > 0) payload.commune_id = commune;
  }
  if (String(phone || '').trim() !== '') payload.phone = String(phone).trim();
  if (String(message || '').trim() !== '') payload.message = String(message).trim();
  return payload;
}

/** Retire les champs refusés par la validation (422) ; une position refusée part entière. */
export function strip(payload, fields = []) {
  const lean = { ...payload };
  fields.forEach((field) => {
    const name = String(field).split('.')[0];
    delete lean[name];
    if (['lat', 'lng', 'accuracy'].includes(name)) { delete lean.lat; delete lean.lng; delete lean.accuracy; }
    if (name === 'zone_id') delete lean.commune_id;
  });
  return lean;
}

/** Position du navigateur, ou null (refus, absence, délai dépassé) : jamais d'exception. */
export function locate(geo = typeof navigator === 'undefined' ? null : navigator.geolocation, timeoutMs = 12000) {
  return new Promise((resolve) => {
    if (!geo || typeof geo.getCurrentPosition !== 'function') { resolve(null); return; }
    let settled = false;
    const finish = (value) => { if (!settled) { settled = true; clearTimeout(timer); resolve(value); } };
    // Le délai de l'API ne compte pas l'attente de l'invite d'autorisation : borne propre.
    const timer = setTimeout(() => finish(null), timeoutMs);
    try {
      geo.getCurrentPosition(
        (p) => finish({ lat: p.coords.latitude, lng: p.coords.longitude, accuracy: p.coords.accuracy }),
        () => finish(null),
        { enableHighAccuracy: true, timeout: Math.max(1000, timeoutMs - 2000), maximumAge: 60000 },
      );
    } catch (e) {
      finish(null);
    }
  });
}

/** Lien d'appel : chiffres et « + » seulement. */
export function telHref(number) {
  return `tel:${String(number || '').replace(/[^\d+]/g, '')}`;
}

/** Dernier choix « Partager ma position » d'un·e visiteur·se (localStorage, jamais bloquant). */
export function readShare(fallback = true, storage = globalThis.localStorage) {
  try {
    const value = storage ? storage.getItem(SHARE_KEY) : null;
    if (value === '1') return true;
    if (value === '0') return false;
  } catch (e) { /* stockage indisponible : valeur par défaut */ }
  return fallback;
}

export function writeShare(value, storage = globalThis.localStorage) {
  try { if (storage) storage.setItem(SHARE_KEY, value ? '1' : '0'); } catch (e) { /* navigation privée */ }
}

/**
 * Client de l'API SOS. `fetch`, `wait` et `waitOnline` sont injectables (tests).
 * send(payload, key, { onQueued }) → { state: 'sent', data, meta } | { state: 'failed', status } | { state: 'stopped' }.
 */
export function createSosClient(opts = {}) {
  const f = opts.fetch || ((...args) => fetch(...args));
  const endpoint = opts.endpoint || '/api/v1/sos';
  const csrfUrl = opts.csrfUrl || '/sanctum/csrf-cookie';
  const wait = opts.wait || ((ms) => new Promise((resolve) => setTimeout(resolve, ms)));
  const waitOnline = opts.waitOnline || ((ms) => new Promise((resolve) => {
    const done = () => { clearTimeout(timer); window.removeEventListener('online', done); resolve(); };
    const timer = setTimeout(done, ms);
    window.addEventListener('online', done);
  }));
  let stopped = false;

  const headers = (extra = {}) => {
    const h = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...extra };
    const xsrf = readCookie('XSRF-TOKEN', opts.cookies);
    if (xsrf) h['X-XSRF-TOKEN'] = xsrf;
    return h;
  };

  async function request(url, init, retried = false) {
    const response = await f(url, { credentials: 'same-origin', cache: 'no-store', ...init, headers: headers(init.headers) });
    if (response.status === 419 && !retried) {
      await f(csrfUrl, { method: 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } });
      return request(url, init, true);
    }
    return response;
  }

  async function attempt(payload, key) {
    if (offline()) return { state: 'queued', reason: 'offline' };
    let response;
    try {
      response = await request(endpoint, { method: 'POST', headers: { 'Idempotency-Key': key }, body: JSON.stringify(payload) });
    } catch (e) {
      return { state: 'queued', reason: 'network' };
    }
    if (response.status === 202) {
      const after = Number(response.headers && response.headers.get ? response.headers.get('Retry-After') : 0);
      return { state: 'processing', retryAfter: Number.isFinite(after) && after > 0 ? after : 2 };
    }
    const body = await response.json().catch(() => ({}));
    if (response.ok && body && body.data) return { state: 'sent', data: body.data, meta: body.meta || {} };
    if (response.status === 422) return { state: 'invalid', fields: Object.keys((body && body.errors) || {}), code: body && body.code, status: 422 };
    if (response.status >= 500 || response.status === 429 || response.status === 408) return { state: 'queued', reason: 'server', status: response.status };
    return { state: 'failed', status: response.status };
  }

  return {
    stop() { stopped = true; },

    async send(payload, key, { onQueued } = {}) {
      let current = { payload, key };
      let stripped = false;
      let delay = 0;
      let notified = false;
      while (!stopped) {
        const r = await attempt(current.payload, current.key);
        if (r.state === 'sent') return r;
        if (r.state === 'processing') { await wait(r.retryAfter * 1000); continue; }
        if (r.state === 'invalid') {
          if (stripped) return { state: 'failed', status: 422 };
          stripped = true;
          // Clé refusée (déjà prise ou absente) : nouvelle clé ; champs refusés retirés, la clé change aussi (corps différent).
          current = { payload: strip(current.payload, r.fields), key: uuid() };
          continue;
        }
        if (r.state === 'failed') return r;
        if (!notified) {
          notified = true;
          handOff(current.payload, current.key);
          if (onQueued) onQueued(r);
        }
        delay = retryDelay(delay);
        await waitOnline(delay * 1000);
      }
      return { state: 'stopped' };
    },

    /** cancel | safe | show ; jeton X-Sos-Token pour un SOS sans compte. */
    async action(url, method = 'POST', token = null) {
      const extra = token ? { 'X-Sos-Token': token } : {};
      try {
        const response = await request(url, { method, headers: extra, body: method === 'POST' ? '{}' : undefined });
        const body = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, data: body && body.data, message: body && body.message };
      } catch (e) {
        return { ok: false, status: 0 };
      }
    },
  };
}

/** Remise à la boîte d'envoi commune (P5-10) quand elle existe ; l'évènement permet de la brancher sans changer ce module. */
export function handOff(payload, key) {
  if (typeof window === 'undefined') return;
  const entry = { uuid: key, type: 'sos', payload, created_at: new Date().toISOString() };
  try {
    if (window.XM && window.XM.outbox && typeof window.XM.outbox.add === 'function') window.XM.outbox.add(entry);
  } catch (e) { /* la boucle de cette page continue */ }
  window.dispatchEvent(new CustomEvent('xm:outbox', { detail: entry }));
}

const fill = (text, values = {}) => Object.keys(values).reduce((out, k) => out.replace(`:${k}`, String(values[k])), String(text || ''));

/** Page /sos : idle → (locating) → (manual) → sending → sent | queued | failed ; puis cancelled ou « en sécurité ». */
export function xmSosPage(opts = {}) {
  const client = opts.client || createSosClient({ endpoint: opts.endpoint, csrfUrl: opts.csrfUrl });
  const storage = opts.storage === undefined ? globalThis.localStorage : opts.storage;
  let token = null; // X-Sos-Token : fermeture seulement
  let key = null;
  let autoTimer = null;
  let clock = null;
  const initial = opts.initial && opts.initial.data ? opts.initial : null;

  return {
    member: Boolean(opts.member),
    phase: initial ? (initial.data.cancelled ? 'cancelled' : 'sent') : 'idle',
    share: opts.member ? opts.share !== false : readShare(opts.share !== false, storage),
    phone: '',
    note: '',
    located: null,
    zoneId: opts.profile && opts.profile.zone_id ? String(opts.profile.zone_id) : '',
    communeId: opts.profile && opts.profile.commune_id ? String(opts.profile.commune_id) : '',
    zones: Array.isArray(opts.zones) ? opts.zones : [],
    position: null,
    alert: initial ? initial.data : null,
    meta: initial ? initial.meta || {} : {},
    sentAt: Date.now(),
    autoLeft: 0,
    busy: false,
    error: '',
    announce: '',
    now: Date.now(),
    texts: { ...(opts.texts || {}) },

    init() {
      if (!this.member && this.$watch) this.$watch('share', (value) => writeShare(value, storage));
      if (this.phase === 'sent') this.startClock();
      if (initial && this.$nextTick) this.$nextTick(() => this.go(this.phase));
    },

    communes() {
      const zone = this.zones.find((z) => String(z.id) === String(this.zoneId));
      return zone ? zone.communes : [];
    },

    /** Évènement `sos-send` de x-sos-button (mode managed), au terme du décompte de 5 s. */
    /** Texte de la confirmation de x-sos-button (prop confirmBind) : avec ou sans position selon la case. */
    sosConfirmText() {
      return this.share ? this.texts.confirmShared : this.texts.confirmUnshared;
    },

    async onSend(detail) {
      if (detail && detail.resolve) detail.resolve({ state: 'idle' });
      if (this.phase !== 'idle') return;
      key = uuid();
      this.error = '';
      this.position = null;
      if (this.share) {
        this.go('locating');
        this.position = await locate(opts.geolocation, opts.locateTimeout || 12000);
        if (this.phase !== 'locating') return;
      }
      if (this.position || this.zoneId !== '') { this.submit(); return; }
      this.manual();
    },

    /** Interrompt l'attente de la position : envoi immédiat sans elle. */
    skipLocate() {
      if (this.phase !== 'locating') return;
      if (this.zoneId !== '') this.submit();
      else this.manual();
    },

    manual() {
      this.go('manual');
      this.autoLeft = opts.manualSeconds || 15;
      clearInterval(autoTimer);
      // Personne qui ne peut plus agir : l'alerte part quand même, sans zone si besoin (point focal national).
      autoTimer = setInterval(() => {
        this.autoLeft -= 1;
        if (this.autoLeft <= 0) this.submit();
      }, 1000);
    },

    /** Retour à l'écran du bouton depuis le repli manuel : rien n'est encore parti. */
    abortManual() {
      if (this.phase !== 'manual') return;
      clearInterval(autoTimer);
      key = null;
      this.go('idle');
      this.say(this.texts.cancelled);
    },

    autoText() {
      return fill(this.texts.auto, { s: this.autoLeft });
    },

    async submit() {
      clearInterval(autoTimer);
      if (!['idle', 'locating', 'manual', 'failed'].includes(this.phase)) return;
      this.go('sending');
      const payload = buildPayload({
        share: this.share, position: this.position, zoneId: this.zoneId, communeId: this.communeId, phone: this.phone, message: this.note,
      });
      this.located = Boolean(this.share && this.position);
      const r = await client.send(payload, key, {
        onQueued: () => { this.go('queued'); this.say(this.texts.queued); },
      });
      if (r.state === 'sent') this.sent(r);
      else if (r.state === 'failed') { this.go('failed'); this.say(this.texts.failed); }
    },

    retry() {
      if (this.phase !== 'failed') return;
      key = uuid();
      this.submit();
    },

    sent(r) {
      token = r.data.sos_token || null;
      const { sos_token: omitted, ...data } = r.data;
      this.alert = data;
      this.meta = r.meta || {};
      this.sentAt = Date.now();
      this.go('sent');
      this.say(this.texts.sent);
      this.startClock();
    },

    startClock() {
      clearInterval(clock);
      this.now = Date.now();
      clock = setInterval(() => {
        this.now = Date.now();
        if (this.cancelLeft() <= 0) clearInterval(clock);
      }, 1000);
    },

    cancelLeft() {
      if (!this.alert || this.alert.cancelled || !this.alert.can_cancel_until) return 0;
      return Math.max(0, Math.ceil((Date.parse(this.alert.can_cancel_until) - this.now) / 1000));
    },

    url(name) {
      const base = String(opts.showUrl || '/api/v1/sos/__ID__').replace('__ID__', this.alert ? this.alert.id : '');
      return name === 'show' ? base : `${base}/${name}`;
    },

    async cancel() {
      if (this.busy || this.cancelLeft() <= 0) return;
      this.busy = true;
      this.error = '';
      const r = await client.action(this.url('cancel'), 'POST', token);
      this.busy = false;
      if (r.ok && r.data) {
        this.alert = { ...this.alert, ...r.data };
        this.go('cancelled');
        this.say(this.texts.undone);
      } else {
        this.error = r.message || this.texts.actionFailed;
        if (r.data) this.alert = { ...this.alert, ...r.data };
      }
    },

    async safe() {
      if (this.busy || !this.alert || this.alert.safe) return;
      this.busy = true;
      this.error = '';
      const r = await client.action(this.url('safe'), 'POST', token);
      this.busy = false;
      if (r.ok && r.data) {
        this.alert = { ...this.alert, ...r.data };
        this.say(this.texts.safeConfirmed);
      } else {
        this.error = r.message || this.texts.actionFailed;
      }
    },

    /** Réponse d'un sondage de x-alerts.notified : statut, contact principal, annulation ou sécurité à jour. */
    refresh(data) {
      if (!this.alert || !data || typeof data !== 'object' || (data.id && data.id !== this.alert.id)) return;
      const { sos_token: omitted, ...fresh } = data;
      this.alert = { ...this.alert, ...fresh };
      if (fresh.cancelled && this.phase === 'sent') this.phase = 'cancelled';
    },

    /** Options du composant x-alerts.notified monté dans l'état « envoyée » (sondage 10 s, jeton en mémoire). */
    notifiedOptions() {
      const minutes = Number(opts.pollMinutes) || 30;
      const created = this.alert && this.alert.created_at ? Date.parse(this.alert.created_at) : this.sentAt;
      return {
        people: (this.alert && this.alert.notified) || [],
        endpoint: this.meta && this.meta.poll_interval_seconds ? this.url('show') : null,
        poll: (this.meta && this.meta.poll_interval_seconds) || opts.poll || 10,
        pollUntil: new Date((Number.isFinite(created) ? created : this.sentAt) + minutes * 60000).toISOString(),
        token,
        onUpdate: (data) => this.refresh(data),
      };
    },

    primary() {
      return (this.alert && this.alert.primary_contact && this.alert.primary_contact.phone) ? this.alert.primary_contact : null;
    },

    callHref(number) {
      return telHref(number);
    },

    routedText() {
      const routed = this.alert && this.alert.routed;
      if (!routed || !routed.name) return '';
      return fill(this.texts.routed, { name: routed.name, role: routed.role || '' });
    },

    /** Position jointe ou non : d'abord la réponse de l'API (`located`, aussi après le bouton flottant ou un 422). */
    positionText() {
      const server = this.alert && typeof this.alert.located === 'boolean' ? this.alert.located : null;
      const located = server !== null ? server : this.located;
      if (located === null) return '';
      if (!located) return this.texts.positionNo || '';
      const accuracy = this.position ? Number(this.position.accuracy) : NaN;
      return Number.isFinite(accuracy) ? fill(this.texts.positionYes, { meters: Math.round(accuracy) }) : (this.texts.positionJoined || '');
    },

    undoText() {
      return fill(this.texts.undo, { seconds: this.cancelLeft() });
    },

    callPrimaryText() {
      const p = this.primary();
      return p ? fill(this.texts.callPrimary, { name: p.label }) : '';
    },

    restart() {
      if (!['cancelled', 'failed'].includes(this.phase) && !(this.alert && this.alert.safe)) return;
      clearInterval(clock);
      token = null;
      key = null;
      this.alert = null;
      this.meta = {};
      this.position = null;
      this.located = null;
      this.error = '';
      this.go('idle');
    },

    go(phase) {
      this.phase = phase;
      if (this.$nextTick) {
        this.$nextTick(() => {
          const target = this.$root && this.$root.querySelector(`[data-sos-focus="${phase}"]`);
          if (target) target.focus();
        });
      }
    },

    say(text) {
      // Réinitialiser puis écrire : un même texte est annoncé de nouveau (aria-live="assertive").
      this.announce = '';
      const write = () => { this.announce = text || ''; };
      if (this.$nextTick) this.$nextTick(write); else write();
    },

    destroy() {
      clearInterval(autoTimer);
      clearInterval(clock);
      client.stop();
    },
  };
}

/**
 * Bouton flottant des espaces connectés (P5-08) : envoi immédiat sans GPS (Permissions-Policy limitée à /sos), puis
 * suivi /sos?alerte= (location.replace : pas d'entrée d'historique de plus sur un appareil partagé). Une seule alerte
 * en cours : clé et promesse gardées ici jusqu'à la réponse ; un nouvel appui remontre l'état « en attente ».
 */
export function xmSosFab(opts = {}) {
  const client = opts.client || createSosClient({ endpoint: opts.endpoint, csrfUrl: opts.csrfUrl });
  const texts = { ...(opts.texts || {}) };
  const call = opts.number ? { href: telHref(opts.number), label: fill(texts.call, { number: opts.number }) } : null;
  const page = opts.pageUrl ? { href: opts.pageUrl, label: texts.openPage } : null;
  const navigate = opts.navigate || ((url) => window.location.replace(url));
  let pending = null; // envoi en cours (même Idempotency-Key à chaque essai)
  let waiting = null; // état « en attente du réseau » à remontrer
  let report = () => {}; // resolve du dernier appui : le panneau ouvert reçoit le résultat

  const finish = (r) => {
    pending = null;
    waiting = null;
    if (r.state === 'sent' && r.data && r.data.sos_token) {
      // Session non rattachée par Sanctum (SANCTUM_STATEFUL_DOMAINS) : SOS sans compte, que /sos?alerte= ne montrerait
      // pas. On reste ici : « partie », sans relance, avec l'appel et la page SOS (numéros).
      report({ state: 'sent', title: texts.sent, text: texts.detached, call, link: page });
    } else if (r.state === 'sent') {
      report({ state: 'sent', title: texts.sent, text: texts.sentText });
      navigate(String(opts.followUrl || '/sos?alerte=__ID__').replace('__ID__', encodeURIComponent(r.data.id)));
    } else if (r.state === 'failed') {
      report({ state: 'failed', title: texts.failedTitle, text: texts.failed, call, link: page });
    }
    return r;
  };

  return {
    onSend(detail) {
      report = (detail && detail.resolve) || (() => {});
      if (pending) {
        if (waiting) report(waiting);
        return pending;
      }
      const payload = buildPayload({ zoneId: opts.zoneId ?? null, communeId: opts.communeId ?? null });
      pending = client.send(payload, uuid(), {
        onQueued: () => {
          waiting = { state: 'queued', title: texts.offlineTitle, text: texts.offline, call };
          report(waiting);
        },
      }).then(finish, () => finish({ state: 'failed', status: 0 }));
      return pending;
    },
    destroy() { client.stop(); },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmSosPage', xmSosPage);
    window.Alpine.data('xmSosFab', xmSosFab);
  });
}
