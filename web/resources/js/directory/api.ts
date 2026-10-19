// Appels de l'API de l'annuaire membre depuis l'écran Vue (lot annuaire-membre, P8-12 ; docs/API.md §19, routes
// api.directory.*) : liste filtrée et paginée (« Charger plus ») et demande de rappel, rejouable par son
// Idempotency-Key (ADR-32). Chaque fonction reçoit le client useApi() (XSRF, 419, 429 et 5xx annoncés par toast) et
// rend l'objet utile, sans enveloppe ; les 422 sont affichés par la modale.
import type { Api } from '@/app/useApi';
import type { CallbackPayload, CallbackResult, ContactPage, Filters } from './types';

export const API_BASE = '/api/v1';

export function directoryApi(api: Api) {
    return {
        async list(filters: Filters, page: number): Promise<ContactPage> {
            const { data } = await api.get<ContactPage>(`${API_BASE}/directory`, {
                query: {
                    q: filters.q ?? undefined,
                    role: filters.role ?? undefined,
                    zone: filters.zone ?? undefined,
                    commune: filters.commune ?? undefined,
                    page,
                },
            });
            return data;
        },
        async callback(contactId: number, payload: CallbackPayload, idempotencyKey: string): Promise<CallbackResult> {
            const { data } = await api.post<{ data: CallbackResult }>(`${API_BASE}/directory/${contactId}/callback`, payload, { idempotencyKey });
            return data.data;
        },
    };
}

export type DirectoryApi = ReturnType<typeof directoryApi>;
