// @vitest-environment jsdom
// « Charger plus » (P4-02) : remplacement de #liste et du bloc [data-xm-more], focus, URL, repli en navigation classique
// (réponse sans liste ou fetch rejeté), clic ignoré pendant aria-busy.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyNextPage, onLoadMoreClick } from './load-more.js';

const page = (cards, more) => `
  <section id="liste"><h2>Liste</h2><ul data-xm-items>${cards.map((c) => `<li><a href="/thematiques/${c}">${c}</a></li>`).join('')}</ul></section>
  ${more ? `<div data-xm-more><a href="${more}">Charger plus</a></div>` : '<p data-xm-more>Vous avez tout vu.</p>'}`;

const click = (link, extra = {}) => {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true, ...extra });
  Object.defineProperty(event, 'target', { value: link });
  return event;
};

describe('load-more', () => {
  beforeEach(() => {
    document.body.innerHTML = page(['a', 'b'], 'http://localhost/thematiques?n=12#liste');
    vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('remplace la liste et le bloc suivant, met l’URL à jour sans ancre et focalise le premier ajout', () => {
    expect(applyNextPage(document, page(['a', 'b', 'c', 'd']), 'http://localhost/thematiques?n=12#liste')).toBe(true);

    expect(document.querySelectorAll('[data-xm-items] li')).toHaveLength(4);
    expect(document.querySelector('[data-xm-more]').textContent).toBe('Vous avez tout vu.');
    expect(window.history.replaceState).toHaveBeenCalledWith(null, '', 'http://localhost/thematiques?n=12');
    expect(document.activeElement.getAttribute('href')).toBe('/thematiques/c');
  });

  it('signale une réponse sans liste pour laisser la navigation classique', () => {
    expect(applyNextPage(document, '<p>Erreur</p>', '/x')).toBe(false);
    expect(document.querySelectorAll('[data-xm-items] li')).toHaveLength(2);
  });

  it('récupère la page suivante au clic et marque le lien occupé', async () => {
    const fetch = vi.fn().mockResolvedValue({ ok: true, text: async () => page(['a', 'b', 'c'], 'http://localhost/thematiques?n=18#liste') });
    vi.stubGlobal('fetch', fetch);
    const link = document.querySelector('[data-xm-more] a');
    const event = click(link);

    await onLoadMoreClick(event);

    expect(event.defaultPrevented).toBe(true);
    expect(link.getAttribute('aria-busy')).toBe('true');
    expect(fetch).toHaveBeenCalledWith('http://localhost/thematiques?n=12#liste', expect.objectContaining({ credentials: 'same-origin' }));
    expect(document.querySelectorAll('[data-xm-items] li')).toHaveLength(3);
    expect(document.querySelector('[data-xm-more] a').getAttribute('href')).toBe('http://localhost/thematiques?n=18#liste');
  });

  it('retombe sur la navigation classique quand fetch échoue', async () => {
    // Lien vers la page courante + ancre : jsdom n'exécute que ce type de navigation (changement d'ancre).
    const target = `${window.location.href.split('#')[0]}#repli`;
    document.querySelector('[data-xm-more] a').setAttribute('href', target);
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    const event = click(document.querySelector('[data-xm-more] a'));

    await onLoadMoreClick(event);

    expect(event.defaultPrevented).toBe(true);
    expect(window.location.hash).toBe('#repli');
    expect(document.querySelectorAll('[data-xm-items] li')).toHaveLength(2);
  });

  it('ignore un second clic pendant le chargement : un seul fetch', async () => {
    let resolve;
    const fetch = vi.fn(() => new Promise((done) => { resolve = done; }));
    vi.stubGlobal('fetch', fetch);
    const link = document.querySelector('[data-xm-more] a');

    const first = onLoadMoreClick(click(link));
    const second = click(link);
    await onLoadMoreClick(second);

    expect(fetch).toHaveBeenCalledTimes(1);
    expect(second.defaultPrevented).toBe(true);

    resolve({ ok: true, text: async () => page(['a', 'b', 'c']) });
    await first;
    expect(document.querySelectorAll('[data-xm-items] li')).toHaveLength(3);
    expect(document.activeElement.getAttribute('href')).toBe('/thematiques/c');
  });

  it('laisse faire le navigateur avec Ctrl ou Cmd (nouvel onglet)', async () => {
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    const event = click(document.querySelector('[data-xm-more] a'), { ctrlKey: true });

    await onLoadMoreClick(event);

    expect(event.defaultPrevented).toBe(false);
    expect(fetch).not.toHaveBeenCalled();
  });
});
