// Appels de l'API du profil depuis l'écran Vue (lot profil, P8-07, P8-08 ; docs/API.md §3, §11, §18) : un seul endroit
// pour les adresses (/api/v1, hors Ziggy). Chaque fonction reçoit le client useApi() (XSRF, 419, 429, toasts) et rend
// l'objet utile, sans enveloppe.
import type { Api } from '@/app/useApi';
import type { CursorPage, Post } from '@/feed/types';
import type { ConnectionsPage, ProgressiveState } from './types';

export const API_BASE = '/api/v1';

const memberUrl = (pseudo: string, suffix = '') => `${API_BASE}/members/${encodeURIComponent(pseudo)}${suffix}`;

interface Envelope<T> {
    data: T;
}

/** Profil « moi » renvoyé par PATCH me/profile (vue « moi » de UserResource). */
export interface SelfProfile {
    pronouns: string | null;
    member_type: string | null;
    ally_status: string | null;
    gender_identity: string | null;
    orientation: string | null;
    age_range: string | null;
    interests: string[];
    shared_fields: string[];
    show_zone?: boolean;
    completion: number;
    progressive: ProgressiveState;
}

export interface ProfileImages {
    avatar_url: string | null;
    cover_url: string;
    completion: number;
    has_avatar: boolean;
    has_cover: boolean;
}

export function profileApi(api: Api) {
    return {
        async posts(userId: number, cursor: string | null): Promise<CursorPage<Post>> {
            const { data } = await api.get<CursorPage<Post>>(`${API_BASE}/users/${userId}/posts`, { query: { cursor } });
            return data;
        },
        async connections(pseudo: string, query: { q?: string | null; page?: number }): Promise<ConnectionsPage> {
            const { data } = await api.get<ConnectionsPage>(memberUrl(pseudo, '/connections'), { query: { q: query.q ?? undefined, page: query.page } });
            return data;
        },
        async updateProfile(body: Record<string, unknown>): Promise<SelfProfile> {
            const { data } = await api.patch<Envelope<{ profile: SelfProfile }>>(`${API_BASE}/me/profile`, body, { silent: true });
            return data.data.profile;
        },
        async uploadImage(kind: 'avatar' | 'cover', file: File): Promise<ProfileImages> {
            const form = new FormData();
            form.append('image', file);
            const { data } = await api.post<Envelope<ProfileImages>>(`${API_BASE}/me/profile/${kind}`, form, { silent: true });
            return data.data;
        },
        async removeImage(kind: 'avatar' | 'cover'): Promise<ProfileImages> {
            const { data } = await api.delete<Envelope<ProfileImages>>(`${API_BASE}/me/profile/${kind}`);
            return data.data;
        },
    };
}

export type ProfileApi = ReturnType<typeof profileApi>;
