// Envoi multipart avec progression et annulation (P7-10, vidéo de 30 Mo) : `fetch` ne rend pas la progression d'un
// envoi, ce module utilise donc XMLHttpRequest, pour ce seul cas. Mêmes en-têtes et mêmes erreurs que app/useApi.ts :
// cookie XSRF, `Idempotency-Key`, 419 → cookie neuf puis un seul rejeu, 401 → connexion, 422 → ApiError avec les erreurs
// par champ, 429 → délai de Retry-After, 413 (requête plus grosse que post_max_size) → « trop lourd », réseau coupé →
// ApiError de statut 0. Annulation : `signal` abandonne la requête (AbortError), rien n'est publié.
import { ApiError, readCookie } from '@/app/useApi';
import { t } from '@/app/i18n';
import { route } from '@/app/routes';

export interface UploadOptions {
    idempotencyKey?: string;
    /** Pourcentage entier 0–100 de l'envoi du corps. */
    onProgress?: (percent: number) => void;
    signal?: AbortSignal;
    /** Poids maximal annoncé (Mo), pour le message d'un 413. */
    maxMb?: number;
}

export interface UploadResponse<T> {
    status: number;
    data: T;
}

const abortError = () => new DOMException('Envoi annulé', 'AbortError');

function send(url: string, body: FormData, options: UploadOptions): Promise<{ status: number; text: string; retryAfter: string | null }> {
    return new Promise((resolve, reject) => {
        if (options.signal?.aborted) {
            reject(abortError());
            return;
        }
        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        const xsrf = typeof document === 'undefined' ? '' : readCookie('XSRF-TOKEN', document.cookie);
        if (xsrf) xhr.setRequestHeader('X-XSRF-TOKEN', xsrf);
        if (options.idempotencyKey) xhr.setRequestHeader('Idempotency-Key', options.idempotencyKey);
        xhr.withCredentials = true;
        const onAbort = () => xhr.abort();
        options.signal?.addEventListener('abort', onAbort, { once: true });
        const done = () => options.signal?.removeEventListener('abort', onAbort);
        if (xhr.upload) {
            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable && event.total > 0) options.onProgress?.(Math.min(100, Math.round((event.loaded / event.total) * 100)));
            };
        }
        xhr.onload = () => {
            done();
            options.onProgress?.(100);
            resolve({ status: xhr.status, text: xhr.responseText ?? '', retryAfter: xhr.getResponseHeader('Retry-After') });
        };
        xhr.onerror = () => {
            done();
            reject(new ApiError(0, 'network', t('microcopy.error.unreachable')));
        };
        xhr.ontimeout = xhr.onerror;
        xhr.onabort = () => {
            done();
            reject(abortError());
        };
        xhr.send(body);
    });
}

const parse = (text: string): unknown => {
    try {
        return text === '' ? null : JSON.parse(text);
    } catch {
        return null;
    }
};

/** `POST` multipart suivi : rend `{ status, data }` (201 ou 202) ou lève une ApiError (ou AbortError si annulé). */
export async function uploadPost<T = unknown>(url: string, body: FormData, options: UploadOptions = {}): Promise<UploadResponse<T>> {
    let response = await send(url, body, options);
    if (response.status === 419) {
        // Jeton XSRF périmé : cookie neuf puis un seul rejeu (le corps FormData est relisible).
        try {
            await fetch('/sanctum/csrf-cookie', { method: 'GET', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        } catch {
            throw new ApiError(0, 'network', t('microcopy.error.unreachable'));
        }
        response = await send(url, body, options);
    }
    const data = parse(response.text);
    if (response.status >= 200 && response.status < 300) return { status: response.status, data: data as T };

    const envelope = (data && typeof data === 'object' ? data : {}) as { message?: unknown; code?: unknown; errors?: unknown };
    const serverMessage = typeof envelope.message === 'string' ? envelope.message : '';
    const code = typeof envelope.code === 'string' ? envelope.code : `http_${response.status}`;
    const errors = envelope.errors && typeof envelope.errors === 'object' ? (envelope.errors as Record<string, string[]>) : {};
    const retry = response.retryAfter === null ? null : Number(response.retryAfter);
    const retryAfter = retry !== null && Number.isFinite(retry) ? Math.max(0, Math.ceil(retry)) : null;

    if (response.status === 401) {
        if (typeof window !== 'undefined') window.location.assign(route('connexion', undefined, '/connexion'));
        throw new ApiError(401, code, serverMessage || t('errors.api.unauthenticated'), {}, null, data);
    }
    if (response.status === 413) {
        const max = options.maxMb ?? 30;
        throw new ApiError(413, code, t('feed.video.too_large', { max }), { video: [t('feed.video.too_large', { max })] }, null, data);
    }
    if (response.status === 422) throw new ApiError(422, code, serverMessage || t('microcopy.error.form'), errors, null, data);
    if (response.status === 429) {
        // Quota quotidien de vidéos : le serveur donne déjà une phrase adressée à la personne.
        const message = serverMessage || (retryAfter !== null ? t('microcopy.error.rate_limited', { seconds: retryAfter }) : t('errors.429.text_no_delay'));
        throw new ApiError(429, code, message, {}, retryAfter, data);
    }
    throw new ApiError(response.status, code, serverMessage || t('microcopy.error.generic'), {}, null, data);
}
