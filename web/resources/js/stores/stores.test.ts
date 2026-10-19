// @vitest-environment jsdom
// Stores Pinia session, notifications et ui (P6-05) : hydratation depuis les props partagées, permissions, titre
// d'onglet neutre, compteurs, marquage « lu » optimiste annulé sur refus de l'API, toasts.
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { freshPinia } from '@/test/helpers';
import type { Api } from '@/app/useApi';
import type { SharedProps } from '@/app/types';
import { useNotificationsStore } from './notifications';
import { useSessionStore } from './session';
import { useUiStore } from './ui';

const props: SharedProps = {
    auth: { user: { id: 3, pseudo: 'Awa_S', avatar_url: null, role: 'membre', roles: ['membre'], email_verified: true, pending_validation: false, profile: { completion: 40, member_type: 'membre', discreet_mode: false }, settings: { theme: 'light', language: 'fr', neutral_tab_title: false, mask_sensitive_notifications: false, sos_share_gps: true } } },
    can: { 'posts.create': true, 'peer_space.access': false },
    counters: { notifications: 4, messages: 2 },
    flash: { toast: null, status: null },
    lang: { locale: 'fr', fallback: 'fr' },
    ui: { neutral_title: false, neutral_label: 'Notes', bell_poll_seconds: 30, outbox: { account: 'abc', ttl_days: 7 }, sos: { hold_ms: 1500, countdown_seconds: 4 } },
};

const fakeApi = (impl: Partial<Record<'get' | 'post', (...args: unknown[]) => Promise<unknown>>>) => ({
    get: vi.fn(impl.get ?? (async () => ({ status: 200, data: {}, headers: new Headers() }))),
    post: vi.fn(impl.post ?? (async () => ({ status: 204, data: null, headers: new Headers() }))),
}) as unknown as Api & { get: ReturnType<typeof vi.fn>; post: ReturnType<typeof vi.fn> };

beforeEach(() => freshPinia());

describe('store session', () => {
    it('hydrate compte, permissions, réglages de la coquille', () => {
        const session = useSessionStore();
        session.hydrate(props);
        expect(session.authenticated).toBe(true);
        expect(session.pseudo).toBe('Awa_S');
        expect(session.can('posts.create')).toBe(true);
        expect(session.can('peer_space.access')).toBe(false);
        expect(session.can('inconnue')).toBe(false);
        expect(session.outboxAccount).toBe('abc');
        expect(session.sosHoldMs).toBe(1500);
        expect(session.sosCountdown).toBe(4);
    });

    it('titre d’onglet : « Titre – Xomiton », ou « Notes » en mode neutre', () => {
        const session = useSessionStore();
        session.hydrate(props);
        expect(session.documentTitle('Fil d’actualité')).toBe('Fil d’actualité – Xomiton');
        expect(session.documentTitle('')).toBe('Xomiton');
        session.hydrate({ ...props, ui: { ...props.ui, neutral_title: true } });
        expect(session.documentTitle('Messages')).toBe('Notes');
    });

    it('déconnexion côté serveur : user null', () => {
        const session = useSessionStore();
        session.hydrate(props);
        session.hydrate({ auth: { user: null }, can: {} });
        expect(session.authenticated).toBe(false);
        expect(session.can('posts.create')).toBe(false);
    });
});

