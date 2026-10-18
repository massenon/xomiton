// @vitest-environment jsdom
// Store outbox (P6-05) : adossé à shared/outbox.js (P5-10), mêmes options que outbox-boot.js (compte de la page,
// durée de vie), chargé à la demande, avis du code de suivi montés, compteur suivant les évènements de outbox.js.
import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { freshPinia } from '@/test/helpers';
import { useOutboxStore } from './outbox';

const fakeModules = () => {
    const box = { add: vi.fn(async (e: unknown) => e), flush: vi.fn(async () => ({ sent: [], failed: [] })), count: vi.fn(async () => 3), start: vi.fn() };
    const core = { getOutbox: vi.fn((_opts?: unknown) => box) };
    const notices = { mountOutboxNotices: vi.fn() };
    const importer = vi.fn(async () => [core, notices] as never);
    return { box, core, notices, importer };
};

const memoryStorage = (pending = 0) => {
    const map = new Map<string, string>(pending ? [['xm.outbox.pending', String(pending)]] : []);
    return { getItem: (k: string) => map.get(k) ?? null, setItem: (k: string, v: string) => map.set(k, v), removeItem: (k: string) => map.delete(k) } as unknown as Storage;
};

beforeEach(() => freshPinia());

describe('store outbox', () => {
    it('ne charge pas outbox.js sans envoi en attente ; le charge au premier add() avec le compte de la page', async () => {
        const m = fakeModules();
        const store = useOutboxStore();
        await store.start({ account: 'acct', ttlDays: 7, texts: { queued: 'En file' }, importer: m.importer, storage: memoryStorage(), target: new EventTarget() as Window });
        expect(m.importer).not.toHaveBeenCalled();
        await store.add({ type: 'signalement', url: '/api/v1/signalements', body: '{}' });
        expect(m.importer).toHaveBeenCalledOnce();
        expect(m.core.getOutbox).toHaveBeenCalledWith(expect.objectContaining({ ttlDays: 7 }));
        const opts = (m.core.getOutbox.mock.calls as unknown as Array<[{ account: () => string }]>)[0][0];
        expect(opts.account()).toBe('acct');
        expect(m.notices.mountOutboxNotices).toHaveBeenCalledWith({ queued: 'En file' });
        expect(m.box.start).toHaveBeenCalledOnce();
        expect(m.box.add).toHaveBeenCalledWith(expect.objectContaining({ type: 'signalement', account: 'acct' }));
        expect(store.ready).toBe(true);
    });

    it('charge tout de suite quand un envoi attend (xm.outbox.pending) ; compte inconnu : aucun tri', async () => {
        const m = fakeModules();
        const store = useOutboxStore();
        await store.start({ account: null, importer: m.importer, storage: memoryStorage(2), target: new EventTarget() as Window });
        expect(store.pending).toBe(2);
        expect(m.importer).toHaveBeenCalledOnce();
        expect(m.core.getOutbox).toHaveBeenCalledWith({ ttlDays: undefined });
        await store.add({ type: 'sos' });
        expect(m.box.add).toHaveBeenCalledWith({ type: 'sos' });
        expect(await store.refresh()).toBe(3);
    });

    it('suit les évènements xm:outbox:queued et xm:outbox:flushed', async () => {
        const m = fakeModules();
        const target = new EventTarget();
        const store = useOutboxStore();
        await store.start({ account: 'acct', importer: m.importer, storage: memoryStorage(), target: target as Window });
        target.dispatchEvent(new CustomEvent('xm:outbox:queued', { detail: { id: 'a', type: 'sos', pending: 1 } }));
        expect(store.pending).toBe(1);
        const detail = { sent: [{ id: 'a', type: 'sos', status: 201 }], failed: [], pending: 0 };
        target.dispatchEvent(new CustomEvent('xm:outbox:flushed', { detail }));
        expect(store.pending).toBe(0);
        expect(store.lastFlush).toEqual(detail);
        await store.flush(true);
        expect(m.box.flush).toHaveBeenCalledWith({ force: true });
        store.stop();
        target.dispatchEvent(new CustomEvent('xm:outbox:queued', { detail: { pending: 5 } }));
        expect(store.pending).toBe(0);
    });

    it('le vrai module shared/outbox.js est celui chargé par défaut', async () => {
        const core = await import('../shared/outbox.js');
        expect(typeof core.getOutbox).toBe('function');
        expect(core.PENDING_KEY).toBe('xm.outbox.pending');
    });

    it('entries() filtre par type et écarte les envois d’un autre compte ; remove() retire l’entrée et rend le reste', async () => {
        const m = fakeModules();
        const list = [
            { id: 'a', type: 'message', account: 'acct' },
            { id: 'b', type: 'message', account: 'autre' },
            { id: 'c', type: 'signalement', account: 'acct' },
            { id: 'd', type: 'message' }, // sans compte (page sans session) : gardée, comme heldFor de shared/outbox.js
        ];
        const box = Object.assign(m.box, { list: vi.fn(async () => list), remove: vi.fn(async () => undefined), count: vi.fn(async () => 2) });
        m.core.getOutbox.mockReturnValue(box);
        const store = useOutboxStore();
        await store.start({ account: 'acct', importer: m.importer, storage: memoryStorage(), target: new EventTarget() as Window });

        expect((await store.entries('message')).map((e) => e.id)).toEqual(['a', 'd']);
        expect((await store.entries()).map((e) => e.id)).toEqual(['a', 'c', 'd']);
        await expect(store.remove('a')).resolves.toBe(2);
        expect(box.remove).toHaveBeenCalledWith('a');
        expect(store.pending).toBe(2);
    });

    it('entries() sans compte connu (page sans session) : aucun tri par compte', async () => {
        const m = fakeModules();
        const box = Object.assign(m.box, { list: vi.fn(async () => [{ id: 'a', type: 'message', account: 'x' }, { id: 'b', type: 'message' }]) });
        m.core.getOutbox.mockReturnValue(box);
        const store = useOutboxStore();
        await store.start({ account: null, importer: m.importer, storage: memoryStorage(), target: new EventTarget() as Window });

        expect((await store.entries('message')).map((e) => e.id)).toEqual(['a', 'b']);
    });
});
