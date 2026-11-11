// Appels de l'API des forums depuis les écrans Vue (lot forums, P8-05 ; docs/API.md §16, routes api.forums.*) : un seul
// endroit pour les adresses (/api/v1, hors Ziggy). Chaque fonction reçoit le client useApi() (XSRF, 419, 429, toasts)
// et rend l'objet utile, sans enveloppe. Signaler : modale du fil (components/feed/ReportContentModal.vue, cible
// `{ topic }` ou `{ reply }`, feed/api.ts).
import type { Api } from '@/app/useApi';
import type { ForumReply, ForumTopic, PagedList } from './types';

export const API_BASE = '/api/v1/forums';

const topicUrl = (id: string, suffix = '') => `${API_BASE}/topics/${encodeURIComponent(id)}${suffix}`;

interface Envelope<T> {
    data: T;
    meta?: Record<string, unknown>;
}

export function forumsApi(api: Api) {
    return {
        async topics(query: { forum?: string | null; q?: string | null; page?: number }): Promise<PagedList<ForumTopic>> {
            const { data } = await api.get<PagedList<ForumTopic>>(`${API_BASE}/topics`, {
                query: { forum: query.forum ?? undefined, q: query.q ?? undefined, page: query.page },
            });
            return data;
        },
        async createTopic(body: { forum: string; title: string; body: string; is_anonymous: boolean }): Promise<ForumTopic> {
            const { data } = await api.post<Envelope<ForumTopic>>(`${API_BASE}/topics`, body, { silent: true });
            return data.data;
        },
        async deleteTopic(id: string): Promise<void> {
            await api.delete(topicUrl(id));
        },
        async replies(id: string, page: number): Promise<PagedList<ForumReply>> {
            const { data } = await api.get<PagedList<ForumReply>>(topicUrl(id, '/replies'), { query: { page } });
            return data;
        },
        async reply(id: string, body: { body: string; is_anonymous: boolean }): Promise<{ reply: ForumReply; repliesCount: number }> {
            const { data } = await api.post<Envelope<ForumReply>>(topicUrl(id, '/replies'), body, { silent: true });
            return { reply: data.data, repliesCount: Number(data.meta?.replies_count ?? 0) };
        },
        async deleteReply(id: number): Promise<void> {
            await api.delete(`${API_BASE}/replies/${id}`);
        },
    };
}

export type ForumsApi = ReturnType<typeof forumsApi>;
