// Quiz vrai / faux de /do-glegbe (P4-04, MODULE_SPECIFICATIONS.md §A « fiches, quiz ») : composant Alpine xmQuiz, chargé
// à la demande par public.js. Verdict immédiat et corrigé ouvert à chaque réponse, score quand tout est répondu,
// « Recommencer » ; tout reste dans le navigateur (aucune requête réseau, aucun stockage). Annonces dans une seule
// région polie : verdict + corrigé (lu dans le balisage [data-quiz-explanation]), puis score. Textes reçus de Blade.

/** Bonnes réponses parmi les choix faits (null = pas encore répondu). */
export const scoreOf = (picks = [], answers = []) =>
  picks.reduce((sum, pick, index) => sum + (pick !== null && pick === answers[index] ? 1 : 0), 0);

const fill = (template, values) =>
  Object.entries(values).reduce((text, [key, value]) => text.replaceAll(`:${key}`, String(value)), String(template || ''));

/**
 * @param {{ answers?: boolean[], texts?: { correct?: string, wrong?: string, score?: string } }} [opts]
 */
export function xmQuiz(opts = {}) {
  const answers = (opts.answers || []).map((answer) => answer === true);
  const texts = opts.texts || {};

  return {
    picks: answers.map(() => null),
    announce: '',

    answer(index, value) {
      if (index < 0 || index >= answers.length) return;
      this.picks[index] = value === true;
      const right = this.isRight(index);
      const item = this.$root?.querySelectorAll('[data-quiz-item]')[index];
      const explanation = item?.querySelector('[data-quiz-explanation]')?.textContent?.trim() || '';
      const parts = [right ? texts.correct : texts.wrong, explanation];
      if (this.done()) parts.push(this.scoreLabel());
      this.announce = parts.filter(Boolean).join(' ');
    },

    isRight(index) {
      return this.picks[index] !== null && this.picks[index] === answers[index];
    },

    isWrong(index) {
      return this.picks[index] !== null && this.picks[index] !== answers[index];
    },

    done() {
      return answers.length > 0 && this.picks.every((pick) => pick !== null);
    },

    score() {
      return scoreOf(this.picks, answers);
    },

    scoreLabel() {
      return fill(texts.score, { score: this.score(), total: answers.length });
    },

    restart() {
      this.picks = answers.map(() => null);
      this.announce = '';
      this.$root?.querySelectorAll('input[type="radio"]').forEach((input) => { input.checked = false; });
      this.$root?.querySelector('input[type="radio"]')?.focus();
    },
  };
}

if (typeof document !== 'undefined') {
  document.addEventListener('alpine:init', () => {
    window.Alpine.data('xmQuiz', xmQuiz);
  });
}