describe('store notifications', () => {
    it('compteurs des props partagées et de unread-count (P6-07)', async () => {
        const store = useNotificationsStore();
        store.hydrate(props);
        expect(store.unread).toBe(4);
        expect(store.messages).toBe(2);
        const api = fakeApi({ get: async () => ({ status: 200, data: { data: { all: 12, unread: 9, read: 3, categories: {} } }, headers: new Headers() }) });
        await store.refreshCount(api);
        expect(store.unread).toBe(9);
        expect(api.get).toHaveBeenCalledWith('/api/v1/notifications/unread-count', { silent: true });
    });

    it('liste filtrée (meta.unread fait foi), forme XmBell, marquage optimiste annulé sur refus', async () => {
        const store = useNotificationsStore();
        store.hydrate(props);
        const api = fakeApi({
            get: async () => ({ status: 200, data: { data: [{ id: 'a', title: 'Nouvelle réponse', url: '/fil', icon: 'chat', read: false, created_at: '2026-10-18T10:00:00Z' }, { id: 'b', title: null, body: 'Corps', read: true }], meta: { unread: 5 } }, headers: new Headers() }),
            post: async () => { throw new Error('refus'); },
        });
        await store.load(api, 'unread');
        expect(api.get).toHaveBeenCalledWith('/api/v1/notifications', { query: { filter: 'unread' } });
        expect(store.unread).toBe(5);
        expect(store.bellItems).toEqual([
            { id: 'a', text: 'Nouvelle réponse', href: '/fil', icon: 'chat', time: '2026-10-18T10:00:00Z', read: false },
            { id: 'b', text: 'Corps', href: null, icon: 'bell', time: null, read: true },
        ]);
        await expect(store.markRead(api, 'a')).rejects.toThrow('refus');
        expect(store.items[0].read).toBe(false);
        expect(store.unread).toBe(5);
        await expect(store.markAllRead(api)).rejects.toThrow('refus');
        expect(store.items.map((n) => n.read)).toEqual([false, true]);
        expect(store.unread).toBe(5);
    });

    it('marquage accepté : POST {id}/read et read-all, compteur du serveur', async () => {
        const store = useNotificationsStore();
        store.hydrate(props);
        store.items = [{ id: 'a1', title: 'A', read: false }, { id: 'b', title: 'B', read: false }];
        const api = fakeApi({ post: async (url: unknown) => ({ status: 200, data: String(url).endsWith('read-all') ? { data: { marked: 1, unread: 0 } } : { meta: { unread: 7 } }, headers: new Headers() }) });
        await store.markRead(api, 'a1');
        expect(api.post).toHaveBeenCalledWith('/api/v1/notifications/a1/read');
        expect(store.unread).toBe(7);
        await store.markAllRead(api);
        expect(api.post).toHaveBeenLastCalledWith('/api/v1/notifications/read-all');
        expect(store.unread).toBe(0);
        expect(store.items.every((n) => n.read)).toBe(true);
    });

    it('cloche → page /notifications : marquages de la cloche reportés sur la liste et les badges, polling des filtres', async () => {
        const store = useNotificationsStore();
        const page = () => [{ id: 'a', title: 'A', tab: 'invitations' as const, read: false }, { id: 'b', title: 'B', tab: 'system' as const, read: false }];
        store.hydratePage('all', { data: page(), meta: { current_page: 1, last_page: 1 } }, { all: { all: 2, unread: 2 }, invitations: { all: 1, unread: 1 }, system: { all: 1, unread: 1 } });
        store.items = page();
        const served = { all: { all: 2, unread: 1 }, invitations: { all: 1, unread: 0 }, system: { all: 1, unread: 1 } };
        const api = fakeApi({
            post: async (url: unknown) => ({ status: 200, data: String(url).endsWith('read-all') ? { data: { marked: 1, unread: 0 } } : { meta: { unread: 1, tabs: served } }, headers: new Headers() }),
            get: async () => ({ status: 200, data: { data: { unread: 0, tabs: { all: { all: 2, unread: 0 }, invitations: { all: 1, unread: 0 }, system: { all: 1, unread: 0 } } } }, headers: new Headers() }),
        });

        await store.markRead(api, 'a');
        expect(store.pageItems.map((n) => n.read)).toEqual([true, false]);
        expect(store.tabCounts.invitations.unread).toBe(0);
        expect(store.tabCounts.all.unread).toBe(1);

        await store.markAllRead(api);
        expect(api.post).toHaveBeenLastCalledWith('/api/v1/notifications/read-all');
        expect(store.pageItems.every((n) => n.read)).toBe(true);
        expect(store.tabCounts.system.unread).toBe(0);
        expect(store.tabCounts.all.unread).toBe(store.unread);

        await store.refreshCount(api);
        expect(store.tabCounts.all).toEqual({ all: 2, unread: 0 });
    });

    it('« Tout marquer comme lu » de la page refusé : page, cloche et compteurs restaurés', async () => {
        const store = useNotificationsStore();
        store.hydratePage('exchanges', { data: [{ id: 'c', title: 'C', tab: 'exchanges', read: false }], meta: { current_page: 1, last_page: 1 } }, { all: { all: 2, unread: 2 }, exchanges: { all: 1, unread: 1 } });
        store.items = [{ id: 'c', title: 'C', tab: 'exchanges', read: false }, { id: 'd', title: 'D', tab: 'system', read: false }];
        const api = fakeApi({ post: async () => { throw new Error('refus'); } });

        await expect(store.markPageAllRead(api)).rejects.toThrow('refus');
        expect(api.post).toHaveBeenCalledWith('/api/v1/notifications/read-all', { tab: 'exchanges' });
        expect(store.pageItems[0].read).toBe(false);
        expect(store.items.map((n) => n.read)).toEqual([false, false]);
        expect(store.tabCounts.exchanges.unread).toBe(1);
        expect(store.unread).toBe(2);
    });
});

describe('store ui', () => {
    it('réseau et toasts (urgent pour danger, délai 4,5 s)', () => {
        const ui = useUiStore();
        ui.setOnline(false);
        expect(ui.online).toBe(false);
        const id = ui.pushToast('Erreur', 'danger');
        expect(ui.toasts[0]).toMatchObject({ id, urgent: true, delay: 4500 });
        ui.pushToast('Info', 'info', { urgent: true, delay: 6000 });
        expect(ui.toasts[1]).toMatchObject({ urgent: true, delay: 6000 });
        ui.dismissToast(id);
        expect(ui.toasts.map((t) => t.message)).toEqual(['Info']);
    });
});
