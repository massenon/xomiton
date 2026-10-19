// Logique de la recherche membre (lot recherche-membre, P8-13) : terme assaini comme le serveur, seuil en caractères,
// adresse de /recherche, suggesteur à anti-rebond (une requête par pause de frappe, la précédente annulée, réponse
// périmée ignorée, rien en dessous du seuil).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { canSuggest, cleanTerm, createSuggester, DEBOUNCE_MS, searchHref } from './suggest';

afterEach(() => {
    vi.useRealTimers();
});

describe('terme', () => {
    it('retire les caractères de contrôle, normalise les espaces et borne la longueur en caractères', () => {
        expect(cleanTerm('  violence \u0007  conjugale\n')).toBe('violence conjugale');
        expect(cleanTerm('é'.repeat(100))).toHaveLength(80);
        expect(cleanTerm('abc d', 4)).toBe('abc');
        expect(canSuggest('é')).toBe(false);
        expect(canSuggest('éa')).toBe(true);
        expect(canSuggest('👍')).toBe(false);
    });

    it('compose l’adresse de la page de résultats', () => {
        expect(searchHref('/recherche', 'zou & co', 'contacts')).toBe('/recherche?q=zou+%26+co&type=contacts');
        expect(searchHref('/recherche', '', null)).toBe('/recherche');
    });
});

describe('createSuggester', () => {
    it('attend la pause de frappe, n’envoie qu’une requête et annule la précédente', async () => {
        vi.useFakeTimers();
        const signals: AbortSignal[] = [];
        const load = vi.fn(async (term: string, signal: AbortSignal) => {
            signals.push(signal);
            return [term];
        });
        const onResult = vi.fn();
        const suggester = createSuggester({ load, onResult });

        suggester.schedule('vi');
        suggester.schedule('vio');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS - 1);
        expect(load).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(1);
        expect(load).toHaveBeenCalledTimes(1);
        expect(load).toHaveBeenCalledWith('vio', expect.any(AbortSignal));
        expect(onResult).toHaveBeenCalledWith(['vio'], 'vio');

        suggester.schedule('viol');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(signals[1].aborted).toBe(false);
        suggester.schedule('viole');
        expect(signals[1].aborted).toBe(true);
    });

    it('ignore une réponse arrivée après une frappe plus récente et vide la liste sous le seuil', async () => {
        vi.useFakeTimers();
        let release: (value: string[]) => void = () => undefined;
        const load = vi.fn(() => new Promise<string[]>((resolve) => { release = resolve; }));
        const onResult = vi.fn();
        const onClear = vi.fn();
        const onStart = vi.fn();
        const suggester = createSuggester({ load, onResult, onClear, onStart });

        suggester.schedule('zou');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(onStart).toHaveBeenCalledWith('zou');
        suggester.schedule('z');
        release(['périmé']);
        await Promise.resolve();
        expect(onResult).not.toHaveBeenCalled();
        expect(onClear).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(load).toHaveBeenCalledTimes(1);

        suggester.cancel();
        expect(onResult).not.toHaveBeenCalled();
    });

    it('signale une erreur réseau sans la confondre avec une annulation', async () => {
        vi.useFakeTimers();
        const onError = vi.fn();
        const failing = createSuggester({ load: async () => { throw new Error('réseau'); }, onResult: vi.fn(), onError });
        failing.schedule('zou');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(onError).toHaveBeenCalledTimes(1);

        const aborted = vi.fn();
        const cancelled = createSuggester({ load: async () => { throw new DOMException('stop', 'AbortError'); }, onResult: vi.fn(), onError: aborted });
        cancelled.schedule('zou');
        await vi.advanceTimersByTimeAsync(DEBOUNCE_MS);
        expect(aborted).not.toHaveBeenCalled();
    });
});
