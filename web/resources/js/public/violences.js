// Auto-évaluation confidentielle de /violences (P4-04 ; prototype violences.php l.136-185) : composant Alpine
// xmSelfCheck, chargé à la demande par public.js. Tout se passe dans le navigateur :
//  - aucune requête réseau (ni fetch, ni XMLHttpRequest, ni sendBeacon) et aucun stockage (ni localStorage,
//    ni sessionStorage, ni IndexedDB, ni cookie) : les réponses ne vivent que dans l'état du composant ;
//  - réponses effacées quand on quitte la page (pagehide) : rien ne réapparaît par le bouton « Retour » ;
//  - changer une réponse masque le résultat affiché (il ne correspondrait plus) : « Voir mon résultat » le recalcule.
// Seuils du prototype reçus du serveur (ViolencesController) : score ≤ lowMax → « low », ≤ mediumMax → « medium »,
// au-delà → « high ». Aucun texte ici : messages et résultats viennent de Blade (lang/fr/pages/violences.php).

/** Nombre de « oui » (valeur 1) ; toute autre valeur compte pour 0. */
export const scoreOf = (answers = []) => answers.reduce((sum, answer) => sum + (answer === 1 ? 1 : 0), 0);

/** Index de la première question sans réponse (ni 1 ni 0), -1 si toutes ont une réponse. */
export const firstUnanswered = (answers = []) => answers.findIndex((answer) => answer !== 0 && answer !== 1);

/** Niveau du résultat selon les seuils du prototype. */
export const levelFor = (score, { lowMax = 1, mediumMax = 4 } = {}) => {
  if (score <= lowMax) return 'low';
  return score <= mediumMax ? 'medium' : 'high';
};

const keyOf = (answers = []) => answers.map((answer) => (answer === 0 || answer === 1 ? answer : '-')).join('');

const blank = (total) => Array.from({ length: Math.max(0, Number(total) || 0) }, () => null);

/**
 * @param {{ total?: number, lowMax?: number, mediumMax?: number, texts?: { incomplete?: string, resetDone?: string } }} [opts]
 */
export function xmSelfCheck(opts = {}) {
  const texts = opts.texts || {};
  const thresholds = { lowMax: Number(opts.lowMax ?? 1), mediumMax: Number(opts.mediumMax ?? 4) };
  let evaluated = '';

  return {
    answers: blank(opts.total),
    result: null,
    notice: '',

    init() {
      // $watch d'Alpine suit le tableau en profondeur (answers[i] = …) et rappelle après coup : le résultat n'est
      // masqué que si les réponses diffèrent de celles qui l'ont produit.
      if (typeof this.$watch === 'function') this.$watch('answers', () => this.stale());
    },

    /** Masque le résultat affiché s'il ne correspond plus aux réponses. */
    stale() {
      if (this.result !== null && keyOf(this.answers) !== evaluated) this.result = null;
    },

    /** Calcule et affiche le résultat ; sans toutes les réponses, le dit et place le focus sur la première manquante. */
    evaluate() {
      const missing = firstUnanswered(this.answers);
      if (missing !== -1) {
        this.result = null;
        this.notice = texts.incomplete || '';
        this.focusQuestion(missing);
        return null;
      }
      this.notice = '';
      evaluated = keyOf(this.answers);
      this.result = levelFor(scoreOf(this.answers), thresholds);
      const show = () => this.focusResult();
      if (typeof this.$nextTick === 'function') this.$nextTick(show);
      else show();
      return this.result;
    },

    /** « Recommencer » : efface réponses et résultat, l'annonce, revient à la première question. */
    reset() {
      this.clear();
      this.notice = texts.resetDone || '';
      this.focusQuestion(0);
    },

    /** Efface tout sans rien annoncer (départ de la page). */
    clear() {
      this.answers = blank(this.answers.length);
      this.result = null;
      this.notice = '';
      this.$root?.querySelectorAll('input[type="radio"]').forEach((input) => { input.checked = false; });
    },

    focusQuestion(index) {
      const question = this.$root?.querySelectorAll('[data-check-question]')[index];
      question?.querySelector('input[type="radio"]')?.focus();
    },

    focusResult() {
      const block = this.$root?.querySelector(`[data-check-result="${this.result}"]`);
      if (!block) return;
      block.hidden = false;
      block.focus({ preventScroll: true });
      const reduce = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;
      block.scrollIntoView?.({ behavior: reduce ? 'auto' : 'smooth', block: 'nearest' });
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmSelfCheck', xmSelfCheck);
  });
}
