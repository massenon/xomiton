/* Xomiton – service worker : mise en cache des pages clés et ressources pour le mode hors connexion. */
const CACHE = 'xomiton-v1';
const CORE = ['assets/css/style.css', 'assets/css/xomiton.css', 'assets/css/xomiton-fonts.css', 'assets/js/xomiton.js', 'assets/js/functions.js',
  'assets/vendor/bootstrap/js/bootstrap.bundle.min.js', 'assets/images/logo.svg', 'urgences.php', 'ressources.php', 'annuaire.php', 'signalement.php', 'sos.php', 'collecte.php'];
self.addEventListener('install', e => { e.waitUntil(caches.open(CACHE).then(c => Promise.allSettled(CORE.map(u => c.add(u)))).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(k => Promise.all(k.filter(x => x !== CACHE).map(x => caches.delete(x)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request; if (r.method !== 'GET' || r.url.includes('api.php')) return;
  e.respondWith(fetch(r).then(res => { const copy = res.clone(); if (res.ok) caches.open(CACHE).then(c => c.put(r, copy)); return res; })
    .catch(() => caches.match(r).then(m => m || caches.match('urgences.php'))));
});
