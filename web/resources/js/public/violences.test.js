// @vitest-environment jsdom
// Auto-évaluation confidentielle de /violences (P4-04) : score, seuils du prototype, première question sans réponse,
// et parcours complet dans un vrai Alpine (choix, résultat, « Recommencer », départ de la page) en vérifiant
// qu'AUCUNE requête réseau ni écriture de stockage n'a lieu (fetch, XMLHttpRequest, sendBeacon, WebSocket,
// EventSource, localStorage, sessionStorage, IndexedDB, cookie).
import Alpine from 'alpinejs';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { firstUnanswered, levelFor, scoreOf, xmSelfCheck } from './violences.js';

const CONFIG = { total: 8, lowMax: 1, mediumMax: 4, texts: { incomplete: 'Répondez aux 8 questions.', resetDone: 'Réponses effacées.' } };

// Balisage rendu par resources/views/public/violences/check.blade.php (réduit à ce que lit le composant).
const markup = () => `
  <section id="auto-evaluation" x-data='xmSelfCheck(${JSON.stringify(CONFIG)})' x-on:pagehide.window="clear()">
    <p data-check-nojs x-show="false">Activez JavaScript.</p>
    <ol>${Array.from({ length: CONFIG.total }, (_, i) => `
      <li data-check-question>
        <fieldset>
          <legend id="check-q${i}">Question ${i + 1}</legend>
          <input type="radio" name="check-q${i}" id="check-q${i}-oui" value="1" autocomplete="off" x-model.number="answers[${i}]">
          <input type="radio" name="check-q${i}" id="check-q${i}-non" value="0" autocomplete="off" x-model.number="answers[${i}]">
        </fieldset>
      </li>`).join('')}
    </ol>
    <div data-check-tools hidden x-bind:hidden="false">
      <button type="button" data-evaluate x-on:click="evaluate()">Voir mon résultat</button>
      <button type="button" data-reset x-on:click="reset()">Recommencer</button>
    </div>
    <p aria-live="polite" data-check-notice x-text="notice"></p>
    ${['low', 'medium', 'high'].map((level) => `<div data-check-result="${level}" tabindex="-1" hidden x-bind:hidden="result !== '${level}'">${level}</div>`).join('')}
  </section>`;

const flush = async () => {
  await new Promise((resolve) => { setTimeout(resolve, 0); });
  await Alpine.nextTick();
};

const mount = async () => {
  document.body.innerHTML = markup();
  await flush();
  return document.querySelector('#auto-evaluation');
};

const answer = (root, index, yes) => root.querySelector(`#check-q${index}-${yes ? 'oui' : 'non'}`).click();
const visibleResult = (root) => [...root.querySelectorAll('[data-check-result]')].filter((el) => !el.hidden).map((el) => el.dataset.checkResult);

/** Espions sur tout ce qui pourrait faire sortir une réponse de l'écran. */
const watchLeaks = () => {
  const spies = {
    fetch: vi.fn(),
    beacon: vi.fn(),
    websocket: vi.fn(),
    eventSource: vi.fn(),
    idbOpen: vi.fn(),
    cookie: vi.fn(),
  };
  vi.stubGlobal('fetch', spies.fetch);
  vi.stubGlobal('WebSocket', spies.websocket);
  vi.stubGlobal('EventSource', spies.eventSource);
  vi.stubGlobal('indexedDB', { open: spies.idbOpen });
  Object.defineProperty(navigator, 'sendBeacon', { value: spies.beacon, configurable: true });
  Object.defineProperty(document, 'cookie', { configurable: true, get: () => '', set: spies.cookie });
  spies.xhrOpen = vi.spyOn(XMLHttpRequest.prototype, 'open');
  spies.xhrSend = vi.spyOn(XMLHttpRequest.prototype, 'send');
  spies.setItem = vi.spyOn(Storage.prototype, 'setItem');
  spies.getItem = vi.spyOn(Storage.prototype, 'getItem');
  return spies;
};

const expectNoLeak = (spies) => {
  Object.entries(spies).forEach(([name, spy]) => {
    expect(spy, name).not.toHaveBeenCalled();
  });
};

