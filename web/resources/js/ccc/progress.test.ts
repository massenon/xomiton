// @vitest-environment jsdom
// Progression du manuel (P8-12) : état gardé sur l'appareil seulement (localStorage par compte), lecture tolérante,
// écriture protégée, ménage des modules et chapitres disparus.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { clearSeen, loadSeen, percentOf, sanitize, saveSeen, storageKey, toggle, totals } from './progress';
import type { ManualModule } from './types';

const module = (key: string, chapters: string[]): ManualModule => ({ key, family: 'manuel', title: key, description: null, cover: null, duration_minutes: 45, chapters, chapters_count: chapters.length });
const modules = [module('m1', ['a', 'b', 'c', 'd']), module('m2', ['e', 'f'])];

afterEach(() => {
    localStorage.clear();
    vi.restoreAllMocks();
});

describe('progression du manuel', () => {
    it('garde la progression par compte, sous une clé propre', () => {
        expect(storageKey(7)).toBe('xm_ccc_manuel_v1:7');
        expect(storageKey(null)).toBe('xm_ccc_manuel_v1');
        expect(saveSeen(7, { m1: [0, 2] })).toBe(true);
        expect(loadSeen(7, modules).seen).toEqual({ m1: [0, 2] });
        expect(loadSeen(8, modules).seen).toEqual({});
        expect(clearSeen(7)).toBe(true);
        expect(loadSeen(7, modules).seen).toEqual({});
    });

    it('ignore un contenu corrompu et ne garde que les modules et rangs existants', () => {
        localStorage.setItem(storageKey(7), '{pas du json');
        expect(loadSeen(7, modules)).toEqual({ seen: {}, available: true });
        expect(sanitize({ m1: [3, 3, 9, -1, 'x', 1.5, 0], m9: [0], m2: 'non' }, modules)).toEqual({ m1: [0, 3] });
        expect(sanitize([1, 2], modules)).toEqual({});
        expect(sanitize(null, modules)).toEqual({});
    });

    it('survit à un stockage refusé (lecture, écriture, effacement)', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('bloqué'); });
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('plein'); });
        vi.spyOn(Storage.prototype, 'removeItem').mockImplementation(() => { throw new Error('bloqué'); });
        expect(loadSeen(7, modules)).toEqual({ seen: {}, available: false });
        expect(saveSeen(7, { m1: [0] })).toBe(false);
        expect(clearSeen(7)).toBe(false);
    });

    it('coche, décoche et calcule les pourcentages', () => {
        let seen = toggle({}, 'm1', 1, true);
        seen = toggle(seen, 'm1', 0, true);
        seen = toggle(seen, 'm2', 1, true);
        expect(seen).toEqual({ m1: [0, 1], m2: [1] });
        expect(totals(seen, modules)).toEqual({ done: 3, total: 6, percent: 50 });
        expect(toggle(seen, 'm2', 1, false)).toEqual({ m1: [0, 1] });
        expect(percentOf(1, 3)).toBe(33);
        expect(percentOf(0, 0)).toBe(0);
    });
});
