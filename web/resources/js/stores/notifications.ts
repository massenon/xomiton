// Store Pinia `notifications` (P6-05) : compteurs de l'en-tête (props partagées `counters`, rafraîchis à chaque visite
// et par la cloche, P6-03) et liste de la cloche, sur l'API de P6-07 (docs/API.md §7) : `GET notifications?filter=`,
// `GET notifications/unread-count`, `POST notifications/{id}/read`, `POST notifications/read-all`. Marquage « lu »
// optimiste, annulé si l'API refuse ; le compteur renvoyé par le serveur (`meta.unread`, `data.unread`) fait foi.
import { defineStore } from 'pinia';
import type { Api } from '@/app/useApi';
import type { SharedProps } from '@/app/types';

/** NotificationResource (App\Http\Resources\NotificationResource) : titre et corps déjà neutralisés si sensibles. */
export interface NotificationItem {
    id: string;
    type?: string;
    category?: string | null;
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

export const NOTIFICATIONS_ENDPOINT = '/api/v1/notifications';

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
        async refreshCount(api: Api) {
            const { data } = await api.get<{ data: { unread: number } }>(`${NOTIFICATIONS_ENDPOINT}/unread-count`, { silent: true });
            this.unread = unreadOf(data?.data?.unread) ?? this.unread;
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
        async markRead(api: Api, id: string) {
            const item = this.items.find((n) => n.id === id);
            if (!item || item.read) return;
            item.read = true;
            this.unread = Math.max(0, this.unread - 1);
            try {
                const { data } = await api.post<{ meta?: { unread?: number } }>(`${NOTIFICATIONS_ENDPOINT}/${encodeURIComponent(id)}/read`);
                this.unread = unreadOf(data?.meta?.unread) ?? this.unread;
            } catch (error) {
                item.read = false;
                this.unread += 1;
                throw error;
            }
        },
        async markAllRead(api: Api) {
            const previous = { unread: this.unread, read: this.items.map((n) => n.read) };
            this.items.forEach((n) => { n.read = true; });
            this.unread = 0;
            try {
                const { data } = await api.post<{ data?: { unread?: number } }>(`${NOTIFICATIONS_ENDPOINT}/read-all`);
                this.unread = unreadOf(data?.data?.unread) ?? 0;
            } catch (error) {
                this.items.forEach((n, i) => { n.read = previous.read[i] ?? n.read; });
                this.unread = previous.unread;
                throw error;
            }
        },
    },
});
