// @vitest-environment jsdom
// Repli de préchargement (Safari / Firefox) : lit la liste blanche des Speculation Rules, ne demande qu'une page publique
// autorisée, une seule fois, jamais une route sensible, ni avec « économiser les données ».
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { initPrefetch } from './prefetch.js';

const rules = {
  prefetch: [{ where: { and: [
    { href_matches: ['/', '/thematiques/*', '/cookies', '/ressources/*'] },
    { not: { href_matches: ['/ressources/*/telecharger'] } },
    { not: { selector_matches: '[rel~=nofollow]' } },
  ] }, eagerness: 'moderate' }],
};

const page = (links = '') => {
  document.head.innerHTML = `<script type="speculationrules">${JSON.stringify(rules)}</script>`;
  document.body.innerHTML = `<a id="ok" href="/cookies">c</a><a id="th" href="/thematiques/vbg">t</a><a id="dl" href="/ressources/x/telecharger">d</a>
    <a id="sos" href="/sos">s</a><a id="login" href="/connexion">l</a><a id="ext" href="https://example.org/cookies">e</a>
    <a id="nf" rel="nofollow" href="/cookies">n</a><a id="blank" target="_blank" href="/cookies">b</a>${links}`;
};
const over = (id) => document.getElementById(id).dispatchEvent(new MouseEvent('mouseover', { bubbles: true }));

beforeEach(() => { vi.useFakeTimers(); });

describe('initPrefetch', () => {
  it('précharge une page de la liste blanche au survol, une seule fois', () => {
    page();
    const get = vi.fn(async () => ({}));
    expect(initPrefetch({ get, native: false })).toBe(true);
    over('ok'); vi.advanceTimersByTime(150);
    over('ok'); vi.advanceTimersByTime(150);
    over('th'); vi.advanceTimersByTime(150);
    expect(get.mock.calls.map((c) => c[0])).toEqual(['/cookies', '/thematiques/vbg']);
  });

  it('ne demande jamais une route sensible, un téléchargement, un lien externe, nofollow ou target', () => {
    page();
    const get = vi.fn(async () => ({}));
    initPrefetch({ get, native: false });
    for (const id of ['dl', 'sos', 'login', 'ext', 'nf', 'blank']) { over(id); vi.advanceTimersByTime(150); }
    expect(get).not.toHaveBeenCalled();
  });

  it('précharge aussi au toucher', () => {
    page();
    const get = vi.fn(async () => ({}));
    initPrefetch({ get, native: false });
    document.getElementById('ok').dispatchEvent(new Event('touchstart', { bubbles: true }));
    expect(get).toHaveBeenCalledWith('/cookies');
  });

  it('ne fait rien si le navigateur gère les règles, avec « économiser les données », en 2G ou sans règles', () => {
    page();
    const get = vi.fn();
    expect(initPrefetch({ get, native: true })).toBe(false);
    expect(initPrefetch({ get, native: false, nav: { connection: { saveData: true } } })).toBe(false);
    expect(initPrefetch({ get, native: false, nav: { connection: { effectiveType: '2g' } } })).toBe(false);
    document.head.innerHTML = '';
    expect(initPrefetch({ get, native: false })).toBe(false);
    over('ok'); vi.advanceTimersByTime(150);
    expect(get).not.toHaveBeenCalled();
  });

  it('reste sans effet si les règles sont illisibles', () => {
    document.head.innerHTML = '<script type="speculationrules">{pas du json</script>';
    expect(initPrefetch({ get: vi.fn(), native: false })).toBe(false);
  });
});
