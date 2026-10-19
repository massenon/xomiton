// Appels de l'API « mon compte » depuis l'écran /parametres (lot parametres-notifications, P8-09 ; docs/API.md §3) :
// un seul endroit pour les adresses (/api/v1, hors Ziggy). Chaque fonction reçoit le client useApi() (XSRF, 419, 429,
// toasts) et rend l'objet utile, sans enveloppe. Les erreurs 422 remontent en ApiError (fieldErrors) sans toast.
import type { Api } from '@/app/useApi';
import type { ChannelPrefs, Identity, Newsletter, Privacy, Security, SessionRow } from './types';

export const ME = '/api/v1/me';

interface Envelope<T> {
    data: T;
}

export function settingsApi(api: Api) {
    return {
        async updateIdentity(body: Partial<Identity>): Promise<Identity> {
            const { data } = await api.patch<Envelope<Identity>>(`${ME}/identity`, body, { silent: true });
            return data.data;
        },
        async changePassword(body: { current_password: string; password: string; password_confirmation: string }): Promise<{ changed: boolean; revoked: number }> {
            const { data } = await api.put<Envelope<{ changed: boolean; revoked: number }>>(`${ME}/password`, body, { silent: true });
            return data.data;
        },
        async updatePrivacy(body: Partial<Privacy>): Promise<Privacy> {
            const { data } = await api.patch<Envelope<Privacy>>(`${ME}/privacy`, body, { silent: true });
            return data.data;
        },
        /** PATCH me/settings : thème, langue, préférences de notification, résumé (sécurité et SOS ignorés côté serveur). */
        async updateSettings(body: { theme?: string; locale?: string; notification_prefs?: Record<string, Partial<ChannelPrefs>>; notification_digest?: string }): Promise<void> {
            await api.patch(`${ME}/settings`, body, { silent: true });
        },
        async updateSecurity(body: Partial<Security>): Promise<Security> {
            const { data } = await api.patch<Envelope<Security>>(`${ME}/security`, body, { silent: true });
            return data.data;
        },
        async updateNewsletter(body: { subscribed?: boolean; interests?: string[] }): Promise<Newsletter> {
            const { data } = await api.patch<Envelope<Newsletter>>(`${ME}/newsletter`, body, { silent: true });
            return data.data;
        },
        async sessions(): Promise<SessionRow[]> {
            const { data } = await api.get<Envelope<SessionRow[]>>(`${ME}/sessions`, { silent: true });
            return Array.isArray(data.data) ? data.data : [];
        },
        async revokeSession(id: string): Promise<void> {
            await api.delete(`${ME}/sessions/${encodeURIComponent(id)}`);
        },
        async revokeOtherSessions(): Promise<number> {
            const { data } = await api.delete<Envelope<{ revoked: number }>>(`${ME}/sessions`);
            return Number(data.data?.revoked) || 0;
        },
        /** DELETE me/devices/{uuid} : 204, l'appareil reste listé comme retiré. */
        async revokeDevice(uuid: string): Promise<void> {
            await api.delete(`${ME}/devices/${encodeURIComponent(uuid)}`);
        },
        async requestExport(): Promise<void> {
            await api.post(`${ME}/export`, {}, { silent: true });
        },
        async deleteAccount(password: string): Promise<void> {
            await api.delete(ME, { password }, { silent: true });
        },
    };
}

export type SettingsApi = ReturnType<typeof settingsApi>;
