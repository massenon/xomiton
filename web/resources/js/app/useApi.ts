// Client unique de l'API /api/v1 dans l'espace Vue (P6-05, DEVELOPMENT_STANDARDS §4, ADR-31 : Sanctum en mode SPA, même
// domaine, cookie de session + XSRF-TOKEN). Gestion centralisée des réponses :
//  - chaque envoi reprend le cookie `XSRF-TOKEN` dans l'en-tête `X-XSRF-TOKEN` (jamais stocké) ;
//  - 401 → page de connexion ;
//  - 419 → `GET /sanctum/csrf-cookie` puis rejeu, une seule fois (un second 419 : toast « Session expirée ») ;
//  - 422 → ApiError avec les erreurs par champ (`fieldErrors()` pour <XmField :error>), sans toast ;
//  - 429 → toast « Trop de tentatives » avec le délai de Retry-After ; 5xx → toast d'erreur générique ;
//  - 503 `maintenance` (P13-08) → aucun toast : `onMaintenance` (par défaut l'évènement `xm:maintenance`, qui ouvre
//    l'écran plein XmMaintenance, app/shellBoot.ts) ;
//  - réseau coupé → ApiError `network` (statut 0), sans toast : le bandeau hors connexion informe déjà, et les envois
//    rejouables passent par le store `outbox`.
// Corps : objet → JSON, FormData tel quel. Réponse : `{ status, data, headers }`, `data` = corps JSON entier
// (enveloppe `{ data, meta }` des ressources comprise). Textes : lang/fr (microcopy.error, errors.419.member, errors.429).
import { t } from './i18n';
import { route } from './routes';
import { useToast } from './useToast';
import { isMaintenanceResponse, MAINTENANCE_EVENT, type MaintenancePayload } from './shellBoot';
import type { ToastType } from './types';

export type HttpMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
export type Query = Record<string, string | number | boolean | null | undefined>;

export interface ApiOptions {
    query?: Query;
    headers?: Record<string, string>;
    /** Envoi rejouable (ADR-32) : même clé à chaque essai. */
    idempotencyKey?: string;
    /** Aucun toast (l'écran affiche lui-même l'erreur). */
    silent?: boolean;
    signal?: AbortSignal;
}

export interface ApiResponse<T> {
    status: number;
    data: T;
    headers: Headers;
}

export class ApiError extends Error {
    readonly status: number;
    readonly code: string;
    readonly errors: Record<string, string[]>;
    readonly retryAfter: number | null;
    readonly data: unknown;

    constructor(status: number, code: string, message: string, errors: Record<string, string[]> = {}, retryAfter: number | null = null, data: unknown = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.code = code;
        this.errors = errors;
        this.retryAfter = retryAfter;
        this.data = data;
    }
}

export interface ApiDeps {
    fetch?: typeof fetch;
    cookies?: () => string;
    toast?: (message: string, type: ToastType) => void;
    onUnauthenticated?: () => void;
    /** 503 `maintenance` : écran plein au lieu d'un toast (défaut : évènement `xm:maintenance` sur window). */
    onMaintenance?: (payload: MaintenancePayload) => void;
    csrfUrl?: string;
}

/** Valeur d'un cookie (même lecture que shared/outbox.js, sans charger la boîte d'envoi ni idb dans app.ts). */
export function readCookie(name: string, source: string): string {
    const match = source.split(';').map((part) => part.trim()).find((part) => part.startsWith(`${name}=`));
    if (!match) return '';
    try {
        return decodeURIComponent(match.slice(name.length + 1));
    } catch {
        return match.slice(name.length + 1);
    }
}

/** Première erreur de chaque champ : `{ pseudo: 'Choisis…' }`, pour <XmField :error> et <XmErrorSummary>. */
export function fieldErrors(error: unknown): Record<string, string> {
    if (!(error instanceof ApiError)) return {};
    return Object.fromEntries(Object.entries(error.errors).map(([field, messages]) => [field, messages[0] ?? '']));
}

const withQuery = (url: string, query?: Query): string => {
    if (!query) return url;
    const params = new URLSearchParams();
    Object.entries(query).forEach(([key, value]) => {
        if (value !== null && value !== undefined) params.append(key, String(value));
    });
    const qs = params.toString();
    return qs ? `${url}${url.includes('?') ? '&' : '?'}${qs}` : url;
};

const retryAfterOf = (headers: Headers): number | null => {
    const value = headers.get('Retry-After');
    if (value === null || value === '') return null;
    const seconds = Number(value);
    if (Number.isFinite(seconds)) return Math.max(0, Math.ceil(seconds));
    const date = Date.parse(value);
    return Number.isNaN(date) ? null : Math.max(0, Math.ceil((date - Date.now()) / 1000));
};

const parse = async (response: Response): Promise<unknown> => {
    if (response.status === 204) return null;
    const type = response.headers.get('Content-Type') || '';
    if (type.includes('json')) {
        try {
            return await response.json();
        } catch {
            return null;
        }
    }
    const text = await response.text();
    return text === '' ? null : text;
};

const defaultMaintenance = (payload: MaintenancePayload): void => {
    if (typeof window === 'undefined') return;
    window.dispatchEvent(new CustomEvent(MAINTENANCE_EVENT, { detail: payload }));
};

