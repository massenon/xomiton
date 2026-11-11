// Câblage de la coquille Inertia (P6-03) : gabarit membre par défaut des pages et pont `window.XM.outbox` (contrat de
// shared/outbox-boot.js, remise d'un SOS hors ligne par sos.js) vers le store `outbox`.
import { describe, expect, it, vi } from 'vitest';
import { bindOutbox, withDefaultLayout } from './shellBoot';

const Layout = { name: 'MemberLayout' };

describe('withDefaultLayout', () => {
    it('donne le gabarit membre à une page qui n’en déclare pas', () => {
        expect(withDefaultLayout({ name: 'Page' }, Layout)).toEqual({ name: 'Page', layout: Layout });
    });

    it('laisse sans gabarit une page `layout: null` et garde un autre gabarit', () => {
        const Other = { name: 'Autre' };
        expect(withDefaultLayout({ layout: null }, Layout).layout).toBeNull();
        expect(withDefaultLayout({ layout: Other }, Layout).layout).toBe(Other);
    });
});

describe('bindOutbox', () => {
    const store = () => ({
        add: vi.fn(async (entry: Record<string, unknown>) => ({ id: 1, ...entry })),
        flush: vi.fn(async (force?: boolean) => ({ sent: 1, force })),
        refresh: vi.fn(async () => 2),
    });

    it('relaie add, flush (force) et count au store, sur window.XM créé au besoin', async () => {
        const target: { XM?: Record<string, unknown> } = {};
        const outbox = store();
        bindOutbox(target, outbox);
        const bridge = target.XM?.outbox as ReturnType<typeof bindOutbox>;

        const sos = { type: 'sos', url: '/api/v1/sos', body: { zone_id: 4 } };
        await expect(bridge.add(sos)).resolves.toEqual({ id: 1, ...sos });
        expect(outbox.add).toHaveBeenCalledWith(sos);

        await expect(bridge.flush({ force: true })).resolves.toEqual({ sent: 1, force: true });
        await bridge.flush();
        expect(outbox.flush.mock.calls).toEqual([[true], [false]]);

        await expect(bridge.count()).resolves.toBe(2);
        expect(outbox.refresh).toHaveBeenCalledTimes(1);
    });

    it('garde les autres entrées de window.XM et ne lève jamais : null en cas d’échec', async () => {
        const toast = vi.fn();
        const target: { XM?: Record<string, unknown> } = { XM: { toast } };
        const outbox = store();
        outbox.add.mockRejectedValueOnce(new Error('IndexedDB indisponible'));
        outbox.flush.mockRejectedValueOnce(new Error('hors ligne'));
        outbox.refresh.mockRejectedValueOnce(new Error('hors ligne'));
        const bridge = bindOutbox(target, outbox);

        expect(target.XM?.toast).toBe(toast);
        await expect(bridge.add({ type: 'sos' })).resolves.toBeNull();
        await expect(bridge.flush()).resolves.toBeNull();
        await expect(bridge.count()).resolves.toBeNull();
    });
});
