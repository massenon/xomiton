// Navigation sans rechargement de l'administration (Livewire `wire:navigate`, spec Partie 1) : tout ce qui entoure le
// remplacement du <body> par Livewire. Livewire échange le <body> et fusionne le <head> ; ce module s'occupe de ce qu'il
// laisse de côté :
//  - nonce CSP : le document garde la CSP de sa première réponse ; le HTML d'une navigation porte un autre nonce, que le
//    navigateur refuserait (scripts en ligne clonés) et qui ferait ajouter chaque feuille de style une seconde fois. Le
//    nonce de la réponse est donc remplacé avant que Livewire ne lise le HTML (swapNonce) ;
//  - réponses à ne pas afficher dans l'administration (session expirée → /connexion, 419, 403, 5xx, hors ligne, page qui
//    n'est pas l'administration) : chargement complet de l'adresse demandée, sans écran blanc ;
//  - démontage des scripts d'écran (écouteurs retirés, graphiques détruits) sur `livewire:navigating` ;
//  - état que le <html> perd à chaque échange (thème, barre latérale repliée) ;
//  - barre latérale persistée : entrée active (`aria-current`) et pastilles recalculées à chaque page ;
//  - accessibilité : focus sur le h1 et annonce vocale polie « Page : <titre> » (WCAG 2.4.3).
import { adminI18n } from './i18n.js';

export const ADMIN_BASE = '/admin';
const NAV_HEADER = 'x-livewire-navigate';
const COLLAPSED = 'xm-sidebar-collapsed';

/** Nonce de la CSP d'une réponse (`'nonce-…'`), ou null. */
export function nonceFromCsp(csp) {
  const match = /'nonce-([^']+)'/.exec(String(csp || ''));
  return match ? match[1] : null;
}

/**
 * Aligne les nonces d'une réponse de navigation sur le document (valeurs base64 : aucune échappatoire nécessaire).
 *  - <body> : le nonce du document, pour que les scripts en ligne clonés par Livewire passent la CSP ;
 *  - <head> : nonce vide. Le navigateur vide l'attribut des éléments connectés (`nonce=""` dans outerHTML) ; Livewire
 *    compare ces outerHTML pour ne pas ajouter deux fois une feuille de style ou un script déjà présents, et ne
 *    reconnaîtrait rien d'autre. Un script en ligne inédit dans le <head> serait donc refusé par la CSP : le <head> d'une
 *    page ne reçoit que des feuilles de style et des scripts externes (même origine).
 */
export function swapNonce(html, from, to) {
  if (!from) return html;
  const end = html.indexOf('</head>');
  const head = end === -1 ? html : html.slice(0, end);
  const rest = end === -1 ? '' : html.slice(end);
  const needle = `nonce="${from}"`;
  return head.split(needle).join('nonce=""') + (to ? rest.split(needle).join(`nonce="${to}"`) : rest);
}

/** Nonce du document : celui de ses scripts ou feuilles de style (l'attribut est masqué, la propriété reste lisible). */
export function documentNonce(doc = document) {
  const el = doc.querySelector('script[nonce], link[nonce], style[nonce]');
  return el && el.nonce ? el.nonce : null;
}

/** Page de relais : remplace l'écran le temps d'un chargement complet de `url` (voir `leaveIfRelay`). */
export function relayHtml(url, text) {
  const safe = (value) => String(value).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  return `<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>${safe(text)}</title></head>`
    + `<body><p role="status" data-xm-nav-relay="${safe(url)}">${safe(text)}</p></body></html>`;
}

/** Vrai si la réponse ne doit pas être affichée dans l'administration. */
export function needsFullLoad(response, html, base = ADMIN_BASE) {
  if (!response || !response.ok) return true;
  if (!/text\/html/i.test(response.headers.get('content-type') || '')) return true;
  if (response.redirected) {
    try {
      const path = new URL(response.url).pathname;
      if (path !== base && !path.startsWith(`${base}/`)) return true;
    } catch (e) {
      return true;
    }
  }
  return !/<body[^>]*\bxm-admin\b/.test(html);
}

/**
 * Enveloppe `fetch` pour les seules requêtes de navigation de Livewire (en-tête `X-Livewire-Navigate`, survol compris) :
 * aligne le nonce et remplace toute réponse « hors administration » par une page de relais qui recharge l'adresse.
 */
export function installNavigationFetch(win = window) {
  if (win.__xmNavFetch) return;
  win.__xmNavFetch = true;
  const original = win.fetch.bind(win);
  const docNonce = documentNonce(win.document);

  win.fetch = async (input, init) => {
    const headers = new Headers((init && init.headers) || (input && input.headers) || {});
    if (!headers.has(NAV_HEADER)) return original(input, init);

    const requested = typeof input === 'string' ? input : (input && input.url) || String(input);
    const relay = () => {
      const response = new Response(relayHtml(requested, adminI18n().navReload), { status: 200, headers: { 'content-type': 'text/html; charset=utf-8' } });
      Object.defineProperty(response, 'url', { value: new URL(requested, win.location.href).href });
      return response;
    };

    let response;
    let html;
    try {
      response = await original(input, init);
      html = await response.text();
    } catch (e) {
      return relay();
    }
    if (needsFullLoad(response, html)) return relay();

    html = swapNonce(html, nonceFromCsp(response.headers.get('content-security-policy')), docNonce);
    const copy = new Response(html, { status: response.status, statusText: response.statusText, headers: response.headers });
    Object.defineProperty(copy, 'url', { value: response.url });
    return copy;
  };
}

