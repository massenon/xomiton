// @vitest-environment jsdom
// useApi() (P6-05) : X-XSRF-TOKEN repris du cookie, 401 → connexion, 419 → /sanctum/csrf-cookie puis un seul rejeu,
// 422 → erreurs par champ sans toast, 429 → toast avec Retry-After, 5xx → toast, réseau coupé → ApiError network.
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { freshPinia, useFrench } from '@/test/helpers';
import { useUiStore } from '@/stores/ui';
import { t } from './i18n';
import { ApiError, createApi, fieldErrors, readCookie, useApi } from './useApi';

type Call = { url: string; init: RequestInit };

const json = (status: number, body: unknown, headers: Record<string, string> = {}) =>
    new Response(body === null ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json', ...headers } });

function fakeFetch(...responses: Array<Response | Error>) {
    const calls: Call[] = [];
    const fetch = vi.fn(async (url: RequestInfo | URL, init?: RequestInit) => {
        calls.push({ url: String(url), init: init ?? {} });
        const next = responses.shift();
        if (next instanceof Error) throw next;
        return next ?? json(200, {});
    });
    return { fetch: fetch as unknown as typeof globalThis.fetch, calls };
}

const header = (call: Call, name: string) => (call.init.headers as Record<string, string>)[name];

beforeEach(() => {
    freshPinia();
    useFrench();
});

describe('useApi', () => {
    it('envoie le cookie XSRF-TOKEN décodé dans X-XSRF-TOKEN, JSON et Idempotency-Key', async () => {
        const { fetch, calls } = fakeFetch(json(201, { data: { id: 7 } }));
        const api = createApi({ fetch, cookies: () => 'laravel_session=abc; XSRF-TOKEN=eyJ%3D%3D' });
        const res = await api.post<{ data: { id: number } }>('/api/v1/posts', { body: 'Bonjour' }, { idempotencyKey: 'k-1', query: { draft: 0, skip: null } });
        expect(res.status).toBe(201);
        expect(res.data.data.id).toBe(7);
        expect(calls[0].url).toBe('/api/v1/posts?draft=0');
        expect(calls[0].init.credentials).toBe('same-origin');
        expect(header(calls[0], 'X-XSRF-TOKEN')).toBe('eyJ==');
        expect(header(calls[0], 'Accept')).toBe('application/json');
        expect(header(calls[0], 'Content-Type')).toBe('application/json');
        expect(header(calls[0], 'Idempotency-Key')).toBe('k-1');
        expect(calls[0].init.body).toBe('{"body":"Bonjour"}');
    });

    it('FormData envoyé tel quel, 204 → data null', async () => {
        const { fetch, calls } = fakeFetch(new Response(null, { status: 204 }));
        const form = new FormData();
        form.append('file', new Blob(['x']), 'a.txt');
        const res = await createApi({ fetch, cookies: () => '' }).post('/api/v1/media', form);
        expect(res.data).toBeNull();
        expect(calls[0].init.body).toBe(form);
        expect(header(calls[0], 'Content-Type')).toBeUndefined();
        expect(header(calls[0], 'X-XSRF-TOKEN')).toBeUndefined();
    });

    it('401 → page de connexion', async () => {
        const onUnauthenticated = vi.fn();
        const { fetch } = fakeFetch(json(401, { message: 'Connexion requise.', code: 'unauthenticated' }));
        const error = await createApi({ fetch, cookies: () => '', onUnauthenticated }).get('/api/v1/me').catch((e) => e);
        expect(error).toBeInstanceOf(ApiError);
        expect(error.status).toBe(401);
        expect(onUnauthenticated).toHaveBeenCalledOnce();
    });

    it('419 → GET /sanctum/csrf-cookie puis rejeu avec le jeton neuf', async () => {
        let cookie = 'XSRF-TOKEN=ancien';
        const { fetch, calls } = fakeFetch(json(419, { message: 'CSRF token mismatch.' }), json(204, null), json(200, { data: 'ok' }));
        const wrapped = (async (url: RequestInfo | URL, init?: RequestInit) => {
            if (String(url) === '/sanctum/csrf-cookie') cookie = 'XSRF-TOKEN=neuf';
            return fetch(url, init);
        }) as typeof globalThis.fetch;
        const res = await createApi({ fetch: wrapped, cookies: () => cookie }).patch('/api/v1/me/settings', { theme: 'dark' });
        expect(res.data).toEqual({ data: 'ok' });
        expect(calls.map((c) => c.url)).toEqual(['/api/v1/me/settings', '/sanctum/csrf-cookie', '/api/v1/me/settings']);
        expect(header(calls[0], 'X-XSRF-TOKEN')).toBe('ancien');
        expect(header(calls[2], 'X-XSRF-TOKEN')).toBe('neuf');
    });

    it('second 419 : toast « Session expirée » (tu), pas de troisième essai', async () => {
        const { fetch, calls } = fakeFetch(json(419, {}), json(204, null), json(419, {}));
        const error = await useApi({ fetch, cookies: () => '' }).post('/api/v1/x').catch((e) => e);
        expect(error.status).toBe(419);
        expect(calls).toHaveLength(3);
        const toast = useUiStore().toasts[0];
        expect(toast.message).toBe(t('errors.419.member.text'));
        expect(toast.message).toContain('Ta session a expiré.');
        expect(toast.urgent).toBe(true);
    });

    it('422 → erreurs par champ, sans toast', async () => {
        const { fetch } = fakeFetch(json(422, { message: 'Les données envoyées sont invalides.', code: 'validation_failed', errors: { pseudo: ['Choisis un pseudo.'], 'contacts.0.phone': ['Numéro invalide.', 'Autre'] } }));
        const error = await useApi({ fetch, cookies: () => '' }).post('/api/v1/me/profile', {}).catch((e) => e);
        expect(error.status).toBe(422);
        expect(error.code).toBe('validation_failed');
        expect(fieldErrors(error)).toEqual({ pseudo: 'Choisis un pseudo.', 'contacts.0.phone': 'Numéro invalide.' });
        expect(useUiStore().toasts).toHaveLength(0);
        expect(fieldErrors(new Error('x'))).toEqual({});
    });

    it('429 → toast avec le délai de Retry-After ; silent : aucun toast', async () => {
        const { fetch } = fakeFetch(json(429, { code: 'throttled' }, { 'Retry-After': '42' }), json(429, {}, {}));
        const api = useApi({ fetch, cookies: () => '' });
        const error = await api.post('/api/v1/posts', {}).catch((e) => e);
        expect(error.retryAfter).toBe(42);
        expect(useUiStore().toasts[0]).toMatchObject({ message: 'Trop de tentatives. Réessaie dans 42 secondes.', type: 'warning' });
        await api.post('/api/v1/posts', {}, { silent: true }).catch(() => undefined);
        expect(useUiStore().toasts).toHaveLength(1);
    });

    it('5xx → toast d’erreur générique ; 503 garde le message du serveur', async () => {
        const { fetch } = fakeFetch(json(500, { message: 'Erreur interne.' }), json(503, { message: 'Plateforme en maintenance.' }));
        const api = useApi({ fetch, cookies: () => '' });
        await api.get('/api/v1/a').catch(() => undefined);
        await api.get('/api/v1/b').catch(() => undefined);
        expect(useUiStore().toasts.map((t) => t.message)).toEqual([
            t('microcopy.error.generic'),
            'Plateforme en maintenance.',
        ]);
    });

    it('réseau coupé → ApiError network (statut 0), sans toast ; 403 sans toast', async () => {
        const { fetch } = fakeFetch(new TypeError('Failed to fetch'), json(403, { message: 'Action non autorisée.', code: 'forbidden' }));
        const api = useApi({ fetch, cookies: () => '' });
        const offline = await api.get('/api/v1/feed').catch((e) => e);
        expect(offline).toMatchObject({ status: 0, code: 'network', message: t('microcopy.error.unreachable') });
        // Aucune promesse d'envoi différé hors de la boîte d'envoi.
        expect(offline.message).not.toContain('partira');
        const forbidden = await api.delete('/api/v1/posts/1').catch((e) => e);
        expect(forbidden).toMatchObject({ status: 403, code: 'forbidden', message: 'Action non autorisée.' });
        expect(useUiStore().toasts).toHaveLength(0);
    });

    it('readCookie : absent, encodé, mal encodé', () => {
        expect(readCookie('XSRF-TOKEN', 'a=1')).toBe('');
        expect(readCookie('XSRF-TOKEN', 'a=1; XSRF-TOKEN=x%2By')).toBe('x+y');
        expect(readCookie('XSRF-TOKEN', 'XSRF-TOKEN=%E0%A4%A')).toBe('%E0%A4%A');
    });
});
