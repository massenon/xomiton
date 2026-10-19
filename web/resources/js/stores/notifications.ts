// Store Pinia `notifications` (P6-05) : compteurs de l'en-tête (props partagées `counters`, rafraîchis à chaque visite
// et par la cloche, P6-03) et liste de la cloche, sur l'API de P6-07 (docs/API.md §7) : `GET notifications?filter=`,
// `GET notifications/unread-count`, `POST notifications/{id}/read`, `POST notifications/read-all`. Marquage « lu »
// optimiste, annulé si l'API refuse ; le compteur renvoyé par le serveur (`meta.unread`, `data.unread`) fait foi.
// Page /notifications (P8-10, lot parametres-notifications) : liste du filtre courant (`page*`, `?tab=`), compteurs
// par filtre (`tabCounts`, `unread-count` → `data.tabs`), marquage lu par notification et par filtre. L'état lu est
// partagé dans les deux sens : un marquage fait sur la page vaut pour la cloche, et un marquage de la cloche (une
// notification ou « Tout marquer comme lu ») vaut pour la liste et les badges de la page (même id, mêmes compteurs).
import { defineStore } from 'pinia';
import type { Api } from '@/app/useApi';
import type { SharedProps } from '@/app/types';

/** NotificationResource (App\Http\Resources\NotificationResource) : titre et corps déjà neutralisés si sensibles. */
export interface NotificationItem {
    id: string;
    type?: string;
    category?: string | null;
    /** Filtre de la page /notifications (NotificationTab côté serveur). */
    tab?: NotificationTab | null;
    title: string | null;
    body?: string | null;
    url?: string | null;
    icon?: string | null;
    sensitive?: boolean;
    read: boolean;
    read_at?: string | null;
    created_at?: string | null;
}

export type NotificationFilter = 'unread' | 'read' | 'all';

export type NotificationTab = 'all' | 'invitations' | 'events' | 'exchanges' | 'system';
export type TabCounts = Record<NotificationTab, { all: number; unread: number }>;
export const NOTIFICATION_TABS: NotificationTab[] = ['all', 'invitations', 'events', 'exchanges', 'system'];

export interface NotificationPage {
    data: NotificationItem[];
    meta: { current_page: number; last_page: number; total?: number };
}

export const emptyTabCounts = (): TabCounts => Object.fromEntries(NOTIFICATION_TABS.map((tab) => [tab, { all: 0, unread: 0 }])) as TabCounts;

const tabCountsOf = (value: unknown): TabCounts | null => {
    if (!value || typeof value !== 'object') return null;
    const counts = emptyTabCounts();
    NOTIFICATION_TABS.forEach((tab) => {
        const row = (value as Record<string, { all?: unknown; unread?: unknown }>)[tab];
        if (row) counts[tab] = { all: Number(row.all) || 0, unread: Number(row.unread) || 0 };
    });
    return counts;
};

export const NOTIFICATIONS_ENDPOINT = '/api/v1/notifications';

const cloneTabs = (counts: TabCounts): TabCounts => JSON.parse(JSON.stringify(counts)) as TabCounts;

const unreadOf = (value: unknown): number | null => {
    const n = Number(value);
    return Number.isFinite(n) && value !== null && value !== undefined ? n : null;
};

