// @vitest-environment jsdom
// Visionneuse de BD publique (P19-04) : ancre #planche-n, bornes, compteur, une planche à la fois, boutons et flèches du
// clavier, composant xmComic dans un vrai Alpine ; sans JavaScript toutes les planches restent visibles.
import Alpine from 'alpinejs';
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { clamp, counterLabel, pageFromHash, xmComic } from './comic-viewer.js';

const markup = (count = 4) => `
  <section id="bd" x-data='xmComic(${JSON.stringify({ count, texts: { counter: 'Planche :n sur :count' } })})'>
    <div data-xm-comic-stage tabindex="-1" x-on:keydown="onKey($event)">
      <ol>${Array.from({ length: count }, (_, i) => `<li id="planche-${i + 1}" data-xm-comic-page><img loading="lazy" alt="Planche ${i + 1}"></li>`).join('')}</ol>
    </div>
    <div x-show="enhanced">
      <button type="button" data-prev x-on:click="prev()" x-bind:disabled="isFirst()">Précédente</button>
      <p data-counter x-text="counter()"></p>
      <button type="button" data-next x-on:click="next()" x-bind:disabled="isLast()">Suivante</button>
    </div>
  </section>`;

const flush = async () => {
  await new Promise((resolve) => { setTimeout(resolve, 0); });
  await Alpine.nextTick();
};

const visible = () => [...document.querySelectorAll('[data-xm-comic-page]')].filter((li) => !li.hidden).map((li) => li.id);

beforeAll(() => {
  window.Alpine = Alpine;
  Alpine.data('xmComic', xmComic);
  Alpine.start();
});

afterEach(() => {
  document.body.innerHTML = '';
  window.history.replaceState(null, '', '/');
});

describe('fonctions pures', () => {
  it('lit le rang dans l’ancre et le borne', () => {
    expect(pageFromHash('#planche-3', 4)).toBe(3);
    expect(pageFromHash('#planche-9', 4)).toBe(1);
    expect(pageFromHash('#autre', 4)).toBe(1);
    expect(clamp(0, 4)).toBe(1);
    expect(clamp(7, 4)).toBe(4);
    expect(counterLabel('Planche :n sur :count', 2, 4)).toBe('Planche 2 sur 4');
  });
});

describe('xmComic', () => {
  it('montre une planche à la fois, avance aux boutons et garde l’ancre', async () => {
    document.body.innerHTML = markup();
    await flush();
    expect(visible()).toEqual(['planche-1']);
    expect(document.querySelector('[data-counter]').textContent).toBe('Planche 1 sur 4');
    expect(document.querySelector('[data-prev]').disabled).toBe(true);
    // La planche suivante est demandée tout de suite (plus de chargement paresseux).
    expect(document.querySelector('#planche-2 img').loading).toBe('eager');

    document.querySelector('[data-next]').click();
    await flush();
    expect(visible()).toEqual(['planche-2']);
    expect(window.location.hash).toBe('#planche-2');
    expect(document.querySelector('[data-counter]').textContent).toBe('Planche 2 sur 4');
  });

  it('répond aux flèches gauche / droite, Début et Fin', async () => {
    document.body.innerHTML = markup();
    await flush();
    const stage = document.querySelector('[data-xm-comic-stage]');
    const key = (k) => stage.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true }));

    key('ArrowRight');
    key('ArrowRight');
    await flush();
    expect(visible()).toEqual(['planche-3']);
    key('End');
    await flush();
    expect(visible()).toEqual(['planche-4']);
    expect(document.querySelector('[data-next]').disabled).toBe(true);
    key('ArrowRight');
    await flush();
    expect(visible()).toEqual(['planche-4']);
    key('Home');
    key('ArrowLeft');
    await flush();
    expect(visible()).toEqual(['planche-1']);
  });

  it('ouvre la planche de l’ancre de l’adresse', async () => {
    window.history.replaceState(null, '', '/bd/vn01#planche-3');
    document.body.innerHTML = markup();
    await flush();
    expect(visible()).toEqual(['planche-3']);
  });
});
