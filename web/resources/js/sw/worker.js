// Comportement du service worker (P5-10, ADR-26), sans Workbox ni `self` : caches, fetch, horloge d'expiration et
// réponse partielle (Range) sont injectés, ce qui le rend testable sous Vitest (worker.test.js). sw.js le câble.
//  - Navigations : réseau d'abord ; une page gardée (rules.js) n'est stockée que rendue sans compte
//    (`X-Xm-Audience: guest`, jamais `no-store`) ; sans réseau, ou après `timeout` ms quand une copie existe : copie
//    marquée `data-xm-offline-copy`, sinon la page de repli, sinon une page minimale avec les numéros d'urgence.
//  - Préchargement sans cookie (`credentials: 'omit'`, `Purpose: prefetch`) des pages à l'installation et des
//    ressources hors ligne ; corps transmis en flux (jamais lu entier en mémoire).
//  - Ressources hors ligne : à l'installation, seulement les ressources marquées manquantes ou modifiées (`rev`) et
//    retrait de celles que l'équipe ne marque plus (sauf enregistrées par l'appareil) ; au message `offline-resources`
//    (shared/sw-register.js), ajout de ce qui manque et retrait de ce qui n'est plus enregistré ni marqué. L'état
//    (enregistrées par l'appareil, révisions chargées) est gardé dans META_CACHE.
//  - Déconnexion (POST /deconnexion, POST /api/v1/auth/logout) : pages gardées et fiches des ressources effacées puis
//    rechargées sans cookie (défense en profondeur : aucune page d'une session n'est stockée).
import {
  DEFAULT_FALLBACK, DEFAULT_PAGES, META_CACHE, PAGES_CACHE, RESOURCES_CACHE, API_CACHE, canStore, isGuestResponse,
  isResourcePage, isStoredPage, markOfflineCopy, offlineDocument, offlineResourcePaths, pageKey,
} from './rules.js';

export const KNOWN_CACHES = [PAGES_CACHE, RESOURCES_CACHE, API_CACHE, META_CACHE];
export const LOGOUT_PATHS = ['/deconnexion', '/api/v1/auth/logout'];
const STATE_PATH = '/__xm-sw/state';

/** Réponse détachée (une réponse redirigée ne peut pas servir une navigation), corps en flux, en-têtes gardés. */
export function passthrough(response) {
  return new Response(response.body, { status: response.status, statusText: response.statusText, headers: new Headers(response.headers) });
}

/** Copie au corps remplacé (marque de copie hors ligne), sans Content-Length périmé. */
function rewrite(response, body) {
  const headers = new Headers(response.headers);
  headers.delete('Content-Length');
  headers.set('X-Xm-Offline-Copy', '1');
  return new Response(body, { status: response.status, statusText: response.statusText, headers });
}

/** Révisions des ressources marquées par l'équipe : chemin (page et fichier) → `rev` (date de mise à jour). */
export function markedRevisions(marked = [], origin = 'http://localhost') {
  const revs = {};
  (Array.isArray(marked) ? marked : []).forEach((entry) => {
    if (!entry || typeof entry !== 'object') return;
    offlineResourcePaths({ marked: [entry] }, origin).forEach((path) => { revs[path] = String(entry.rev ?? ''); });
  });
  return revs;
}

