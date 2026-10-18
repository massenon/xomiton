// @vitest-environment jsdom
// Quiz vrai / faux de /do-glegbe (P4-04) : score, verdict et corrigé ouvert à chaque réponse, annonce polie (verdict +
// corrigé, puis score), « Recommencer », composant xmQuiz dans un vrai Alpine — sans requête réseau ni stockage.
import Alpine from 'alpinejs';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { scoreOf, xmQuiz } from './do-glegbe.js';

const CONFIG = { answers: [false, true, false], texts: { correct: 'Bonne réponse.', wrong: 'Pas tout à fait.', score: 'Votre score : :score sur :total.' } };

const markup = () => `
  <section id="quiz" x-data='xmQuiz(${JSON.stringify(CONFIG)})'>
    <ol>${CONFIG.answers.map((_, i) => `
      <li data-quiz-item>
        <fieldset>
        <legend id="q${i}">Affirmation ${i + 1}</legend>
        <div data-quiz-choices hidden x-bind:hidden="false">
          <input type="radio" name="q${i}" id="q${i}-vrai" value="1" x-on:change="answer(${i}, true)">
          <input type="radio" name="q${i}" id="q${i}-faux" value="0" x-on:change="answer(${i}, false)">
          <span data-right x-show="isRight(${i})">Bonne réponse.</span>
          <span data-wrong x-show="isWrong(${i})">Pas tout à fait.</span>
        </div>
        </fieldset>
        <details data-quiz-details x-bind:open="picks[${i}] !== null"><summary>Voir la réponse</summary><p data-quiz-explanation>Corrigé ${i + 1}.</p></details>
      </li>`).join('')}
    </ol>
    <p data-quiz-live x-text="announce"></p>
    <div data-quiz-score hidden x-bind:hidden="!done()"><p data-score x-text="scoreLabel()"></p><button type="button" data-restart x-on:click="restart()">Recommencer</button></div>
  </section>`;

const flush = async () => {
  await new Promise((resolve) => { setTimeout(resolve, 0); });
  await Alpine.nextTick();
  // x-show applique le changement d'affichage au cadre suivant.
  await new Promise((resolve) => { setTimeout(resolve, 20); });
};

const pick = (root, index, value) => root.querySelector(`#q${index}-${value ? 'vrai' : 'faux'}`).click();

describe('fonctions pures', () => {
  it('compte les bonnes réponses données', () => {
    expect(scoreOf([false, true, null], [false, true, false])).toBe(2);
    expect(scoreOf([true, false, true], [false, true, false])).toBe(0);
    expect(scoreOf([], [])).toBe(0);
  });

  it('annonce le verdict, puis le score quand tout est répondu', () => {
    const quiz = xmQuiz(CONFIG);
    expect(quiz.done()).toBe(false);

    quiz.answer(0, false);
    expect(quiz.isRight(0)).toBe(true);
    expect(quiz.announce).toBe('Bonne réponse.');
    quiz.answer(1, false);
    expect(quiz.isWrong(1)).toBe(true);
    quiz.answer(2, false);
    expect(quiz.done()).toBe(true);
    expect(quiz.scoreLabel()).toBe('Votre score : 2 sur 3.');
    expect(quiz.announce).toBe('Bonne réponse. Votre score : 2 sur 3.');

    quiz.answer(9, true);
    expect(quiz.picks).toHaveLength(3);
  });
});

describe('composant xmQuiz dans Alpine', () => {
  let spies;

  beforeAll(async () => {
    window.Alpine = Alpine;
    Alpine.start();
    await flush();
  });

  beforeEach(() => {
    spies = { fetch: vi.fn(), setItem: vi.spyOn(Storage.prototype, 'setItem') };
    vi.stubGlobal('fetch', spies.fetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it('révèle les choix, ouvre le corrigé, donne le verdict et le score, sans réseau ni stockage', async () => {
    document.body.innerHTML = markup();
    await flush();
    const root = document.querySelector('#quiz');
    const items = [...root.querySelectorAll('[data-quiz-item]')];

    expect(items.every((item) => !item.querySelector('[data-quiz-choices]').hidden)).toBe(true);
    expect(items.every((item) => !item.querySelector('details').open)).toBe(true);
    expect(root.querySelector('[data-quiz-score]').hidden).toBe(true);

    pick(root, 0, true);
    await flush();
    expect(items[0].querySelector('details').open).toBe(true);
    expect(items[0].querySelector('[data-wrong]').style.display).toBe('');
    expect(items[0].querySelector('[data-right]').style.display).toBe('none');
    expect(root.querySelector('[data-quiz-live]').textContent).toBe('Pas tout à fait. Corrigé 1.');

    pick(root, 1, true);
    pick(root, 2, false);
    await flush();
    expect(root.querySelector('[data-quiz-score]').hidden).toBe(false);
    expect(root.querySelector('[data-score]').textContent).toBe('Votre score : 2 sur 3.');

    root.querySelector('[data-restart]').click();
    await flush();
    expect(root.querySelectorAll('input:checked')).toHaveLength(0);
    expect(items.every((item) => !item.querySelector('details').open)).toBe(true);
    expect(root.querySelector('[data-quiz-score]').hidden).toBe(true);
    expect(document.activeElement).toBe(root.querySelector('#q0-vrai'));

    expect(spies.fetch).not.toHaveBeenCalled();
    expect(spies.setItem).not.toHaveBeenCalled();
  });
});