/** Page de relais affichée : charge l'adresse demandée en entier (la session expirée mène alors à /connexion). */
export function leaveIfRelay(doc = document, win = window) {
  const relay = doc.querySelector('[data-xm-nav-relay]');
  if (!relay) return false;
  win.location.replace(relay.getAttribute('data-xm-nav-relay'));
  return true;
}

/** Thème et barre repliée, perdus quand Livewire réécrit les attributs de <html> (le serveur ne connaît ni l'un ni l'autre). */
export function restoreShellState(doc = document, win = window) {
  let theme = 'light';
  let collapsed = false;
  try {
    theme = win.localStorage.getItem('theme') || 'light';
    collapsed = win.localStorage.getItem('xm-admin-sidebar') === 'collapsed';
  } catch (e) { /* stockage indisponible */ }
  if (theme === 'auto') theme = win.matchMedia && win.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  doc.documentElement.setAttribute('data-bs-theme', theme);
  doc.documentElement.classList.toggle(COLLAPSED, collapsed);
}

/** État du menu posé par le gabarit (<script id="xmNavState">) : { current, badges: { clé: { text, color, label } } }. */
export function readNavState(doc = document) {
  try {
    const el = doc.getElementById('xmNavState');
    return el ? JSON.parse(el.textContent || '{}') : null;
  } catch (e) {
    return null;
  }
}

/** Met à jour la barre latérale persistée : entrée active, `aria-current`, pastilles. */
export function syncSidebar(doc = document, state = readNavState(doc)) {
  const sidebar = doc.getElementById('xmSidebar');
  if (!sidebar || !state) return;
  sidebar.querySelectorAll('a[data-xm-nav]').forEach((link) => {
    const current = link.dataset.xmNav === state.current;
    link.classList.toggle('active', current);
    if (current) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');

    const badge = (state.badges || {})[link.dataset.xmNav] || null;
    link.querySelectorAll(':scope > .badge, :scope > .visually-hidden').forEach((el) => el.remove());
    if (!badge) return;
    const pill = doc.createElement('span');
    pill.className = `badge bg-${badge.color}`;
    pill.textContent = badge.text;
    link.append(pill);
    if (badge.label) {
      pill.setAttribute('aria-hidden', 'true');
      const sr = doc.createElement('span');
      sr.className = 'visually-hidden';
      sr.textContent = `(${badge.label})`;
      link.append(sr);
    }
  });
}

/** Annonce « Page : <titre> » dans la région aria-live persistée, puis focus sur le h1 de la page. */
export function announcePage(doc = document) {
  const heading = doc.querySelector('.xm-admin-topbar h1');
  const region = doc.getElementById('xmRouteAnnouncer');
  const title = heading ? heading.textContent.trim() : doc.title;
  if (region) {
    // Texte vidé puis reposé : une annonce identique (retour sur la même page) est quand même relue.
    region.textContent = '';
    const text = (adminI18n().pageAnnounce || ':title').replace(':title', title);
    setTimeout(() => { region.textContent = text; }, 50);
  }
  if (heading) {
    if (!heading.hasAttribute('tabindex')) heading.setAttribute('tabindex', '-1');
    heading.focus({ preventScroll: true });
  }
}

/** Registre des démontages d'écran : appelé à chaque `livewire:navigating`, puis vidé. */
const teardowns = [];
let generation = 0;

export function currentGeneration() { return generation; }

/** Enregistre le démontage d'un écran monté à la génération `gen` ; démonté tout de suite si la page a déjà changé. */
export function registerTeardown(fn, gen = generation) {
  if (typeof fn !== 'function') return;
  if (gen !== generation) { try { fn(); } catch (e) { /* écran déjà détruit */ } return; }
  teardowns.push(fn);
}

export function runTeardowns() {
  generation += 1;
  teardowns.splice(0).forEach((fn) => { try { fn(); } catch (e) { /* un écran défaillant ne bloque pas la navigation */ } });
}

/**
 * Branche les évènements de navigation. `mount` (re)monte les éléments propres à la page (toasts flashés, plein écran,
 * écrans `data-xm-screen`) ; `closeOverlays` ferme ce qui ne survit pas à l'échange du <body> (offcanvas, SweetAlert2).
 */
export function bindNavigation({ mount, closeOverlays = () => {}, doc = document, win = window }) {
  installNavigationFetch(win);
  let navigating = false;

  doc.addEventListener('livewire:navigate', closeOverlays);
  // Recherche globale (formulaire GET de la barre supérieure) : même navigation qu'un lien, pas de rechargement.
  doc.addEventListener('submit', (event) => {
    const form = event.target && event.target.closest ? event.target.closest('[data-xm-navigate-form]') : null;
    if (!form || event.defaultPrevented || !win.Livewire || typeof win.Livewire.navigate !== 'function') return;
    event.preventDefault();
    const url = new URL(form.getAttribute('action') || win.location.href, win.location.href);
    new FormData(form).forEach((value, key) => { url.searchParams.set(key, String(value)); });
    win.Livewire.navigate(url.toString());
  });
  doc.addEventListener('livewire:navigating', (event) => {
    navigating = true;
    runTeardowns();
    // Après l'échange du <body>, avant la peinture : le thème et la barre repliée ne clignotent pas.
    if (event.detail && typeof event.detail.onSwap === 'function') event.detail.onSwap(() => restoreShellState(doc, win));
  });
  doc.addEventListener('livewire:navigated', () => {
    // Livewire envoie aussi `navigated` au premier chargement : seul un `navigating` préalable est une vraie navigation.
    if (!navigating) return;
    navigating = false;
    if (leaveIfRelay(doc, win)) return;
    restoreShellState(doc, win);
    syncSidebar(doc);
    mount();
    announcePage(doc);
  });
}
