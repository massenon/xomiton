// @vitest-environment jsdom
// Mode séance d'une conversation en BD (P20-10) : une étape à la fois, pensées masquées puis révélées, questions à la
// fin, clavier et télécommande (→ / Page suivante / Espace / Entrée, ← / Page précédente, P), statut annoncé.
import Alpine from 'alpinejs';
import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { clampStep, fill, xmBdSeance } from './bd-seance.js';

const options = { panels: 2, thoughts: [true, false], texts: { counter: 'Case :n sur :count', questions: 'Questions', revealed: 'Pensées révélées.', hidden: 'Pensées masquées.' } };
const markup = () => `
  <section id="seance" tabindex="-1" x-data='xmBdSeance(${JSON.stringify(options)})' x-on:keydown.window="onKey($event)">
    <ol>
      <li data-xm-seance-step><svg><g class="xm-bdc-chain xm-bdc-thought"></g></svg></li>
      <li data-xm-seance-step>Case 2</li>
      <li data-xm-seance-step>Questions</li>
    </ol>
    <p data-status x-text="status()"></p>
    <button type="button" data-reveal x-on:click="toggleThoughts()">Révéler</button>
    <button type="button" data-next x-on:click="next()" x-bind:disabled="isLast()">Suivant</button>
  </section>`;

const flush = async () => {
  await new Promise((resolve) => { setTimeout(resolve, 0); });
  await Alpine.nextTick();
};
const visible = () => [...document.querySelectorAll('[data-xm-seance-step]')].map((li, i) => (li.hidden ? null : i)).filter((i) => i !== null);
const root = () => document.getElementById('seance');
const key = (k) => window.dispatchEvent(new KeyboardEvent('keydown', { key: k, bubbles: true }));

beforeAll(() => {
  window.Alpine = Alpine;
  Alpine.data('xmBdSeance', xmBdSeance);
  Alpine.start();
});

afterEach(() => {
  document.body.innerHTML = '';
});

describe('fonctions pures', () => {
  it('borne l’étape et remplit le compteur', () => {
    expect(clampStep(-2, 3)).toBe(0);
    expect(clampStep(9, 3)).toBe(2);
    expect(fill('Case :n sur :count', 2, 5)).toBe('Case 2 sur 5');
  });
});

describe('xmBdSeance', () => {
  it('masque d’abord les pensées, les révèle à l’appui suivant, puis avance jusqu’aux questions', async () => {
    document.body.innerHTML = markup();
    await flush();
    expect(visible()).toEqual([0]);
    expect(root().classList.contains('is-hiding-thoughts')).toBe(true);
    expect(document.querySelector('[data-status]').textContent).toBe('Case 1 sur 2 — Pensées masquées.');

    document.querySelector('[data-next]').click();
    await flush();
    expect(visible()).toEqual([0]);
    expect(root().classList.contains('is-hiding-thoughts')).toBe(false);
    expect(document.querySelector('[data-status]').textContent).toBe('Case 1 sur 2 — Pensées révélées.');

    document.querySelector('[data-next]').click();
    await flush();
    expect(visible()).toEqual([1]);
    expect(document.querySelector('[data-status]').textContent).toBe('Case 2 sur 2');

    document.querySelector('[data-next]').click();
    await flush();
    expect(visible()).toEqual([2]);
    expect(document.querySelector('[data-status]').textContent).toBe('Questions');
    expect(document.querySelector('[data-next]').disabled).toBe(true);
  });

  it('se pilote au clavier ou à la télécommande', async () => {
    document.body.innerHTML = markup();
    await flush();
    key('PageDown');
    await flush();
    expect(root().classList.contains('is-hiding-thoughts')).toBe(false);
    key('ArrowRight');
    await flush();
    expect(visible()).toEqual([1]);
    key('PageUp');
    await flush();
    expect(visible()).toEqual([0]);
    expect(root().classList.contains('is-hiding-thoughts')).toBe(false);
    key('p');
    await flush();
    expect(root().classList.contains('is-hiding-thoughts')).toBe(true);
    key('End');
    await flush();
    expect(visible()).toEqual([2]);
    key('Home');
    await flush();
    expect(visible()).toEqual([0]);
  });

  it('sans étape, reste inerte (toutes les cases visibles sans JavaScript)', async () => {
    document.body.innerHTML = `<section x-data='xmBdSeance({})'></section>`;
    await flush();
    expect(document.querySelector('section').classList.contains('is-enhanced')).toBe(false);
  });
});
