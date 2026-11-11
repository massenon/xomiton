// Appels de l'API « Qui suivre et invitations » depuis les écrans Vue (lot qui-suivre, P8-06 ; docs/API.md §17, routes
// api.follows.*, api.invitations.*) : un seul endroit pour les adresses (/api/v1, hors Ziggy). Chaque fonction reçoit le
// client useApi() (XSRF, 419, 429 et 5xx annoncés par toast) et rend l'objet utile, sans enveloppe ; les autres refus
// (403, 404, 422 avec le message de l'API) sont annoncés par l'écran (useNetworkActions).
import type { Api } from '@/app/useApi';
import type { Counts, FollowState, Invitation, PagedList, PublicRole, Suggestion } from './types';

export const API_BASE = '/api/v1';

interface Envelope<T> {
    data: T;
    meta?: { created?: boolean; counts?: Counts };
}

export interface InvitationResult {
    invitation: Invitation;
    created: boolean;
    counts: Counts | null;
}

const result = (body: Envelope<Invitation>): InvitationResult => ({
    invitation: body.data,
    created: Boolean(body.meta?.created),
    counts: body.meta?.counts ?? null,
});

export function networkApi(api: Api) {
    return {
        async suggestions(query: { q?: string | null; zone?: number | null; role?: PublicRole | null; cursor?: string | null }): Promise<PagedList<Suggestion>> {
            const { data } = await api.get<PagedList<Suggestion>>(`${API_BASE}/follows/suggestions`, {
                query: { q: query.q ?? undefined, zone: query.zone ?? undefined, role: query.role ?? undefined, cursor: query.cursor ?? undefined },
            });
            return data;
        },
        async invitations(box: 'received' | 'sent', cursor?: string | null): Promise<PagedList<Invitation>> {
            const { data } = await api.get<PagedList<Invitation>>(`${API_BASE}/invitations`, { query: { box, cursor: cursor ?? undefined } });
            return data;
        },
        async invite(userId: number): Promise<InvitationResult> {
            return result((await api.post<Envelope<Invitation>>(`${API_BASE}/invitations`, { user_id: userId })).data);
        },
        async accept(id: number): Promise<InvitationResult> {
            return result((await api.post<Envelope<Invitation>>(`${API_BASE}/invitations/${id}/accept`, undefined)).data);
        },
        async refuse(id: number): Promise<InvitationResult> {
            return result((await api.post<Envelope<Invitation>>(`${API_BASE}/invitations/${id}/refuse`, undefined)).data);
        },
        async cancel(id: number): Promise<void> {
            await api.delete(`${API_BASE}/invitations/${id}`);
        },
        async follow(userId: number, on: boolean): Promise<FollowState> {
            const response = on
                ? await api.post<Envelope<FollowState>>(`${API_BASE}/follows`, { user_id: userId })
                : await api.delete<Envelope<FollowState>>(`${API_BASE}/follows/${userId}`);
            return response.data.data;
        },
    };
}

export type NetworkApi = ReturnType<typeof networkApi>;
