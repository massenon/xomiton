// @vitest-environment jsdom
// Page /notifications (lot parametres-notifications, P8-10 ; prototype notifications.php) : filtres Toutes ·
// Invitations · Événements · Échanges · Système avec badge de non lues, « Marquer comme lu » (optimiste, compteurs du
// serveur ensuite, annulé si l'API refuse), « Tout marquer comme lu » limité au filtre, « Afficher plus », ouverture
// d'une notification non lue (lue d'abord).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { useNotificationsStore, type NotificationItem } from '@/stores/notifications';
import Notifications from './Notifications.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const item = (id: string, overrides: Partial<NotificationItem> = {}): NotificationItem => ({
    id, type: 'invitation_received', category: 'communaute', tab: 'invitations', title: `Notification ${id}`, body: null,
    url: null, icon: 'person-plus', sensitive: false, read: false, read_at: null, created_at: new Date().toISOString(), ...overrides,
});

const counts = (overrides: Record<string, { all: number; unread: number }> = {}) => ({
    all: { all: 3, unread: 2 }, invitations: { all: 1, unread: 1 }, events: { all: 1, unread: 0 }, exchanges: { all: 1, unread: 1 }, system: { all: 0, unread: 0 }, ...overrides,
});

const base = (overrides: Record<string, unknown> = {}) => ({
    title: 'Notifications',
    tab: 'all',
    counts: counts(),
    notifications: {
        data: [item('a'), item('b', { tab: 'events', icon: 'calendar-event', read: true }), item('c', { tab: 'exchanges', icon: 'chat-dots' })],
        meta: { current_page: 1, last_page: 1, total: 3 },
    },
    per_page: 20,
    ...overrides,
});

describe('Page /notifications', () => {
    it('affiche les filtres avec leur badge de non lues et l’état lu de chaque notification', async () => {
        fakeFetch(() => json({ data: {} }));
        await mountInertia(Notifications, { url: '/notifications', pageProps: base() });

        const nav = screen.getByRole('navigation', { name: 'Filtrer les notifications' });
        const links = [...nav.querySelectorAll('a')];
        expect(links.map((a) => a.getAttribute('href'))).toEqual(['/notifications', '/notifications?filtre=invitations', '/notifications?filtre=evenements', '/notifications?filtre=echanges', '/notifications?filtre=systeme']);
        expect(links[0].getAttribute('aria-current')).toBe('page');
        expect(links[1].textContent).toContain('Invitations');
        expect(links[1].textContent).toContain('1 non lue');
        expect(links[4].querySelector('.badge')).toBeNull();
        expect(document.querySelector('[data-xm-unread]')?.textContent).toContain('2');
        expect([...document.querySelectorAll('[data-xm-notifications] li[data-read]')].map((li) => li.getAttribute('data-read'))).toEqual(['0', '1', '0']);
        expect(screen.getAllByRole('button', { name: /^Marquer comme lue/ })).toHaveLength(2);
    });

    it('marque une notification comme lue et reprend les compteurs du serveur', async () => {
        const { calls } = fakeFetch(() => json({ data: { ...item('a'), read: true }, meta: { unread: 1, tabs: counts({ all: { all: 3, unread: 1 }, invitations: { all: 1, unread: 0 } }) } }));
        await mountInertia(Notifications, { url: '/notifications', pageProps: base() });

        await fireEvent.click(screen.getByRole('button', { name: /^Marquer comme lue\s: Notification a$/u }));
        await waitFor(() => expect(document.querySelector('[data-xm-unread]')?.textContent).toContain('1'));
        expect(calls[0]).toMatchObject({ url: '/api/v1/notifications/a/read', method: 'POST' });
        expect(useNotificationsStore().tabCounts.invitations.unread).toBe(0);
        expect(screen.getAllByRole('button', { name: /^Marquer comme lue/ })).toHaveLength(1);
    });

    it('annule le marquage si l’API refuse', async () => {
        fakeFetch(() => json({ message: 'Erreur.' }, 500));
        await mountInertia(Notifications, { url: '/notifications', pageProps: base() });

        await fireEvent.click(screen.getByRole('button', { name: /^Marquer comme lue\s: Notification a$/u }));
        await waitFor(() => expect(useNotificationsStore().pageItems[0].read).toBe(false));
        expect(useNotificationsStore().tabCounts.all.unread).toBe(2);
    });

    it('marque tout comme lu pour le seul filtre courant, puis relit les compteurs', async () => {
        const { calls } = fakeFetch((call) => (call.url.endsWith('/read-all')
            ? json({ data: { marked: 1, unread: 1 } })
            : json({ data: { unread: 1, tabs: counts({ all: { all: 3, unread: 1 }, exchanges: { all: 1, unread: 0 } }) } })));
        await mountInertia(Notifications, {
            url: '/notifications?filtre=echanges',
            pageProps: base({ tab: 'exchanges', notifications: { data: [item('c', { tab: 'exchanges' })], meta: { current_page: 1, last_page: 1, total: 1 } } }),
        });

        await fireEvent.click(screen.getByRole('button', { name: /Tout marquer comme lu/ }));
        await waitFor(() => expect(calls.map((c) => [c.method, c.url])).toEqual([['POST', '/api/v1/notifications/read-all'], ['GET', '/api/v1/notifications/unread-count']]));
        expect(calls[0].body).toEqual({ tab: 'exchanges' });
        expect(useNotificationsStore().tabCounts.exchanges.unread).toBe(0);
        expect((screen.getByRole('button', { name: /Tout marquer comme lu/ }) as HTMLButtonElement).disabled).toBe(true);
    });

    it('charge la page suivante du filtre courant sans doublon', async () => {
        const { calls } = fakeFetch(() => json({ data: [item('a'), item('d', { title: 'Plus ancienne' })], meta: { current_page: 2, last_page: 2 } }));
        await mountInertia(Notifications, { url: '/notifications', pageProps: base({ notifications: { data: [item('a')], meta: { current_page: 1, last_page: 2, total: 2 } } }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Afficher plus de notifications' }));
        await waitFor(() => expect(screen.getByText('Plus ancienne')).toBeTruthy());
        expect(calls[0].url).toBe('/api/v1/notifications?tab=all&page=2&per_page=20');
        expect(document.querySelectorAll('[data-xm-notifications] li[data-read]')).toHaveLength(2);
        expect(screen.queryByRole('button', { name: 'Afficher plus de notifications' })).toBeNull();
    });

    it('dit quand un filtre est vide', async () => {
        fakeFetch(() => json({ data: {} }));
        await mountInertia(Notifications, { url: '/notifications?filtre=systeme', pageProps: base({ tab: 'system', notifications: { data: [], meta: { current_page: 1, last_page: 1, total: 0 } } }) });

        expect(screen.getByText('Aucune notification dans cette catégorie.')).toBeTruthy();
    });
});
