// Personnes prévenues d'une alerte SOS (P5-03, <x-alerts.notified>) : composant Alpine xmNotified. Sonde
// GET /api/v1/sos/{ulid} (data.notified) toutes les `poll` secondes (10 s, simulateur.html l.906), en pause quand
// l'onglet est masqué ; recul sur 429 et sur erreur réseau (jamais sous 10 s ni au-delà de 60 s), retour au rythme
// normal dès la première réponse ; arrêt sur état final (pris en charge et en sécurité, annulé), 401, 403, 404, trois
// 5xx consécutifs ou `pollUntil` dépassé. Hors ligne (navigateur hors ligne ou réseau en échec) : liste figée, message,
// sondage maintenu avec recul, reprise immédiate à l'événement `online`. SOS sans compte : jeton `token` envoyé en
// `X-Sos-Token` (ShowSosRequest, Q52 b), gardé en mémoire seulement (jamais d'état Alpine ni de stockage local).
// `onUpdate(data)` (facultatif) : rappelé avec chaque réponse de sondage (page /sos : statut et contact principal).
// Aucun texte ici : tout vient de Blade (lang/fr, LocaleFormatter) par `opts.texts`.

export const MIN_POLL = 10;
export const MAX_POLL = 60;

/** Réponses qui arrêtent le sondage : jeton absent ou refusé, alerte introuvable. */
export const STOP_STATUSES = [401, 403, 404];

export function initials(label = '') {
  return String(label)
    .trim()
    .split(/\s+/u)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word.charAt(0))
    .join('')
    .toUpperCase();
}

export function backoff(current) {
  return Math.min(MAX_POLL, Math.max(MIN_POLL, current * 2));
}

export function nextInterval(current, status) {
  return status === 429 ? backoff(current) : current;
}

export function isFinal(data = {}) {
  return Boolean(data.cancelled) || (Boolean(data.acknowledged) && Boolean(data.safe));
}

export function newlySeen(before = [], after = []) {
  const seen = new Set(before.filter((p) => p.state === 'seen').map((p) => p.key));
  return after.filter((p) => p.state === 'seen' && !seen.has(p.key));
}

/** Heure selon le gabarit de Blade (`{H}` heure, `{HH}` heure sur deux chiffres, `{mm}` minutes), ex. « 9 h 05 ». */
export function formatTime(date, pattern = '{HH}:{mm}') {
  const pad = (n) => String(n).padStart(2, '0');
  return String(pattern)
    .replace('{mm}', pad(date.getMinutes()))
    .replace('{HH}', pad(date.getHours()))
    .replace('{H}', String(date.getHours()));
}

function browserOffline() {
  return typeof navigator !== 'undefined' && navigator.onLine === false;
}

export function xmNotified(opts = {}) {
  // Jeton de propriétaire : variable de fermeture, hors de l'état réactif (ni inspecteur Alpine, ni stockage).
  const token = typeof opts.token === 'string' && opts.token !== '' ? opts.token : null;
  const base = Math.max(MIN_POLL, Number(opts.poll) || MIN_POLL);

  return {
    people: Array.isArray(opts.people) ? opts.people : [],
    endpoint: opts.endpoint ?? null,
    interval: base,
    retryDelay: 0,
    pollUntil: opts.pollUntil ? Date.parse(opts.pollUntil) : null,
    texts: { acknowledged: ':name', updated: ':time', time: '{HH}:{mm}', ...(opts.texts || {}) },
    ready: false,
    offline: browserOffline(),
    stopped: false,
    updatedAt: null,
    announcement: '',
    errors: 0,
    _timer: null,

    init() {
      this.ready = true;
      if (!this.endpoint) return;
      if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', () => (document.hidden ? this.pause() : this.resume()));
      }
      if (typeof window !== 'undefined') {
        window.addEventListener('online', () => { this.retryDelay = 0; this.resume(); });
        window.addEventListener('offline', () => { this.offline = true; this.pause(); });
      }
      this.schedule();
    },

    headers() {
      const headers = { Accept: 'application/json' };
      if (token) headers['X-Sos-Token'] = token;
      return headers;
    },

    schedule() {
      this.pause();
      if (this.stopped || !this.endpoint) return;
      this._timer = setTimeout(() => this.tick(), (this.retryDelay || this.interval) * 1000);
    },

    pause() {
      if (this._timer) clearTimeout(this._timer);
      this._timer = null;
    },

    resume() {
      if (this.stopped || browserOffline()) return;
      this.tick();
    },

    stop() {
      this.stopped = true;
      this.pause();
    },

    async tick() {
      if (this.stopped || !this.endpoint) return;
      if (this.pollUntil && Date.now() > this.pollUntil) { this.stop(); return; }
      // Seul le navigateur hors ligne suspend le sondage : l'événement `online` le relance.
      if (browserOffline()) { this.offline = true; this.pause(); return; }
      try {
        const response = await fetch(this.endpoint, { headers: this.headers(), credentials: 'same-origin', cache: 'no-store' });
        this.retryDelay = 0;
        this.offline = false;
        if (STOP_STATUSES.includes(response.status)) { this.stop(); return; }
        this.interval = nextInterval(this.interval, response.status);
        if (response.status >= 500) {
          this.errors += 1;
          if (this.errors >= 3) { this.stop(); return; }
        } else if (response.ok) {
          this.errors = 0;
          const json = await response.json();
          this.apply((json && json.data) || {});
        }
      } catch (e) {
        // Réseau instable (navigator.onLine reste souvent vrai) : liste figée, nouvel essai avec recul.
        this.offline = true;
        this.retryDelay = backoff(this.retryDelay || this.interval);
      }
      this.schedule();
    },

    apply(data) {
      const people = Array.isArray(data.notified) ? data.notified : this.people;
      const seen = newlySeen(this.people, people);
      if (seen.length > 0) this.announcement = this.texts.acknowledged.replace(':name', seen[0].label);
      this.people = people;
      this.offline = false;
      this.updatedAt = new Date();
      // Hôte (page /sos, P5-05) : statut, contact principal, annulation et sécurité suivent le même sondage.
      if (typeof opts.onUpdate === 'function') {
        try { opts.onUpdate(data); } catch (e) { /* le sondage continue */ }
      }
      if (isFinal(data)) this.stop();
    },

    primary() {
      return this.people.find((p) => p.phone) || null;
    },

    initials,

    telHref(phone) {
      return String(phone || '').replace(/[^\d+]/g, '');
    },

    updatedLabel() {
      if (!this.updatedAt) return '';
      return this.texts.updated.replace(':time', formatTime(this.updatedAt, this.texts.time));
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmNotified', xmNotified);
  });
}
