// Mode séance d'une conversation en BD (P20-10, /conversations-bd/{slug}/seance) : composant Alpine xmBdSeance, chargé à
// la demande par public.js. Sans JavaScript (ou si ce module ne charge pas), toutes les cases restent affichées, pensées
// comprises, puis les questions d'animation. Avec JavaScript : une étape à la fois (les autres en `hidden`) ; sur une case
// qui contient des pensées, celles-ci restent masquées (classe `is-hiding-thoughts`) jusqu'à « Révéler les pensées » ou
// jusqu'à l'appui suivant ; dernière étape : les questions. Clavier et télécommande de présentation : → Page suivante
// Espace Entrée (avancer), ← Page précédente (revenir), P (pensées), F (plein écran), Début / Fin. Libellés reçus de Blade.

/** Étape bornée à [0, count - 1]. */
export const clampStep = (n, count) => Math.min(Math.max(0, Number(n) || 0), Math.max(0, count - 1));

/** « Case :n sur :count ». */
export const fill = (template, n, count) => String(template || '').replace(':n', String(n)).replace(':count', String(count));

const NEXT = new Set(['ArrowRight', 'PageDown', ' ', 'Enter', 'ArrowDown']);
const PREV = new Set(['ArrowLeft', 'PageUp', 'ArrowUp', 'Backspace']);

/**
 * @param {{ panels?: number, thoughts?: boolean[], texts?: Record<string, string> }} [opts]
 */
export function xmBdSeance(opts = {}) {
  const texts = opts.texts || {};
  const thoughts = Array.isArray(opts.thoughts) ? opts.thoughts.map(Boolean) : [];
  const panels = Number(opts.panels) || thoughts.length;

  return {
    step: 0,
    revealed: false,
    enhanced: false,
    fullscreen: false,

    init() {
      if (this.steps().length === 0) return;
      this.enhanced = true;
      this.$root.classList.add('is-enhanced');
      document.addEventListener('fullscreenchange', () => {
        this.fullscreen = document.fullscreenElement === this.$root;
      });
      this.show(0, false);
    },

    steps() {
      return [...(this.$root?.querySelectorAll('[data-xm-seance-step]') || [])];
    },

    count() {
      return this.steps().length;
    },

    hasThoughts() {
      return this.step < panels && thoughts[this.step] === true;
    },

    show(n, focus = true) {
      this.step = clampStep(n, this.count());
      this.steps().forEach((el, index) => {
        el.hidden = index !== this.step;
        if (index === this.step || index === this.step + 1) {
          el.querySelectorAll('img[loading="lazy"]').forEach((img) => { img.loading = 'eager'; });
        }
      });
      this.applyThoughts();
      if (focus) this.$root?.focus({ preventScroll: true });
    },

    applyThoughts() {
      this.$root?.classList.toggle('is-hiding-thoughts', this.hasThoughts() && !this.revealed);
    },

    go(n) {
      this.revealed = false;
      this.show(n);
    },

    next() {
      if (this.hasThoughts() && !this.revealed) {
        this.toggleThoughts();
        return;
      }
      if (!this.isLast()) this.go(this.step + 1);
    },

    prev() {
      if (this.isFirst()) return;
      // Retour sur une case déjà vue : ses pensées restent révélées.
      this.show(this.step - 1);
      this.revealed = true;
      this.applyThoughts();
    },

    toggleThoughts() {
      if (!this.hasThoughts()) return;
      this.revealed = !this.revealed;
      this.applyThoughts();
    },

    toggleFullscreen() {
      try {
        if (document.fullscreenElement) document.exitFullscreen?.();
        else this.$root?.requestFullscreen?.();
      } catch {
        // Plein écran refusé (navigateur, iframe) : la séance reste utilisable dans la page.
      }
    },

    onKey(event) {
      if (!this.enhanced || event.altKey || event.ctrlKey || event.metaKey) return;
      const tag = event.target?.tagName;
      // Entrée / Espace sur un bouton ou un lien : l'action du bouton seulement (pas d'avance en double).
      if ((event.key === 'Enter' || event.key === ' ') && (tag === 'BUTTON' || tag === 'A')) return;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') return;
      let handled = true;
      if (NEXT.has(event.key)) this.next();
      else if (PREV.has(event.key)) this.prev();
      else if (event.key === 'p' || event.key === 'P') this.toggleThoughts();
      else if (event.key === 'f' || event.key === 'F') this.toggleFullscreen();
      else if (event.key === 'Home') this.go(0);
      else if (event.key === 'End') this.go(this.count() - 1);
      else handled = false;
      if (handled) event.preventDefault();
    },

    isFirst() {
      return this.step <= 0;
    },

    isLast() {
      return this.step >= this.count() - 1 && !(this.hasThoughts() && !this.revealed);
    },

    status() {
      if (this.step >= panels) return texts.questions || '';
      const base = fill(texts.counter, this.step + 1, panels);
      if (!this.hasThoughts()) return base;
      return `${base} — ${this.revealed ? texts.revealed || '' : texts.hidden || ''}`;
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmBdSeance', xmBdSeance);
  });
}
