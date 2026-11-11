// Appels de l'API des événements depuis les écrans Vue (lot evenements, P8-04 ; docs/API.md §15, routes api.events.*) :
// un seul endroit pour les adresses (/api/v1, hors Ziggy). Chaque fonction reçoit le client useApi() (XSRF, 419, 429,
// toasts) et rend l'objet utile, sans enveloppe.
import type { Api } from '@/app/useApi';
import type { EventDraftBody, EventItem, EventPage } from './types';

export const API_BASE = '/api/v1';

const eventUrl = (id: string, suffix = '') => `${API_BASE}/events/${encodeURIComponent(id)}${suffix}`;

interface Envelope<T> {
    data: T;
}

export function eventsApi(api: Api) {
    return {
        async list(query: { filter?: string; category?: string | null; q?: string | null; page?: number }): Promise<EventPage> {
            const { data } = await api.get<EventPage>(`${API_BASE}/events`, {
                query: { filter: query.filter, category: query.category ?? undefined, q: query.q ?? undefined, page: query.page },
            });
            return data;
        },
        async create(body: EventDraftBody): Promise<EventItem> {
            return (await api.post<Envelope<EventItem>>(`${API_BASE}/events`, body, { silent: true })).data.data;
        },
        async rsvp(id: string, going: boolean, reminder = true): Promise<EventItem> {
            return (await api.post<Envelope<EventItem>>(eventUrl(id, '/rsvp'), { going, reminder })).data.data;
        },
    };
}

export type EventsApi = ReturnType<typeof eventsApi>;
