// Enregistrement du service worker (P5-10, ADR-26), importé par public.js : seulement si le gabarit l'annonce
// (`<meta name="xm-offline" content="/sw.js">`, gabarits public et sobre ; vide pour l'espace connecté et le tunnel
// d'accès, absent de l'administration), et seulement en build de production (en développement, le script de
// public/build ne correspond pas aux fichiers servis par Vite). Portée « / ».
// Relais de la bibliothèque hors connexion (resources/js/public/ressources.js) : l'évènement `xm:offline-resources`
// devient un message `offline-resources` au service worker actif, qui met en cache ou retire pages et fichiers.

export function registerServiceWorker(opts = {}) {
  const win = opts.window || window;
  const doc = opts.document || document;
  const container = opts.serviceWorker === undefined ? (win.navigator && win.navigator.serviceWorker) : opts.serviceWorker;
  const enabled = opts.enabled === undefined ? import.meta.env.PROD : opts.enabled;
  const meta = doc.querySelector('meta[name="xm-offline"]');
  const url = meta ? meta.getAttribute('content') || '' : '';

  const post = (message) => {
    if (!container || !container.ready) return;
    container.ready.then((registration) => {
      if (registration && registration.active) registration.active.postMessage(message);
    }).catch(() => undefined);
  };

  win.addEventListener('xm:offline-resources', (event) => {
    const detail = event.detail || {};
    post({ type: 'offline-resources', action: detail.action, items: detail.items || {}, marked: detail.marked || [] });
  });

  if (!enabled || !container || url === '') return null;
  const register = () => container.register(url, { scope: '/' }).catch(() => null);
  if (doc.readyState === 'complete') return register();
  return new Promise((resolve) => {
    win.addEventListener('load', () => resolve(register()), { once: true });
  });
}

if (typeof window !== 'undefined' && typeof document !== 'undefined' && import.meta.env.MODE !== 'test') registerServiceWorker();
