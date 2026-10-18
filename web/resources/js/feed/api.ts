// Appels de l'API du fil depuis les écrans Vue (lot fil-ecrans ; docs/API.md §8, routes api.feed.*, api.posts.*,
// api.comments.*) : un seul endroit pour les adresses (/api/v1, hors Ziggy : seul le groupe « member » est écrit dans
// la page). Chaque fonction reçoit le client useApi() (XSRF, 419, 429, toasts) et rend l'objet utile, sans enveloppe.
import type { Api, ApiOptions } from '@/app/useApi';
import type { Comment, CursorPage, Poll, Post } from './types';

export const API_BASE = '/api/v1';
export const POSTS_URL = `${API_BASE}/posts`;

const postUrl = (id: string, suffix = '') => `${POSTS_URL}/${encodeURIComponent(id)}${suffix}`;

interface Envelope<T> {
    data: T;
    meta?: Record<string, unknown>;
}

export function feedApi(api: Api) {
    const toggle = async <T>(url: string, on: boolean, options?: ApiOptions): Promise<T> =>
        (on ? await api.post<Envelope<T>>(url, undefined, options) : await api.delete<Envelope<T>>(url, undefined, options)).data.data;

    return {
        /** Page suivante du fil (`feed`, `?tag=`) ou des enregistrées (`feed/saved`). */
        async list(kind: 'feed' | 'saved', { cursor, tag }: { cursor?: string | null; tag?: string | null } = {}): Promise<CursorPage<Post>> {
            const url = kind === 'saved' ? `${API_BASE}/feed/saved` : `${API_BASE}/feed`;
            const { data } = await api.get<CursorPage<Post>>(url, { query: { cursor: cursor ?? undefined, tag: kind === 'feed' ? tag ?? undefined : undefined } });
            return data;
        },
        async comments(id: string, cursor?: string | null): Promise<CursorPage<Comment>> {
            const { data } = await api.get<CursorPage<Comment>>(postUrl(id, '/comments'), { query: { cursor: cursor ?? undefined } });
            return data;
        },
        like: (id: string, on: boolean) => toggle<{ liked: boolean; likes_count: number }>(postUrl(id, '/like'), on),
        save: (id: string, on: boolean) => toggle<{ saved: boolean }>(postUrl(id, '/save'), on),
        hide: (id: string, on: boolean) => toggle<{ hidden: boolean }>(postUrl(id, '/hide'), on),
        /** Vote (remplace le précédent) ou retrait du vote (`choices` nul). */
        async vote(id: string, choices: number[] | null): Promise<Poll> {
            const url = postUrl(id, '/poll/vote');
            const response = choices === null
                ? await api.delete<Envelope<Poll>>(url, undefined, { silent: true })
                : await api.post<Envelope<Poll>>(url, { choices }, { silent: true });
            return response.data.data;
        },
        async report(target: { post: string } | { comment: number }, reason: string, details: string | null): Promise<{ created: boolean }> {
            const url = 'post' in target ? postUrl(target.post, '/report') : `${API_BASE}/comments/${target.comment}/report`;
            const response = await api.post<Envelope<{ reported: boolean }>>(url, { reason, details: details || null });
            return { created: response.status === 201 };
        },
        async addComment(id: string, body: string): Promise<{ comment: Comment; count: number }> {
            const { data } = await api.post<Envelope<Comment>>(postUrl(id, '/comments'), { body });
            return { comment: data.data, count: Number(data.meta?.comments_count) || 0 };
        },
        async deleteComment(id: number): Promise<void> {
            await api.delete(`${API_BASE}/comments/${id}`);
        },
        async deletePost(id: string): Promise<void> {
            await api.delete(postUrl(id));
        },
        /** Publication ou sondage (201) ; JSON, ou multipart avec une image ; clé rejouable (ADR-32). */
        async create(body: FormData | Record<string, unknown>, idempotencyKey: string): Promise<Post> {
            const { data } = await api.post<Envelope<Post>>(POSTS_URL, body, { idempotencyKey, silent: true });
            return data.data;
        },
    };
}

export type FeedApi = ReturnType<typeof feedApi>;
