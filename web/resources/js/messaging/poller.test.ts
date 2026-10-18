// Sondage court (P8-01, D7) : intervalle (fixe ou relu à chaque passage), pause onglet caché ou hors réseau, recul après
// erreur, arrêt.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPoller } from './poller';

const fakeEnv = () => {
    const docListeners: Record<string, () => void> = {};
    const winListeners: Record<string, () => void> = {};
    const doc = {
        visibilityState: 'visible' as DocumentVisibilityState,
        addEventListener: (type: string, fn: () => void) => { docListeners[type] = fn; },
        removeEventListener: (type: string) => { delete docListeners[type]; },
    };
    const win = {
        navigator: { onLine: true },
        addEventListener: (type: string, fn: () => void) => { winListeners[type] = fn; },
        removeEventListener: (type: string) => { delete winListeners[type]; },
    };
    return { doc, win, docListeners, winListeners };
};

beforeEach(() => vi.useFakeTimers());
afterEach(() => vi.useRealTimers());

describe('createPoller', () => {
    it('interroge à chaque intervalle, jamais deux passages à la fois, et s’arrête', async () => {
        const env = fakeEnv();
        const run = vi.fn(async () => undefined);
        const poller = createPoller({ interval: 4000, run, doc: env.doc as never, win: env.win as never });
        poller.start();
        await vi.advanceTimersByTimeAsync(4000);
        await vi.advanceTimersByTimeAsync(4000);
        expect(run).toHaveBeenCalledTimes(2);
        await Promise.all([poller.now(), poller.now()]);
        expect(run).toHaveBeenCalledTimes(3);
        poller.stop();
        await vi.advanceTimersByTimeAsync(20000);
        expect(run).toHaveBeenCalledTimes(3);
        expect(env.docListeners.visibilitychange).toBeUndefined();
    });

    it('ne fait rien onglet caché ou hors réseau, reprend au retour', async () => {
        const env = fakeEnv();
        const run = vi.fn(async () => undefined);
        const poller = createPoller({ interval: 4000, run, doc: env.doc as never, win: env.win as never });
        env.doc.visibilityState = 'hidden';
        poller.start();
        await vi.advanceTimersByTimeAsync(12000);
        expect(run).not.toHaveBeenCalled();
        env.doc.visibilityState = 'visible';
        env.docListeners.visibilitychange();
        await vi.advanceTimersByTimeAsync(0);
        expect(run).toHaveBeenCalledTimes(1);
        env.win.navigator.onLine = false;
        await vi.advanceTimersByTimeAsync(8000);
        expect(run).toHaveBeenCalledTimes(1);
        env.win.navigator.onLine = true;
        env.winListeners.online();
        await vi.advanceTimersByTimeAsync(0);
        expect(run).toHaveBeenCalledTimes(2);
        poller.stop();
    });

    it('espace les essais après une erreur puis revient à l’intervalle', async () => {
        const env = fakeEnv();
        let fail = true;
        const run = vi.fn(async () => { if (fail) throw new Error('réseau'); });
        const poller = createPoller({ interval: 1000, run, doc: env.doc as never, win: env.win as never });
        poller.start();
        await vi.advanceTimersByTimeAsync(1000);
        expect(run).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1000);
        expect(run).toHaveBeenCalledTimes(1); // recul : 2 s
        await vi.advanceTimersByTimeAsync(1000);
        expect(run).toHaveBeenCalledTimes(2);
        fail = false;
        await vi.advanceTimersByTimeAsync(4000);
        expect(run).toHaveBeenCalledTimes(3);
        await vi.advanceTimersByTimeAsync(1000);
        expect(run).toHaveBeenCalledTimes(4);
        poller.stop();
    });

    it('relit un intervalle variable à chaque passage (repli de la liste à 30 s, retour à 15 s)', async () => {
        const env = fakeEnv();
        let idle = false;
        const run = vi.fn(async () => undefined);
        const poller = createPoller({ interval: () => (idle ? 30000 : 15000), run, doc: env.doc as never, win: env.win as never });
        poller.start();
        await vi.advanceTimersByTimeAsync(15000);
        expect(run).toHaveBeenCalledTimes(1);
        idle = true;
        await vi.advanceTimersByTimeAsync(15000); // passage déjà programmé à 15 s
        expect(run).toHaveBeenCalledTimes(2);
        await vi.advanceTimersByTimeAsync(15000);
        expect(run).toHaveBeenCalledTimes(2);
        await vi.advanceTimersByTimeAsync(15000);
        expect(run).toHaveBeenCalledTimes(3);
        poller.stop();
    });
});

