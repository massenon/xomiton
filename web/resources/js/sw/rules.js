// Règles du service worker (P5-10, ADR-26), sans dépendance au navigateur : quelles requêtes il traite, quelles pages il
// garde, quelles réponses il peut stocker. Testées par Vitest (rules.test.js) ; worker.js les applique (worker.test.js),
// sw.js n'en est que le câblage Workbox.
// Périmètre repris de prototype_social/sw.js et PERFORMANCE.md §7 : coquille précachée, pages de crise et de ressources
// en « network-first » avec repli sur le cache puis /urgences ; jamais l'administration, l'API (hors référentiels GET),
// le tunnel d'accès, l'OTP ni les fichiers privés signés.

export const PAGES_CACHE = 'xomiton-pages';
export const RESOURCES_CACHE = 'xomiton-ressources';
export const API_CACHE = 'xomiton-api';
/** État du service worker : ressources enregistrées par l'appareil, révisions des ressources marquées déjà chargées. */
export const META_CACHE = 'xomiton-sw-meta';

/** En-tête posé par le serveur sur une page rendue pour un·e visiteur·se sans session ni message flash (TagGuestResponse). */
export const AUDIENCE_HEADER = 'X-Xm-Audience';

/** Pages gardées sans réseau et préchargées à l'installation (repli : /urgences). Remplacées par la config injectée. */
export const DEFAULT_PAGES = ['/urgences', '/sos', '/signaler', '/annuaire', '/ressources'];
export const DEFAULT_FALLBACK = '/urgences';

/** Chemins jamais servis par le service worker (le navigateur va au réseau comme sans lui). */
const EXCLUDED = [
  /^\/admin(?:\/|$)/,
  /^\/api(?:\/|$)/,
  /^\/sanctum(?:\/|$)/,
  /^\/livewire(?:\/|$)/,
  /^\/media\//,
  /^\/storage\//,
  /^\/build\//,
  /^\/up$/,
  /^\/sw\.js$/,
  /^\/design-system(?:\/|$)/,
  /^\/connexion(?:\/|$)/,
  /^\/inscription(?:\/|$)/,
  /^\/deconnexion$/,
  /^\/mot-de-passe-oublie(?:\/|$)/,
  /^\/nouveau-mot-de-passe(?:\/|$)/,
  /^\/code-de-verification(?:\/|$)/,
  /^\/otp(?:\/|$)/,
];

/** Référentiels publics lus en GET (P3-06) et contacts d'urgence (P5-06) : gardés pour la saisie sans réseau. */
const REFERENTIALS = /^\/api\/v1\/(?:zones|violence-types|needs|urgences)$/;

const SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';
const THEMATIQUE = new RegExp(`^/thematiques(?:/${SLUG})?$`);
const RESOURCE_PAGE = new RegExp(`^/ressources/${SLUG}$`);
const RESOURCE_FILE = new RegExp(`^/ressources/${SLUG}/(?:lire|telecharger)$`);

export function isExcluded(pathname) {
  return EXCLUDED.some((pattern) => pattern.test(pathname));
}

export function isReferential(pathname, method = 'GET') {
  return method === 'GET' && REFERENTIALS.test(pathname);
}

/** Page gardée en cache à chaque consultation : liste configurée et thématiques consultées (adresse sans paramètre). */
export function isStoredPage(url, pages = DEFAULT_PAGES) {
  if (url.search !== '') return false;
  return pages.includes(url.pathname) || THEMATIQUE.test(url.pathname);
}

export function isResourcePage(pathname) {
  return RESOURCE_PAGE.test(pathname);
}

export function isResourceFile(pathname) {
  return RESOURCE_FILE.test(pathname);
}

/** Clé de cache d'une page : origine + chemin, sans paramètre (une recherche n'est jamais gardée). */
export function pageKey(url) {
  return `${url.origin}${url.pathname}`;
}

const header = (response, name) => (response && response.headers && typeof response.headers.get === 'function' ? response.headers.get(name) || '' : '');

