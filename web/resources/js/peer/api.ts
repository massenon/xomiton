// Appels de l'API `pair/` depuis les écrans de l'espace pair (lot espace-pair ; docs/API.md §22, routes api.pair.*) :
// alertes routées vers moi (liste, détail, accusé, statut) et suivi des dossiers (étape ajoutée, étape réalisée, statut
// du dossier). Chaque fonction reçoit le client useApi() (XSRF, 419, 429 et 5xx annoncés par toast) et rend l'objet utile,
// sans enveloppe ; les 422 sont affichés par l'écran. La collecte, elle, passe par la boîte d'envoi (store `outbox`).
import type { Api } from '@/app/useApi';
import type { AlertDetail, CaseItem, Followup, RoutedAlert } from './types';

export const API_BASE = '/api/v1/pair';

export interface FollowupPayload {
    date: string;
    step: string;
    outcome: string;
    next_on?: string;
    comment?: string;
    client_uuid: string;
}

export interface RoutedAlertPage {
    items: RoutedAlert[];
    new: number;
    total: number;
}

export function peerApi(api: Api) {
    return {
        /** Une page des alertes ouvertes ; `total` = alertes ouvertes en tout (meta.total), `new` = sans accusé de réception. */
        async alerts(silent = false, page = 1, perPage?: number): Promise<RoutedAlertPage> {
            const query: Record<string, string | number> = { state: 'open' };
            if (perPage !== undefined) query.per_page = perPage;
            if (page > 1) query.page = page;
            const { data } = await api.get<{ data: RoutedAlert[]; meta: { new: number; total?: number } }>(`${API_BASE}/alerts`, { query, silent });
            return { items: data.data, new: data.meta.new, total: data.meta.total ?? data.data.length };
        },
        async alert(id: string): Promise<AlertDetail> {
            const { data } = await api.get<{ data: AlertDetail }>(`${API_BASE}/alerts/${id}`, { silent: true });
            return data.data;
        },
        async acknowledge(id: string): Promise<AlertDetail> {
            const { data } = await api.post<{ data: AlertDetail }>(`${API_BASE}/alerts/${id}/acknowledge`, {});
            return data.data;
        },
        async changeStatus(id: string, status: string): Promise<AlertDetail> {
            const { data } = await api.post<{ data: AlertDetail }>(`${API_BASE}/alerts/${id}/status`, { status });
            return data.data;
        },
        async addFollowup(code: string, payload: FollowupPayload): Promise<Followup> {
            const { data } = await api.post<{ data: Followup }>(`${API_BASE}/cases/${code}/followups`, payload, { idempotencyKey: payload.client_uuid });
            return data.data;
        },
        async completeFollowup(code: string, id: number): Promise<Followup> {
            const { data } = await api.patch<{ data: Followup }>(`${API_BASE}/cases/${code}/followups/${id}`, { outcome: 'realise' });
            return data.data;
        },
        async changeCaseStatus(code: string, status: string): Promise<CaseItem> {
            const { data } = await api.patch<{ data: CaseItem }>(`${API_BASE}/cases/${code}`, { status });
            return data.data;
        },
    };
}

export type PeerApi = ReturnType<typeof peerApi>;
