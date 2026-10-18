// @vitest-environment jsdom
// Lois et codes du Bénin /lois (P4-04) : recherche insensible aux accents, filtre par catégorie, compteur, version
// simple mémorisée sur l'appareil (localStorage `xm_law_simple`, stockage indisponible toléré), ancre #texte-…
// ouverte au chargement, composant xmLaws dans un vrai Alpine — sans aucune requête réseau.
import Alpine from 'alpinejs';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  SIMPLE_KEY, applyFilter, applyMode, countLabel, foldExcept, lawItems, matches, normalize, openFromHash, readSimple, writeSimple,
} from './lois.js';

const TEXTS = { none: 'Aucun texte affiché.', one: '1 texte affiché.', many: ':count textes affichés.' };
const LAWS = [
  { slug: 'loi-2011-26', cat: 'Violences', text: 'loi n°2011-26 prévention des violences, harcèlement sexuel, viol' },
  { slug: 'code-penal', cat: 'Pénal', text: 'code pénal béninois infractions et sanctions' },
  { slug: 'loi-vih', cat: 'Santé', text: 'loi n°2005-31 prévention du vih, non-discrimination' },
];

const markup = () => `
  <div id="lois" x-data='xmLaws(${JSON.stringify({ total: LAWS.length, texts: TEXTS })})'>
    <div data-law-tool hidden x-bind:hidden="false"><input type="checkbox" id="lois-simple" x-model="simple"></div>
    <div data-law-tool hidden x-bind:hidden="false">
      <input type="search" id="lois-q" x-model="q">
      <select id="lois-cat" x-model="cat"><option value="">Toutes</option>${[...new Set(LAWS.map((l) => l.cat))].map((c) => `<option value="${c}">${c}</option>`).join('')}</select>
    </div>
    <p data-law-count x-text="countLabel()"></p>
    <div id="lois-liste">${LAWS.map((law) => `
      <div class="accordion-item" id="texte-${law.slug}" data-law data-cat="${law.cat}" data-text="${law.text}">
        <button class="accordion-button" aria-expanded="true"></button>
        <div class="accordion-collapse collapse show"><p data-law-legal>Juridique</p><p data-law-simple hidden>Simple</p></div>
      </div>`).join('')}
    </div>
    <div data-law-empty hidden x-bind:hidden="visible !== 0"><button type="button" data-clear x-on:click="clear()">Effacer</button></div>
  </div>`;

const flush = async () => {
  await new Promise((resolve) => { setTimeout(resolve, 0); });
  await Alpine.nextTick();
};

const opened = (root) => [...root.querySelectorAll('[data-law]')]
  .filter((el) => el.querySelector('.accordion-collapse').classList.contains('show')).map((el) => el.id.replace('texte-', ''));

const shown = (root) => [...root.querySelectorAll('[data-law]')].filter((el) => !el.hidden).map((el) => el.id.replace('texte-', ''));

const type = (input, value) => {
  input.value = value;
  input.dispatchEvent(new Event('input', { bubbles: true }));
};

describe('fonctions pures', () => {
  it('normalise casse, accents et espaces', () => {
    expect(normalize('  Harcèlement   SEXUEL ')).toBe('harcelement sexuel');
    expect(normalize(null)).toBe('');
  });

  it('filtre par texte normalisé et par catégorie exacte', () => {
    const item = { cat: 'Santé', text: normalize('Prévention du VIH') };
    expect(matches(item, 'vih', '')).toBe(true);
    expect(matches(item, 'prevention', 'Santé')).toBe(true);
    expect(matches(item, 'vih', 'Pénal')).toBe(false);
    expect(matches(item, 'viol', '')).toBe(false);
  });

  it('écrit le compteur au singulier, au pluriel ou à zéro', () => {
    expect(countLabel(0, TEXTS)).toBe('Aucun texte affiché.');
    expect(countLabel(1, TEXTS)).toBe('1 texte affiché.');
    expect(countLabel(7, TEXTS)).toBe('7 textes affichés.');
  });

  it('masque les textes hors filtre et rend le nombre visible', () => {
    document.body.innerHTML = markup();
    const root = document.querySelector('#lois');
    const items = lawItems(root);

    expect(applyFilter(items, 'Harcelement', '')).toBe(1);
    expect(shown(root)).toEqual(['loi-2011-26']);
    expect(applyFilter(items, 'prévention', 'Santé')).toBe(1);
    expect(applyFilter(items, '', '')).toBe(3);
    expect(applyFilter(items, 'mariage', '')).toBe(0);
  });

  it('bascule version simple et version juridique', () => {
    document.body.innerHTML = markup();
    const root = document.querySelector('#lois');

    applyMode(root, true);
    expect([...root.querySelectorAll('[data-law-legal]')].every((el) => el.hidden)).toBe(true);
    expect([...root.querySelectorAll('[data-law-simple]')].every((el) => !el.hidden)).toBe(true);
    applyMode(root, false);
    expect([...root.querySelectorAll('[data-law-simple]')].every((el) => el.hidden)).toBe(true);
  });

  it('lit et écrit la préférence, et tolère un stockage indisponible', () => {
    const store = new Map();
    const fake = { getItem: (k) => store.get(k) ?? null, setItem: (k, v) => store.set(k, v) };
    expect(readSimple(fake)).toBe(false);
    writeSimple(true, fake);
    expect(store.get(SIMPLE_KEY)).toBe('1');
    expect(readSimple(fake)).toBe(true);

    const broken = { getItem: () => { throw new Error('SecurityError'); }, setItem: () => { throw new Error('QuotaExceeded'); } };
    expect(readSimple(broken)).toBe(false);
    expect(() => writeSimple(true, broken)).not.toThrow();
    expect(readSimple(null)).toBe(false);
  });

  it('replie les textes ouverts par le serveur, sauf celui d’une ancre valide', () => {
    document.body.innerHTML = markup();
    const root = document.querySelector('#lois');

    expect(foldExcept(root, '#texte-code-penal')).toBe(true);
    expect(opened(root)).toEqual(['code-penal']);
    expect(root.querySelector('#texte-loi-vih .accordion-button').classList.contains('collapsed')).toBe(true);
    expect(root.querySelector('#texte-loi-vih .accordion-button').getAttribute('aria-expanded')).toBe('false');
    expect(foldExcept(root, '#x"],[data-law')).toBe(false);
    expect(opened(root)).toEqual([]);
  });

  it('ouvre le texte visé par une ancre valide seulement', () => {
    document.body.innerHTML = markup();
    const root = document.querySelector('#lois');
    foldExcept(root, '');

    expect(openFromHash(root, '#texte-code-penal')).toBe(true);
    const item = root.querySelector('#texte-code-penal');
    expect(item.querySelector('.accordion-collapse').classList.contains('show')).toBe(true);
    expect(item.querySelector('.accordion-button').getAttribute('aria-expanded')).toBe('true');
    expect(openFromHash(root, '#texte-inconnu')).toBe(false);
    expect(openFromHash(root, '#x"],[data-law')).toBe(false);
    expect(openFromHash(root, '')).toBe(false);
  });
});

