// Service worker Xomiton (P5-10, ADR-26) : construit par vite-plugin-pwa (stratégie injectManifest) dans public/build/sw.js,
// servi à la racine par GET /sw.js (ServiceWorkerController : portée « / », Cache-Control no-cache, ETag), qui le fait
// précéder de `self.__XM_SW__` (version, pages, ressources marquées par l'équipe et leur révision, textes et numéros
// d'urgence de la page minimale) : un changement de l'un d'eux change les octets du script, donc le navigateur installe
// la nouvelle version.
//  - Précache (Workbox) : coquille du paquet public (CSS, JS dont les modules à la demande, polices, logo), liste
//    calculée au build depuis le manifest Vite (vite.config.js) ; l'administration et l'espace Vue n'y sont pas. Les
//    paramètres d'adresse sont ignorés : le CSS appelle bootstrap-icons-….woff2?<empreinte>, précaché sans paramètre.
//  - Navigations, ressources hors ligne, déconnexion : worker.js (testé par worker.test.js), règles : rules.js.
//  - Référentiels GET de l'API : réseau d'abord (4 s), puis cache.
// Aucun POST n'est intercepté : la boîte d'envoi (shared/outbox.js) rejoue elle-même les envois.
import { clientsClaim } from 'workbox-core';
import { cleanupOutdatedCaches, precacheAndRoute } from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { NetworkFirst } from 'workbox-strategies';
import { CacheExpiration, ExpirationPlugin } from 'workbox-expiration';
import { createPartialResponse } from 'workbox-range-requests';
import { API_CACHE, PAGES_CACHE, canStore, isExcluded, isReferential, isResourceFile } from './rules.js';
import { createWorker } from './worker.js';

const ORIGIN = self.location.origin;
const worker = createWorker({
  config: self.__XM_SW__ || {},
  origin: ORIGIN,
  caches: self.caches,
  fetch: (...args) => self.fetch(...args),
  expiration: new CacheExpiration(PAGES_CACHE, { maxEntries: 60 }),
  partial: createPartialResponse,
});

self.skipWaiting();
clientsClaim();
cleanupOutdatedCaches();
precacheAndRoute(self.__WB_MANIFEST, { ignoreURLParametersMatching: [/.*/] });

const sameOrigin = (url) => url.origin === ORIGIN;

registerRoute(({ url, request }) => sameOrigin(url) && request.method === 'GET' && isResourceFile(url.pathname), worker.resourceFile);
registerRoute(({ url, request }) => sameOrigin(url) && request.mode === 'navigate' && !isExcluded(url.pathname), worker.navigate);
registerRoute(
  ({ url, request }) => sameOrigin(url) && isReferential(url.pathname, request.method),
  new NetworkFirst({
    cacheName: API_CACHE,
    networkTimeoutSeconds: 4,
    plugins: [
      { cacheWillUpdate: async ({ response }) => (canStore(response) ? response : null) },
      new ExpirationPlugin({ maxEntries: 20, maxAgeSeconds: 30 * 86400 }),
    ],
  }),
);

self.addEventListener('install', (event) => { event.waitUntil(worker.install()); });
self.addEventListener('activate', (event) => { event.waitUntil(worker.activate()); });
self.addEventListener('fetch', (event) => { worker.observe(event); });
self.addEventListener('message', (event) => { worker.message(event); });