describe('fonctions pures', () => {
  it('compte les « oui » seulement', () => {
    expect(scoreOf([1, 0, 1, null, 1, 0, 0, 1])).toBe(4);
    expect(scoreOf([])).toBe(0);
    expect(scoreOf(['1', true, 2])).toBe(0);
  });

  it('trouve la première question sans réponse', () => {
    expect(firstUnanswered([1, 0, null, 1])).toBe(2);
    expect(firstUnanswered([1, 0, 0, 1])).toBe(-1);
    expect(firstUnanswered([undefined])).toBe(0);
  });

  it('applique les seuils du prototype (0–1 faible, 2–4 moyen, 5 et plus élevé)', () => {
    expect([0, 1, 2, 4, 5, 8].map((score) => levelFor(score))).toEqual(['low', 'low', 'medium', 'medium', 'high', 'high']);
    expect(levelFor(2, { lowMax: 2, mediumMax: 3 })).toBe('low');
  });

  it('refuse de conclure tant qu’une réponse manque, sans composant Alpine', () => {
    const check = xmSelfCheck(CONFIG);
    expect(check.answers).toEqual(Array(8).fill(null));
    expect(check.evaluate()).toBeNull();
    expect(check.notice).toBe('Répondez aux 8 questions.');

    check.answers = [1, 1, 1, 0, 0, 0, 0, 1];
    expect(check.evaluate()).toBe('medium');
    expect(check.notice).toBe('');
  });
});

describe('composant xmSelfCheck dans Alpine', () => {
  let spies;

  beforeAll(async () => {
    window.Alpine = Alpine;
    Alpine.start();
    await flush();
  });

  beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    spies = watchLeaks();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
  });

  it('révèle les boutons, masque la mention sans JavaScript, et calcule tout sans réseau ni stockage', async () => {
    const root = await mount();
    expect(root.querySelector('[data-check-tools]').hidden).toBe(false);
    expect(root.querySelector('[data-check-nojs]').style.display).toBe('none');

    [1, 1, 1, 1, 1, 1, 0, 0].forEach((value, index) => answer(root, index, value === 1));
    root.querySelector('[data-evaluate]').click();
    await flush();

    expect(visibleResult(root)).toEqual(['high']);
    expect(document.activeElement).toBe(root.querySelector('[data-check-result="high"]'));
    expectNoLeak(spies);
  });

  it('dit qu’il manque des réponses et place le focus sur la première question vide', async () => {
    const root = await mount();
    answer(root, 0, true);
    answer(root, 1, false);
    root.querySelector('[data-evaluate]').click();
    await flush();

    expect(visibleResult(root)).toEqual([]);
    expect(root.querySelector('[data-check-notice]').textContent).toBe('Répondez aux 8 questions.');
    expect(document.activeElement).toBe(root.querySelector('#check-q2-oui'));
    expectNoLeak(spies);
  });

  it('donne les trois niveaux selon le nombre de « oui »', async () => {
    const root = await mount();
    const run = async (yesCount) => {
      Array.from({ length: 8 }, (_, i) => answer(root, i, i < yesCount));
      root.querySelector('[data-evaluate]').click();
      await flush();
      return visibleResult(root);
    };

    expect(await run(1)).toEqual(['low']);
    expect(await run(3)).toEqual(['medium']);
    expect(await run(8)).toEqual(['high']);
    expectNoLeak(spies);
  });

  it('masque le résultat dès qu’une réponse change, puis le recalcule', async () => {
    const root = await mount();
    Array.from({ length: 8 }, (_, i) => answer(root, i, false));
    root.querySelector('[data-evaluate]').click();
    await flush();
    expect(visibleResult(root)).toEqual(['low']);

    [0, 1, 2, 3, 4].forEach((index) => answer(root, index, true));
    await flush();
    expect(visibleResult(root)).toEqual([]);

    root.querySelector('[data-evaluate]').click();
    await flush();
    expect(visibleResult(root)).toEqual(['high']);
    expectNoLeak(spies);
  });

  it('« Recommencer » efface réponses et résultat, l’annonce et revient à la première question', async () => {
    const root = await mount();
    Array.from({ length: 8 }, (_, i) => answer(root, i, false));
    root.querySelector('[data-evaluate]').click();
    await flush();
    expect(visibleResult(root)).toEqual(['low']);

    root.querySelector('[data-reset]').click();
    await flush();

    expect(visibleResult(root)).toEqual([]);
    expect(root.querySelectorAll('input:checked')).toHaveLength(0);
    expect(root.querySelector('[data-check-notice]').textContent).toBe('Réponses effacées.');
    expect(document.activeElement).toBe(root.querySelector('#check-q0-oui'));
    expectNoLeak(spies);
  });

  it('efface tout quand on quitte la page (rien ne revient par « Retour »)', async () => {
    const root = await mount();
    Array.from({ length: 8 }, (_, i) => answer(root, i, true));
    root.querySelector('[data-evaluate]').click();
    await flush();

    window.dispatchEvent(new Event('pagehide'));
    await flush();

    expect(visibleResult(root)).toEqual([]);
    expect(root.querySelectorAll('input:checked')).toHaveLength(0);
    expect(root.querySelector('[data-check-notice]').textContent).toBe('');
    expectNoLeak(spies);
  });
});