describe('composant xmLaws dans Alpine', () => {
  let fetchSpy;

  beforeAll(async () => {
    window.Alpine = Alpine;
    Alpine.start();
    await flush();
  });

  beforeEach(() => {
    localStorage.clear();
    fetchSpy = vi.fn();
    vi.stubGlobal('fetch', fetchSpy);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.innerHTML = '';
    window.location.hash = '';
  });

  it('révèle les outils, filtre en direct, compte et propose d’effacer, sans réseau', async () => {
    document.body.innerHTML = markup();
    await flush();
    const root = document.querySelector('#lois');

    expect([...root.querySelectorAll('[data-law-tool]')].every((el) => !el.hidden)).toBe(true);
    expect(root.querySelector('[data-law-count]').textContent).toBe('3 textes affichés.');

    type(root.querySelector('#lois-q'), 'prevention');
    await flush();
    expect(shown(root)).toEqual(['loi-2011-26', 'loi-vih']);
    expect(root.querySelector('[data-law-count]').textContent).toBe('2 textes affichés.');

    const select = root.querySelector('#lois-cat');
    select.value = 'Santé';
    select.dispatchEvent(new Event('change', { bubbles: true }));
    await flush();
    expect(shown(root)).toEqual(['loi-vih']);
    expect(root.querySelector('[data-law-count]').textContent).toBe('1 texte affiché.');

    type(root.querySelector('#lois-q'), 'mariage');
    await flush();
    expect(root.querySelector('[data-law-empty]').hidden).toBe(false);
    expect(root.querySelector('[data-law-count]').textContent).toBe('Aucun texte affiché.');

    root.querySelector('[data-clear]').click();
    await flush();
    expect(shown(root)).toHaveLength(3);
    expect(root.querySelector('[data-law-empty]').hidden).toBe(true);
    expect(document.activeElement).toBe(root.querySelector('#lois-q'));
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('reprend la préférence « version simple » de l’appareil et la mémorise', async () => {
    localStorage.setItem(SIMPLE_KEY, '1');
    document.body.innerHTML = markup();
    await flush();
    const root = document.querySelector('#lois');
    const toggle = root.querySelector('#lois-simple');

    expect(toggle.checked).toBe(true);
    expect([...root.querySelectorAll('[data-law-simple]')].every((el) => !el.hidden)).toBe(true);

    toggle.click();
    await flush();
    expect(localStorage.getItem(SIMPLE_KEY)).toBe('0');
    expect([...root.querySelectorAll('[data-law-legal]')].every((el) => !el.hidden)).toBe(true);
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('replie au chargement les textes ouverts par le serveur, sans ancre', async () => {
    document.body.innerHTML = markup();
    expect(opened(document.querySelector('#lois'))).toHaveLength(3);
    await flush();

    expect(opened(document.querySelector('#lois'))).toEqual([]);
  });

  it('ne garde ouvert au chargement que le texte de l’ancre', async () => {
    window.location.hash = '#texte-loi-vih';
    document.body.innerHTML = markup();
    await flush();

    expect(opened(document.querySelector('#lois'))).toEqual(['loi-vih']);
  });
});