export function createWorker({ config = {}, origin, caches, fetch, expiration, partial }) {
  const PAGES = Array.isArray(config.pages) && config.pages.length > 0 ? config.pages : DEFAULT_PAGES;
  const FALLBACK = config.fallback || DEFAULT_FALLBACK;
  const TIMEOUT = Number(config.timeout) > 0 ? Number(config.timeout) : 6000;
  const STATE_KEY = origin + STATE_PATH;
  const sameOrigin = (url) => url.origin === origin;

  const cachedPaths = async (cache) => new Set((await cache.keys()).map((request) => new URL(request.url).pathname));

  async function readState() {
    try {
      const match = await (await caches.open(META_CACHE)).match(STATE_KEY);
      const state = match ? await match.json() : {};
      return {
        device: Array.isArray(state.device) ? state.device : null,
        marked: state.marked && typeof state.marked === 'object' ? state.marked : {},
      };
    } catch {
      return { device: null, marked: {} };
    }
  }

  async function patchState(patch) {
    const state = { ...(await readState()), ...patch };
    await (await caches.open(META_CACHE)).put(STATE_KEY, new Response(JSON.stringify(state), { headers: { 'Content-Type': 'application/json' } }));
  }

  /** Charge sans cookie (version publique, jamais celle d'une session) ; rend les chemins effectivement gardés. */
  async function warm(paths, cacheName, explicit = false) {
    const cache = await caches.open(cacheName);
    const results = await Promise.allSettled(paths.map(async (path) => {
      const response = await fetch(new Request(origin + path, { credentials: 'omit', cache: 'no-store', headers: { Purpose: 'prefetch' } }));
      if (!canStore(response, { explicit, anonymous: true })) return false;
      await cache.put(origin + path, passthrough(response));
      if (cacheName === PAGES_CACHE) await expiration.updateTimestamp(origin + path);
      return true;
    }));
    return paths.filter((path, i) => results[i].status === 'fulfilled' && results[i].value === true);
  }

  const warmPages = () => warm([...new Set([...PAGES, FALLBACK])], PAGES_CACHE);

  /** Installation : ressources marquées manquantes ou modifiées seulement ; retrait de celles qui ne le sont plus. */
  async function installResources() {
    const state = await readState();
    const revs = markedRevisions(config.marked, origin);
    const device = new Set(state.device || []);
    const cache = await caches.open(RESOURCES_CACHE);
    const cached = await cachedPaths(cache);
    const dropped = Object.keys(state.marked).filter((path) => !(path in revs) && !device.has(path));
    await Promise.all(dropped.map((path) => cache.delete(origin + path)));
    const current = Object.keys(revs).filter((path) => cached.has(path) && state.marked[path] === revs[path]);
    const loaded = await warm(Object.keys(revs).filter((path) => !current.includes(path)), RESOURCES_CACHE, true);
    const marked = {};
    [...current, ...loaded].forEach((path) => { marked[path] = revs[path]; });
    await patchState({ marked });
  }

  /** Bibliothèque de l'appareil : ajoute ce qui manque, retire ce qui n'est plus enregistré ni marqué. */
  async function syncResources(detail = {}) {
    const device = offlineResourcePaths({ items: detail.items }, origin);
    const marked = offlineResourcePaths({ marked: [...(config.marked || []), ...(Array.isArray(detail.marked) ? detail.marked : [])] }, origin);
    const wanted = new Set([...device, ...marked]);
    const cache = await caches.open(RESOURCES_CACHE);
    const cached = await cachedPaths(cache);
    await Promise.all([...cached].filter((path) => !wanted.has(path)).map((path) => cache.delete(origin + path)));
    await warm([...wanted].filter((path) => !cached.has(path)), RESOURCES_CACHE, true);
    await patchState({ device });
  }

  /** Déconnexion : pages et fiches des ressources rechargées sans cookie ; les fichiers (publics) restent. */
  async function resetPages() {
    await caches.delete(PAGES_CACHE);
    const cache = await caches.open(RESOURCES_CACHE);
    const pages = [...(await cachedPaths(cache))].filter(isResourcePage);
    await Promise.all(pages.map((path) => cache.delete(origin + path)));
    await Promise.all([warmPages(), warm(pages, RESOURCES_CACHE, true)]);
  }

  /** Consultation : seules les pages rendues sans compte sont gardées (ou rafraîchies, pour une fiche enregistrée). */
  async function store(url, response) {
    if (!canStore(response) || !isGuestResponse(response)) return;
    if (isStoredPage(url, PAGES)) {
      const cache = await caches.open(PAGES_CACHE);
      await cache.put(pageKey(url), response);
      await expiration.updateTimestamp(pageKey(url));
      await expiration.expireEntries();
    } else if (isResourcePage(url.pathname)) {
      const cache = await caches.open(RESOURCES_CACHE);
      if (await cache.match(pageKey(url))) await cache.put(pageKey(url), response);
    }
  }

  async function cachedCopy(url) {
    const match = (await caches.match(pageKey(url))) || (await caches.match(origin + FALLBACK));
    return match ? rewrite(match, markOfflineCopy(await match.text())) : null;
  }

  function offlineResponse() {
    return new Response(offlineDocument(config.texts || {}, config.numbers || []), { status: 503, headers: { 'Content-Type': 'text/html; charset=UTF-8', 'Cache-Control': 'no-store' } });
  }

  async function navigate({ request, url, event }) {
    const network = fetch(request).then((response) => {
      event.waitUntil(store(url, response.clone()).catch(() => undefined));
      return response;
    });
    event.waitUntil(network.then(() => undefined, () => undefined));
    const timer = new Promise((resolve) => { setTimeout(resolve, TIMEOUT); })
      .then(() => caches.match(pageKey(url)))
      .then((match) => (match ? cachedCopy(url) : network));
    try {
      return await Promise.race([network, timer]);
    } catch {
      return (await cachedCopy(url)) || offlineResponse();
    }
  }

  async function resourceFile({ request, url }) {
    const cached = await (await caches.open(RESOURCES_CACHE)).match(origin + url.pathname);
    if (!cached) return fetch(request);
    return request.headers.has('range') ? partial(request, cached) : cached;
  }

  const install = () => Promise.all([warmPages(), installResources()]);

  const activate = () => caches.keys().then((names) => Promise.all(names
    .filter((name) => /^xomiton-/.test(name) && !KNOWN_CACHES.includes(name))
    .map((name) => caches.delete(name))));

  /** Déconnexion observée sans y répondre (le navigateur envoie le POST comme d'habitude). */
  function observe(event) {
    const url = new URL(event.request.url);
    if (event.request.method === 'POST' && sameOrigin(url) && LOGOUT_PATHS.includes(url.pathname)) event.waitUntil(resetPages());
  }

  function message(event) {
    if (event.origin && event.origin !== origin) return;
    const data = event.data && typeof event.data === 'object' ? event.data : {};
    if (data.type === 'offline-resources') event.waitUntil(syncResources(data));
  }

  return {
    warm, warmPages, installResources, syncResources, resetPages, store, cachedCopy, navigate, resourceFile, install,
    activate, observe, message, readState,
  };
}