export const useNotificationsStore = defineStore('notifications', {
    state: () => ({
        unread: 0,
        messages: 0,
        items: [] as NotificationItem[],
        loading: false,
        loaded: false,
        pageTab: 'all' as NotificationTab,
        pageItems: [] as NotificationItem[],
        pageCurrent: 1,
        pageLast: 1,
        pageLoading: false,
        tabCounts: emptyTabCounts(),
    }),
    getters: {
        /** Forme attendue par <XmBell :items>. */
        bellItems: (state) => state.items.map((n) => ({
            id: n.id,
            text: n.title || n.body || '',
            href: n.url ?? null,
            icon: n.icon ?? 'bell',
            time: n.created_at ?? null,
            read: n.read,
        })),
    },
    actions: {
        hydrate(props: Partial<SharedProps>) {
            if (props.counters) {
                this.unread = Number(props.counters.notifications) || 0;
                this.messages = Number(props.counters.messages) || 0;
            }
        },
        /** Compteurs de la cloche et, quand le serveur les donne (`data.tabs`), des filtres de la page /notifications. */
        async refreshCount(api: Api) {
            const { data } = await api.get<{ data: { unread: number; tabs?: unknown } }>(`${NOTIFICATIONS_ENDPOINT}/unread-count`, { silent: true });
            this.unread = unreadOf(data?.data?.unread) ?? this.unread;
            this.tabCounts = tabCountsOf(data?.data?.tabs) ?? this.tabCounts;
        },
        async load(api: Api, filter: NotificationFilter = 'all') {
            this.loading = true;
            try {
                const { data } = await api.get<{ data: NotificationItem[]; meta?: { unread?: number } }>(NOTIFICATIONS_ENDPOINT, { query: { filter } });
                this.items = Array.isArray(data?.data) ? data.data : [];
                this.unread = unreadOf(data?.meta?.unread) ?? this.unread;
                this.loaded = true;
            } finally {
                this.loading = false;
            }
        },
        /** « Marquer comme lu » depuis la cloche : même marquage que la page (listes et compteurs partagés). */
        async markRead(api: Api, id: string) {
            await this.markOne(api, id);
        },
        /** Page /notifications : première page et compteurs servis par NotificationsPageController. */
        hydratePage(tab: NotificationTab, list: NotificationPage, counts: Partial<TabCounts> | null = null) {
            this.pageTab = tab;
            this.pageItems = Array.isArray(list?.data) ? list.data.map((n) => ({ ...n })) : [];
            this.pageCurrent = Number(list?.meta?.current_page) || 1;
            this.pageLast = Number(list?.meta?.last_page) || 1;
            this.tabCounts = tabCountsOf(counts) ?? emptyTabCounts();
            this.unread = this.tabCounts.all.unread;
        },
        async loadMorePage(api: Api, perPage = 20) {
            if (this.pageLoading || this.pageCurrent >= this.pageLast) return;
            this.pageLoading = true;
            try {
                const query = { tab: this.pageTab, page: this.pageCurrent + 1, per_page: perPage };
                const { data } = await api.get<{ data: NotificationItem[]; meta?: { current_page?: number; last_page?: number } }>(NOTIFICATIONS_ENDPOINT, { query });
                const known = new Set(this.pageItems.map((n) => n.id));
                this.pageItems.push(...(Array.isArray(data?.data) ? data.data.filter((n) => !known.has(n.id)) : []));
                this.pageCurrent = Number(data?.meta?.current_page) || this.pageCurrent + 1;
                this.pageLast = Number(data?.meta?.last_page) || this.pageLast;
            } finally {
                this.pageLoading = false;
            }
        },
        /** « Marquer comme lu » d'une notification de la page (optimiste, compteurs du serveur ensuite). */
        async markPageRead(api: Api, id: string) {
            await this.markOne(api, id);
        },
        /**
         * Marquage d'une notification, de la cloche ou de la page : les deux listes (même id) et les compteurs par
         * filtre suivent, `meta.unread` et `meta.tabs` du serveur font foi ; tout est annulé si l'API refuse.
         */
        async markOne(api: Api, id: string) {
            const entries = [...this.items, ...this.pageItems].filter((n) => n.id === id);
            if (entries.length === 0 || entries.every((n) => n.read)) return;
            const tab = entries.find((n) => n.tab)?.tab ?? 'system';
            const previous = { read: entries.map((n) => n.read), tabs: cloneTabs(this.tabCounts), unread: this.unread };
            entries.forEach((n) => { n.read = true; });
            this.unread = Math.max(0, this.unread - 1);
            this.tabCounts.all.unread = Math.max(0, this.tabCounts.all.unread - 1);
            if (tab !== 'all' && this.tabCounts[tab]) this.tabCounts[tab].unread = Math.max(0, this.tabCounts[tab].unread - 1);
            try {
                const { data } = await api.post<{ meta?: { unread?: number; tabs?: unknown } }>(`${NOTIFICATIONS_ENDPOINT}/${encodeURIComponent(id)}/read`);
                this.unread = unreadOf(data?.meta?.unread) ?? this.unread;
                this.tabCounts = tabCountsOf(data?.meta?.tabs) ?? this.tabCounts;
            } catch (error) {
                entries.forEach((n, i) => { n.read = previous.read[i] ?? n.read; });
                this.tabCounts = previous.tabs;
                this.unread = previous.unread;
                throw error;
            }
        },
        /** « Tout marquer comme lu » de la page : limité au filtre courant (sauf « Toutes »). */
        async markPageAllRead(api: Api) {
            await this.markAll(api, this.pageTab);
        },
        /** « Tout marquer comme lu » de la cloche : toutes les notifications, page /notifications comprise. */
        async markAllRead(api: Api) {
            await this.markAll(api, 'all');
        },
        /**
         * Marquage de masse (`read-all`, `tab` sauf « Toutes ») : cloche, page et compteurs passent à lu tout de suite,
         * puis les compteurs du serveur (unread-count) font foi ; annulé partout si l'API refuse.
         */
        async markAll(api: Api, tab: NotificationTab) {
            const ids = new Set(this.pageItems.map((n) => n.id));
            const pageInScope = tab === 'all' || this.pageTab === tab;
            const inScope = (n: NotificationItem) => tab === 'all' || n.tab === tab || ids.has(n.id);
            const previous = {
                page: this.pageItems.map((n) => n.read),
                bell: this.items.map((n) => n.read),
                tabs: cloneTabs(this.tabCounts),
                unread: this.unread,
            };
            this.pageItems.forEach((n) => { if (pageInScope || n.tab === tab) n.read = true; });
            this.items.forEach((n) => { if (inScope(n)) n.read = true; });
            const marked = tab === 'all' ? this.unread : this.tabCounts[tab].unread;
            if (tab === 'all') NOTIFICATION_TABS.forEach((key) => { this.tabCounts[key].unread = 0; });
            else {
                this.tabCounts[tab].unread = 0;
                this.tabCounts.all.unread = Math.max(0, this.tabCounts.all.unread - marked);
            }
            this.unread = Math.max(0, this.unread - marked);
            try {
                const url = `${NOTIFICATIONS_ENDPOINT}/read-all`;
                const { data } = tab === 'all' ? await api.post<{ data?: { unread?: number } }>(url) : await api.post<{ data?: { unread?: number } }>(url, { tab });
                this.unread = unreadOf(data?.data?.unread) ?? this.unread;
                await this.refreshCount(api).catch(() => undefined);
            } catch (error) {
                this.pageItems.forEach((n, i) => { n.read = previous.page[i] ?? n.read; });
                this.items.forEach((n, i) => { n.read = previous.bell[i] ?? n.read; });
                this.tabCounts = previous.tabs;
                this.unread = previous.unread;
                throw error;
            }
        },
    },
});
