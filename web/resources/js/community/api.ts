// Appels de l'API des groupes et des pages depuis les écrans Vue (lot groupes-pages ; docs/API.md §13 et §14, routes
// api.groups.*, api.pages.*) : un seul endroit pour les adresses (/api/v1, hors Ziggy). Chaque fonction reçoit le client
// useApi() (XSRF, 419, 429, toasts) et rend l'objet utile, sans enveloppe. Publier dans un groupe : `POST posts` avec
// `group` (feed/api.ts, composer du fil).
import type { Api } from '@/app/useApi';
import type { CursorPage, Post } from '@/feed/types';
import type { FollowState, Group, GroupMember, OrganizationPage, PagedList } from './types';

export const API_BASE = '/api/v1';

const groupUrl = (id: string, suffix = '') => `${API_BASE}/groups/${encodeURIComponent(id)}${suffix}`;
const pageUrl = (id: string, suffix = '') => `${API_BASE}/pages/${encodeURIComponent(id)}${suffix}`;

interface Envelope<T> {
    data: T;
    meta?: Record<string, unknown>;
}

export function communityApi(api: Api) {
    return {
        async groups(query: { tab?: string; q?: string | null; page?: number }): Promise<PagedList<Group>> {
            const { data } = await api.get<PagedList<Group>>(`${API_BASE}/groups`, { query: { tab: query.tab, q: query.q ?? undefined, page: query.page } });
            return data;
        },
        async createGroup(body: { name: string; description: string | null; privacy: string; zone_id: number | null }): Promise<Group> {
            const { data } = await api.post<Envelope<Group>>(`${API_BASE}/groups`, body, { silent: true });
            return data.data;
        },
        async join(id: string): Promise<Group> {
            return (await api.post<Envelope<Group>>(groupUrl(id, '/join'))).data.data;
        },
        async leave(id: string): Promise<Group> {
            return (await api.post<Envelope<Group>>(groupUrl(id, '/leave'))).data.data;
        },
        async members(id: string, query: { status?: 'active' | 'requested'; page?: number } = {}): Promise<PagedList<GroupMember>> {
            const { data } = await api.get<PagedList<GroupMember>>(groupUrl(id, '/members'), { query });
            return data;
        },
        async accept(id: string, userId: number): Promise<Group> {
            return (await api.post<Envelope<Group>>(groupUrl(id, `/members/${userId}/accept`))).data.data;
        },
        async removeMember(id: string, userId: number): Promise<Group> {
            return (await api.delete<Envelope<Group>>(groupUrl(id, `/members/${userId}`))).data.data;
        },
        async setRole(id: string, userId: number, role: string): Promise<GroupMember> {
            return (await api.patch<Envelope<GroupMember>>(groupUrl(id, `/members/${userId}`), { role })).data.data;
        },
        async posts(id: string, cursor?: string | null): Promise<CursorPage<Post>> {
            const { data } = await api.get<CursorPage<Post>>(groupUrl(id, '/posts'), { query: { cursor: cursor ?? undefined } });
            return data;
        },
        /** Discussion du groupe (conversation rattachée, P8-01) : `{ id, url }` de /messages?c=. */
        async discussion(id: string): Promise<{ id: string; url: string }> {
            return (await api.post<Envelope<{ id: string; url: string }>>(groupUrl(id, '/conversation'))).data.data;
        },
        async pages(query: { tab?: string; category?: string | null; q?: string | null; page?: number }): Promise<PagedList<OrganizationPage>> {
            const { data } = await api.get<PagedList<OrganizationPage>>(`${API_BASE}/pages`, {
                query: { tab: query.tab, category: query.category ?? undefined, q: query.q ?? undefined, page: query.page },
            });
            return data;
        },
        async createPage(body: { name: string; category: string; description: string | null; website: string | null }): Promise<OrganizationPage> {
            const { data } = await api.post<Envelope<OrganizationPage>>(`${API_BASE}/pages`, body, { silent: true });
            return data.data;
        },
        async follow(id: string, on: boolean): Promise<FollowState> {
            const url = pageUrl(id, '/follow');
            return (on ? await api.post<Envelope<FollowState>>(url) : await api.delete<Envelope<FollowState>>(url)).data.data;
        },
    };
}

export type CommunityApi = ReturnType<typeof communityApi>;