const defaultLogin = (): void => {
    if (typeof window === 'undefined') return;
    window.location.assign(route('connexion', undefined, '/connexion'));
};

export function createApi(deps: ApiDeps = {}) {
    const fetchImpl = deps.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
    const cookies = deps.cookies ?? (() => (typeof document === 'undefined' ? '' : document.cookie));
    const toast = deps.toast ?? (() => undefined);
    const onUnauthenticated = deps.onUnauthenticated ?? defaultLogin;
    const onMaintenance = deps.onMaintenance ?? defaultMaintenance;
    const csrfUrl = deps.csrfUrl ?? '/sanctum/csrf-cookie';

    const send = (method: HttpMethod, url: string, body: unknown, options: ApiOptions): Promise<Response> => {
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers ?? {}),
        };
        const xsrf = readCookie('XSRF-TOKEN', cookies());
        if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
        if (options.idempotencyKey) headers['Idempotency-Key'] = options.idempotencyKey;
        let payload: BodyInit | undefined;
        if (body instanceof FormData) {
            payload = body;
        } else if (body !== undefined && method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            payload = JSON.stringify(body);
        }
        return fetchImpl(withQuery(url, options.query), { method, headers, body: payload, credentials: 'same-origin', signal: options.signal });
    };

    async function request<T = unknown>(method: HttpMethod, url: string, body?: unknown, options: ApiOptions = {}): Promise<ApiResponse<T>> {
        const notify = (message: string, type: ToastType) => {
            if (!options.silent) toast(message, type);
        };
        let response: Response;
        try {
            response = await send(method, url, body, options);
            if (response.status === 419) {
                // Jeton XSRF périmé (session régénérée, onglet resté ouvert) : cookie neuf puis un seul rejeu.
                await fetchImpl(csrfUrl, { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
                response = await send(method, url, body, options);
            }
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') throw error;
            // Texte neutre : « partira dès que le réseau revient » (microcopy.error.network) reste aux envois mis en file
            // par le store outbox.
            throw new ApiError(0, 'network', t('microcopy.error.unreachable'));
        }

        const data = await parse(response);
        if (response.ok) return { status: response.status, data: data as T, headers: response.headers };

        const envelope = (data && typeof data === 'object' ? data : {}) as { message?: unknown; code?: unknown; errors?: unknown };
        const serverMessage = typeof envelope.message === 'string' ? envelope.message : '';
        const code = typeof envelope.code === 'string' ? envelope.code : `http_${response.status}`;
        const errors = envelope.errors && typeof envelope.errors === 'object' ? (envelope.errors as Record<string, string[]>) : {};
        const retryAfter = retryAfterOf(response.headers);

        switch (true) {
            case response.status === 401:
                onUnauthenticated();
                throw new ApiError(401, code, serverMessage || t('errors.api.unauthenticated'), {}, null, data);
            case response.status === 419: {
                const message = t('errors.419.member.text');
                notify(message, 'danger');
                throw new ApiError(419, code, message, {}, null, data);
            }
            case response.status === 422:
                throw new ApiError(422, code, serverMessage || t('microcopy.error.form'), errors, null, data);
            case response.status === 429: {
                const message = retryAfter !== null ? t('microcopy.error.rate_limited', { seconds: retryAfter }) : t('errors.429.text_no_delay');
                notify(message, 'warning');
                throw new ApiError(429, code, message, {}, retryAfter, data);
            }
            case isMaintenanceResponse(response.status, response.headers, data): {
                const message = serverMessage || t('microcopy.error.generic');
                onMaintenance((data && typeof data === 'object' ? data : {}) as MaintenancePayload);
                throw new ApiError(503, 'maintenance', message, {}, retryAfter, data);
            }
            case response.status >= 500: {
                const message = response.status === 503 && serverMessage ? serverMessage : t('microcopy.error.generic');
                notify(message, 'danger');
                throw new ApiError(response.status, code, message, {}, retryAfter, data);
            }
            default:
                throw new ApiError(response.status, code, serverMessage || t('microcopy.error.generic'), errors, retryAfter, data);
        }
    }

    return {
        request,
        get: <T = unknown>(url: string, options?: ApiOptions) => request<T>('GET', url, undefined, options),
        post: <T = unknown>(url: string, body?: unknown, options?: ApiOptions) => request<T>('POST', url, body, options),
        put: <T = unknown>(url: string, body?: unknown, options?: ApiOptions) => request<T>('PUT', url, body, options),
        patch: <T = unknown>(url: string, body?: unknown, options?: ApiOptions) => request<T>('PATCH', url, body, options),
        delete: <T = unknown>(url: string, body?: unknown, options?: ApiOptions) => request<T>('DELETE', url, body, options),
    };
}

export type Api = ReturnType<typeof createApi>;

/** Client de l'application : toasts par useToast() (Pinia actif requis). */
export function useApi(deps: Omit<ApiDeps, 'toast'> = {}): Api {
    const toasts = useToast();
    return createApi({ ...deps, toast: (message, type) => toasts.show(message, type, { urgent: type === 'danger' }) });
}
