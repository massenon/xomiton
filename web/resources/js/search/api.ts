// Appels de l'API de la recherche membre depuis l'espace Vue (lot recherche-membre, P8-13 ; docs/API.md §20, routes
// api.search.*) : une famille page par page (« Charger plus » de /recherche), la vue d'ensemble (page /recherche en mode
// discret, terme hors de l'adresse) et les suggestions instantanées de l'en-tête
// (annulables : chaque frappe interrompt la requête précédente). Silencieux pour les suggestions (aucun toast : le
// champ reste utilisable, la page complète prend le relais). Le terme n'est gardé nulle part côté client non plus.
import type { Api } from '@/app/useApi';
import type { HitPage, SearchSection, SearchTypeValue, Suggestion } from './types';

export const API_BASE = '/api/v1';

export function searchApi(api: Api) {
    return {
        async page(q: string, type: SearchTypeValue, page: number, silent = false): Promise<HitPage> {
            const { data } = await api.get<HitPage>(`${API_BASE}/search`, { query: { q, type, page }, silent });
            return data;
        },
        async overview(q: string): Promise<SearchSection[]> {
            const { data } = await api.get<{ data: SearchSection[] }>(`${API_BASE}/search`, { query: { q }, silent: true });
            return data.data;
        },
        async suggestions(q: string, signal?: AbortSignal): Promise<SearchSection<Suggestion>[]> {
            const { data } = await api.get<{ data: SearchSection<Suggestion>[] }>(`${API_BASE}/search/suggestions`, { query: { q }, signal, silent: true });
            return data.data;
        },
    };
}

export type SearchApi = ReturnType<typeof searchApi>;
