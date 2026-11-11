// Lois et codes du Bénin /lois (P4-04 ; prototype lois-benin.php l.73-90) : composant Alpine xmLaws, chargé à la
// demande par public.js. Recherche plein texte et filtre par catégorie sur le balisage rendu par le serveur
// ([data-law] : data-cat, data-text), sans requête réseau ; recherche insensible à la casse et aux accents
// (« harcelement » trouve « harcèlement »). Interrupteur « Lire en version simple » : préférence de l'appareil dans
// localStorage `xm_law_simple` ('1' / '0', clé du prototype), lecture et écriture tolérantes (stockage indisponible).
// Accordéon : le serveur rend tous les panneaux ouverts (lisibles sans JavaScript ou si ce module ne charge pas) ;
// init les replie, sauf le texte de l'adresse `#texte-{slug}`. Aucun texte en dur : libellés reçus de Blade.

export const SIMPLE_KEY = 'xm_law_simple';

/** Minuscules, sans accents ni espaces superflus. */
export const normalize = (text) => String(text ?? '')
  .normalize('NFD')
  .replace(/[̀-ͯ]/gu, '')
  .toLowerCase()
  .replace(/\s+/gu, ' ')
  .trim();

/** Un texte correspond-il à la recherche (normalisée) et à la catégorie (exacte, vide = toutes) ? */
export const matches = (item, query = '', category = '') =>
  (!category || item.cat === category) && (!query || item.text.includes(query));

const storage = () => {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
};

export const readSimple = (store = storage()) => {
  try {
    return store?.getItem(SIMPLE_KEY) === '1';
  } catch {
    return false;
  }
};

export const writeSimple = (on, store = storage()) => {
  try {
    store?.setItem(SIMPLE_KEY, on ? '1' : '0');
  } catch {
    // Stockage plein ou interdit : la préférence vaut pour cette visite seulement.
  }
};

/** Libellé du compteur (aucun, un, plusieurs). */
export const countLabel = (count, texts = {}) => {
  if (count === 0) return texts.none || '';
  if (count === 1) return texts.one || '';
  return String(texts.many || '').replace(':count', String(count));
};

/** Textes [data-law] de `root` avec leur texte de recherche normalisé. */
export const lawItems = (root) => [...(root?.querySelectorAll('[data-law]') || [])].map((el) => ({
  el,
  cat: el.dataset.cat || '',
  text: normalize(el.dataset.text || el.textContent),
}));

/** Masque les textes hors filtre ; rend le nombre de textes visibles. */
export const applyFilter = (items, query, category) => {
  const needle = normalize(query);
  let visible = 0;
  items.forEach((item) => {
    const ok = matches(item, needle, category);
    item.el.hidden = !ok;
    if (ok) visible += 1;
  });
  return visible;
};

/** Version simple ou juridique dans tous les textes de `root`. */
export const applyMode = (root, simple) => {
  root?.querySelectorAll('[data-law-legal]').forEach((el) => { el.hidden = simple; });
  root?.querySelectorAll('[data-law-simple]').forEach((el) => { el.hidden = !simple; });
};

/** Ouvre ou replie un texte (classes et ARIA de l'accordéon Bootstrap, sans dépendre de son JavaScript). */
export const setOpen = (item, open) => {
  const panel = item?.querySelector('.accordion-collapse');
  const button = item?.querySelector('.accordion-button');
  if (!panel || !button) return false;
  panel.classList.toggle('show', open);
  button.classList.toggle('collapsed', !open);
  button.setAttribute('aria-expanded', open ? 'true' : 'false');
  return true;
};

/** Texte [data-law] visé par une ancre valide (#texte-…), ou null. */
const fromHash = (root, hash) => (hash && /^#texte-[a-z0-9-]+$/u.test(hash) ? root?.querySelector(`${hash}[data-law]`) ?? null : null);

/** Ouvre le texte visé par l'ancre (#texte-…). */
export const openFromHash = (root, hash) => setOpen(fromHash(root, hash), true);

/** Replie tous les textes (ouverts par le serveur), sauf celui de l'ancre ; rend vrai si l'ancre en a gardé un ouvert. */
export const foldExcept = (root, hash) => {
  const target = fromHash(root, hash);
  root?.querySelectorAll('[data-law]').forEach((item) => { setOpen(item, item === target); });
  return target !== null && setOpen(target, true);
};

/**
 * @param {{ total?: number, texts?: { none?: string, one?: string, many?: string } }} [opts]
 */
export function xmLaws(opts = {}) {
  const texts = opts.texts || {};
  let items = [];

  return {
    q: '',
    cat: '',
    simple: false,
    visible: Number(opts.total) || 0,

    init() {
      items = lawItems(this.$root);
      this.simple = readSimple();
      applyMode(this.$root, this.simple);
      this.apply();
      foldExcept(this.$root, typeof window !== 'undefined' ? window.location.hash : '');
      this.$watch('q', () => this.apply());
      this.$watch('cat', () => this.apply());
      this.$watch('simple', (on) => {
        applyMode(this.$root, on);
        writeSimple(on);
      });
    },

    apply() {
      this.visible = applyFilter(items, this.q, this.cat);
    },

    clear() {
      this.q = '';
      this.cat = '';
      this.apply();
      this.$root?.querySelector('#lois-q')?.focus();
    },

    countLabel() {
      return countLabel(this.visible, texts);
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmLaws', xmLaws);
  });
}