/**
 * Page rendue sans compte (`X-Xm-Audience: guest`) : la seule qu'une consultation peut garder. Une page d'une session
 * (zone et commune du profil sur /sos, en-tête membre, /annuaire) n'est jamais servie à la personne suivante.
 */
export function isGuestResponse(response) {
  return header(response, AUDIENCE_HEADER).toLowerCase() === 'guest';
}

/**
 * Réponse stockable : 200 de la même origine, ni redirection (une page de connexion à la place de /annuaire), ni
 * `no-store` (/signaler réaffiché avec les réponses d'un envoi refusé, suivi d'un SOS `?alerte=`). `anonymous` : page
 * chargée sans cookie par le service worker (formulaire vierge, aucune donnée personnelle), gardée malgré `no-store`.
 * `explicit` : ressource marquée hors ligne, gardée même redirigée vers son lien signé.
 */
export function canStore(response, { explicit = false, anonymous = false } = {}) {
  if (!response || response.status !== 200) return false;
  if (response.type && !['basic', 'default'].includes(response.type)) return false;
  if (!explicit && response.redirected) return false;
  return explicit || anonymous || !/no-store/i.test(header(response, 'Cache-Control'));
}

/** Chemin d'une adresse (absolue ou relative) de la même origine, sinon null. */
export function samePath(href, origin) {
  if (typeof href !== 'string' || href === '') return null;
  try {
    const url = new URL(href, origin);
    return url.origin === origin ? url.pathname : null;
  } catch {
    return null;
  }
}

/**
 * Adresses à garder pour la bibliothèque hors connexion (ressources.js, événement `xm:offline-resources`) : pages et
 * fichiers des ressources enregistrées sur l'appareil (`items`, indexé par slug) et marquées par l'équipe (`marked`).
 */
export function offlineResourcePaths({ items = {}, marked = [] } = {}, origin = 'http://localhost') {
  const entries = [
    ...Object.values(items && typeof items === 'object' ? items : {}),
    ...(Array.isArray(marked) ? marked : []),
  ];
  const paths = new Set();
  for (const entry of entries) {
    if (!entry || typeof entry !== 'object') continue;
    const page = samePath(entry.url, origin);
    if (page && isResourcePage(page)) paths.add(page);
    const file = samePath(entry.file, origin);
    if (file && isResourceFile(file)) paths.add(file);
  }
  return [...paths].sort();
}

/** Marque une copie servie depuis le cache (`<html data-xm-offline-copy>`) : la page sait que son jeton CSRF est périmé. */
export function markOfflineCopy(html) {
  return String(html).replace(/<html(?=[\s>])/i, '<html data-xm-offline-copy');
}

/** Numéro composable pour un lien tel: (chiffres et « + » de tête seulement), ou ''. */
export function telHref(number) {
  const digits = String(number || '').trim().replace(/(?!^\+)[^\d]/g, '');
  return /\d/.test(digits) ? `tel:${digits}` : '';
}

/**
 * Page minimale quand ni la page ni /urgences ne sont en cache (premier passage sans réseau) : texte et numéros
 * d'urgence du réglage `emergency_numbers` ({ label, number }, injectés par ServiceWorkerScript) en liens tel:.
 */
export function offlineDocument(texts = {}, numbers = []) {
  const esc = (value) => String(value || '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const items = (Array.isArray(numbers) ? numbers : [])
    .filter((entry) => entry && telHref(entry.number) !== '')
    .map((entry) => `<li style="margin:.5rem 0"><a href="${esc(telHref(entry.number))}" style="display:inline-block;padding:.6rem 0;font-weight:700">`
      + `${esc(entry.label)}&#160;: ${esc(entry.number)}</a></li>`)
    .join('');
  const list = items === '' ? '' : `<h2 style="font-size:1.1rem">${esc(texts.numbers)}</h2><ul style="list-style:none;padding:0">${items}</ul>`;
  return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    + `<title>${esc(texts.title)}</title></head><body style="font-family:Arial,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem">`
    + `<h1 style="font-size:1.4rem">${esc(texts.title)}</h1><p>${esc(texts.text)}</p>${list}</body></html>`;
}
