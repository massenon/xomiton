// Coquille de l'administration (P10-01) : barre latérale repliable en icônes (≥ 992 px, état mémorisé sur l'appareil),
// plein écran, raccourci Ctrl K / ⌘ K vers la recherche globale, état en ligne / hors connexion, onglets de la cloche au
// clavier (flèches, Début, Fin). L'offcanvas < 992 px est géré par Bootstrap (data-bs-toggle="offcanvas").
export const SIDEBAR_KEY = 'xm-admin-sidebar';
const COLLAPSED = 'xm-sidebar-collapsed';

const store = {
  get() { try { return window.localStorage.getItem(SIDEBAR_KEY); } catch (e) { return null; } },
  set(value) { try { window.localStorage.setItem(SIDEBAR_KEY, value); } catch (e) { /* stockage indisponible */ } },
};

export function syncCollapseButton(doc = document) {
  const btn = doc.getElementById('xmSidebarCollapse');
  if (!btn) return;
  const collapsed = doc.documentElement.classList.contains(COLLAPSED);
  const label = collapsed ? btn.dataset.labelExpand : btn.dataset.labelCollapse;
  btn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
  if (label) {
    btn.title = label;
    const sr = btn.querySelector('[data-xm-collapse-label]');
    if (sr) sr.textContent = label;
  }
  const icon = btn.querySelector('.bi');
  if (icon) icon.className = `bi ${collapsed ? 'bi-chevron-double-right' : 'bi-chevron-double-left'}`;
}

export function toggleSidebar(doc = document) {
  const collapsed = doc.documentElement.classList.toggle(COLLAPSED);
  store.set(collapsed ? 'collapsed' : 'expanded');
  syncCollapseButton(doc);
  return collapsed;
}

export function bindSidebar(doc = document) {
  if (store.get() === 'collapsed') doc.documentElement.classList.add(COLLAPSED);
  syncCollapseButton(doc);
  const btn = doc.getElementById('xmSidebarCollapse');
  if (btn) btn.addEventListener('click', () => toggleSidebar(doc));
}

let fullscreenSync = null;

export function bindFullscreen(doc = document) {
  // Navigation sans rechargement : le bouton est recréé à chaque page ; l'écouteur de `document` de la page précédente est retiré.
  if (fullscreenSync) { doc.removeEventListener('fullscreenchange', fullscreenSync); fullscreenSync = null; }
  const btn = doc.getElementById('xmFullscreen');
  if (!btn) return;
  if (!doc.documentElement.requestFullscreen) { btn.classList.add('d-none'); btn.classList.remove('d-sm-inline-block'); return; }
  const sync = () => {
    const on = Boolean(doc.fullscreenElement);
    const label = on ? btn.dataset.labelExit : btn.dataset.labelEnter;
    btn.innerHTML = `<i class="bi ${on ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'}" aria-hidden="true"></i>`;
    if (label) { btn.setAttribute('aria-label', label); btn.title = label; }
  };
  btn.addEventListener('click', () => {
    const p = doc.fullscreenElement ? doc.exitFullscreen() : doc.documentElement.requestFullscreen();
    if (p && typeof p.catch === 'function') p.catch(() => {});
  });
  fullscreenSync = sync;
  doc.addEventListener('fullscreenchange', sync);
}

/** Zones où Ctrl K appartient à l'éditeur (Quill : insérer un lien) ou à une fenêtre ouverte. */
const SHORTCUT_EXCLUDED = '[contenteditable]:not([contenteditable="false"]), .ql-editor, .modal, .swal2-container';

/**
 * Ctrl K / ⌘ K : focus sur la recherche globale quand elle est visible (ACCESSIBILITE.md §2, raccourci documenté).
 * Ignoré si un autre gestionnaire l'a déjà traité, depuis un éditeur riche ou un champ contenteditable, et tant qu'une
 * modale (Bootstrap ou SweetAlert2) est ouverte.
 */
export function handleShortcut(e, doc = document) {
  if (!(e.ctrlKey || e.metaKey) || (e.key || '').toLowerCase() !== 'k') return false;
  if (e.defaultPrevented) return false;
  const target = e.target && typeof e.target.closest === 'function' ? e.target : null;
  if (target && target.closest(SHORTCUT_EXCLUDED)) return false;
  if (doc.querySelector('.modal.show, .swal2-container.swal2-backdrop-show')) return false;
  const input = doc.getElementById('xmGlobalSearch');
  if (!input || input.offsetParent === null) return false;
  e.preventDefault();
  input.focus();
  if (typeof input.select === 'function') input.select();
  return true;
}

export function syncOnline(online, doc = document) {
  doc.body.classList.toggle('xm-is-offline', !online);
  doc.querySelectorAll('[data-xm-online-state]').forEach((badge) => {
    badge.classList.toggle('bg-success', online);
    badge.classList.toggle('bg-secondary', !online);
    const label = online ? badge.dataset.labelOnline : badge.dataset.labelOffline;
    if (label) badge.textContent = label;
  });
}

/** Onglets de la cloche (role="tab", tabindex itinérant) : flèches gauche / droite, Début, Fin. */
export function handleTabKeys(e) {
  const tab = e.target && e.target.closest ? e.target.closest('[data-xm-admin-bell] [role="tab"]') : null;
  if (!tab) return false;
  const tabs = Array.from(tab.parentElement.querySelectorAll('[role="tab"]'));
  const i = tabs.indexOf(tab);
  const next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
  if (next === undefined) return false;
  e.preventDefault();
  const target = tabs[(next + tabs.length) % tabs.length];
  target.focus();
  target.click();
  return true;
}

/** Réponse 419 de EnforceIdleTimeout : session fermée pour inactivité (et non simple jeton CSRF périmé). */
export function isIdleExpiry(status, content) {
  return status === 419 && typeof content === 'string' && content.includes('"session_idle"');
}

/**
 * Session fermée pour inactivité pendant un polling (cloche 30 s) : la page se recharge et part vers la connexion,
 * sans la boîte « This page has expired » de Livewire ; l'écran ne reste pas affiché sur un poste partagé.
 * À appeler avant le démarrage de Livewire.
 */
export function bindSessionExpiry(win = window) {
  let done = false;
  const register = () => {
    if (done || !win.Livewire || typeof win.Livewire.hook !== 'function') return;
    done = true;
    win.Livewire.hook('request', ({ fail }) => {
      fail(({ status, content, preventDefault }) => {
        if (!isIdleExpiry(status, content)) return;
        preventDefault();
        win.location.reload();
      });
    });
  };
  if (win.Livewire) register();
  else win.document.addEventListener('livewire:init', register, { once: true });
}

/** Éléments propres à chaque page (barre supérieure remplacée à chaque navigation) : appelé aussi sur `livewire:navigated`. */
export function bindPageShell(doc = document, win = window) {
  bindFullscreen(doc);
  syncOnline(win.navigator.onLine !== false, doc);
}

/** Une seule fois par document : barre latérale persistée, raccourcis clavier, état de la connexion. */
export function bindShell(doc = document, win = window) {
  bindSidebar(doc);
  doc.addEventListener('keydown', (e) => { handleShortcut(e, doc) || handleTabKeys(e); });
  win.addEventListener('online', () => syncOnline(true, doc));
  win.addEventListener('offline', () => syncOnline(false, doc));
  bindPageShell(doc, win);
}
