// Visionneuse de BD publique `x-comic-viewer` (P19-04 ; /bd/{key}) : composant Alpine xmComic, chargé à la demande par
// public.js. Sans JavaScript (ou si ce module ne charge pas), toutes les planches restent affichées les unes sous les
// autres, avec leur légende : lisible, imprimable, indexable. Avec JavaScript : une planche à la fois (les autres en
// `hidden`), boutons Précédente / Suivante, compteur « Planche n sur N » annoncé (aria-live), flèches ← / → du clavier
// quand le focus est dans la visionneuse, ancre `#planche-n` gardée dans l'adresse (lien partageable, retour arrière
// respecté). L'impression montre toutes les planches (CSS d'impression). Aucun texte en dur : libellés reçus de Blade.

/** Rang (1…count) lu dans une ancre `#planche-n` (ou `#<anchor>-n`), sinon 1. */
export const pageFromHash = (hash, count, anchor = 'planche') => {
  const match = new RegExp(`^#${anchor}-(\\d+)$`, 'u').exec(String(hash || ''));
  const n = match ? Number(match[1]) : 1;

  return n >= 1 && n <= count ? n : 1;
};

/** Rang borné à [1, count]. */
export const clamp = (n, count) => Math.min(Math.max(1, Number(n) || 1), Math.max(1, count));

/** « Planche :n sur :count ». */
export const counterLabel = (template, n, count) => String(template || '')
  .replace(':n', String(n))
  .replace(':count', String(count));

/**
 * P20-06 : la même visionneuse lit les conversations en BD (cases, ancre `#case-n` : `anchor: 'case'`).
 *
 * @param {{ count?: number, texts?: { counter?: string }, anchor?: string }} [opts]
 */
export function xmComic(opts = {}) {
  const texts = opts.texts || {};
  const anchor = /^[a-z]+$/u.test(String(opts.anchor || '')) ? String(opts.anchor) : 'planche';

  return {
    count: Number(opts.count) || 0,
    current: 1,
    enhanced: false,

    init() {
      this.count = this.pages().length || this.count;
      if (this.count === 0) return;
      this.enhanced = true;
      this.$root.classList.add('is-enhanced');
      this.show(pageFromHash(typeof window !== 'undefined' ? window.location.hash : '', this.count, anchor), false);
    },

    pages() {
      return [...(this.$root?.querySelectorAll('[data-xm-comic-page]') || [])];
    },

    show(n, focus = true) {
      this.current = clamp(n, this.count);
      this.pages().forEach((page, index) => {
        const visible = index + 1 === this.current;
        page.hidden = !visible;
        // Chargement paresseux : l'image de la planche affichée et de la suivante sont demandées tout de suite.
        if (visible || index === this.current) {
          page.querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; });
        }
      });
      if (focus) this.$root?.querySelector('[data-xm-comic-stage]')?.focus({ preventScroll: true });
    },

    go(n) {
      const target = clamp(n, this.count);
      if (target === this.current) return;
      this.show(target);
      try {
        window.history.replaceState(null, '', `#${anchor}-${target}`);
      } catch {
        // Historique indisponible (bac à sable) : la navigation reste possible sans ancre.
      }
    },

    prev() {
      this.go(this.current - 1);
    },

    next() {
      this.go(this.current + 1);
    },

    onKey(event) {
      if (event.altKey || event.ctrlKey || event.metaKey) return;
      if (event.key === 'ArrowLeft') {
        event.preventDefault();
        this.prev();
      } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        this.next();
      } else if (event.key === 'Home') {
        event.preventDefault();
        this.go(1);
      } else if (event.key === 'End') {
        event.preventDefault();
        this.go(this.count);
      }
    },

    isFirst() {
      return this.current <= 1;
    },

    isLast() {
      return this.current >= this.count;
    },

    counter() {
      return counterLabel(texts.counter, this.current, this.count);
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmComic', xmComic);
  });
}
