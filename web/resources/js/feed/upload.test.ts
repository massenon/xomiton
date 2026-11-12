// @vitest-environment jsdom
// Envoi multipart avec progression (P7-10) : en-têtes comme useApi (XSRF, Idempotency-Key, JSON), progression en
// pourcentage entier, annulation (AbortError, requête abandonnée), 419 → cookie neuf puis un seul rejeu, 422 → ApiError avec
// les erreurs par champ, 413 → « vidéo trop lourde », 429 → message du serveur et Retry-After, réseau coupé → statut 0.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError } from '@/app/useApi';
import { useFrench } from '@/test/helpers';
import { uploadPost } from './upload';

class Xhr {
    static instances: Xhr[] = [];
    method = '';
    url = '';
    headers: Record<string, string> = {};
    status = 0;
    responseText = '';
    retryAfter: string | null = null;
    withCredentials = false;
    aborted = false;
    upload: { onprogress: ((event: { lengthComputable: boolean; loaded: number; total: number }) => void) | null } = { onprogress: null };
    onload: (() => void) | null = null;
    onerror: (() => void) | null = null;
    onabort: (() => void) | null = null;
    ontimeout: (() => void) | null = null;
    constructor() { Xhr.instances.push(this); }
    open(method: string, url: string) { this.method = method; this.url = url; }
    setRequestHeader(name: string, value: string) { this.headers[name] = value; }
    getResponseHeader(name: string) { return name === 'Retry-After' ? this.retryAfter : null; }
    send() {}
    abort() { this.aborted = true; this.onabort?.(); }
    respond(status: number, body: unknown, retryAfter: string | null = null) { this.status = status; this.responseText = JSON.stringify(body); this.retryAfter = retryAfter; this.onload?.(); }
}

const form = () => { const data = new FormData(); data.append('kind', 'post'); return data; };
const last = () => Xhr.instances[Xhr.instances.length - 1];
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

beforeEach(() => {
    useFrench();
    Xhr.instances = [];
    vi.stubGlobal('XMLHttpRequest', Xhr);
    document.cookie = 'XSRF-TOKEN=jeton%20xsrf';
});

afterEach(() => {
    vi.unstubAllGlobals();
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
});

describe('uploadPost', () => {
    it('envoie avec les en-têtes de l’API, rend le statut (202) et annonce la progression en pourcentage entier', async () => {
        const progress: number[] = [];
        const promise = uploadPost('/api/v1/posts', form(), { idempotencyKey: 'cle-1', onProgress: (percent) => progress.push(percent) });
        const xhr = last();
        expect(xhr).toMatchObject({ method: 'POST', url: '/api/v1/posts', withCredentials: true });
        expect(xhr.headers).toMatchObject({ Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': 'jeton xsrf', 'Idempotency-Key': 'cle-1' });
        expect(xhr.headers['Content-Type']).toBeUndefined();
        xhr.upload.onprogress?.({ lengthComputable: true, loaded: 1, total: 3 });
        xhr.upload.onprogress?.({ lengthComputable: false, loaded: 2, total: 0 });
        xhr.upload.onprogress?.({ lengthComputable: true, loaded: 3, total: 3 });
        xhr.respond(202, { data: { id: 'x', review_state: 'pending' } });
        await expect(promise).resolves.toEqual({ status: 202, data: { data: { id: 'x', review_state: 'pending' } } });
        expect(progress).toEqual([33, 100, 100]);
    });

    it('annule : la requête est abandonnée et l’appelant reçoit une AbortError', async () => {
        const controller = new AbortController();
        const promise = uploadPost('/api/v1/posts', form(), { signal: controller.signal });
        controller.abort();
        await expect(promise).rejects.toMatchObject({ name: 'AbortError' });
        expect(last().aborted).toBe(true);
        await expect(uploadPost('/api/v1/posts', form(), { signal: controller.signal })).rejects.toMatchObject({ name: 'AbortError' });
    });

    it('419 : demande un cookie neuf puis rejoue une seule fois', async () => {
        const fetchMock = vi.fn(async () => new Response(null, { status: 204 }));
        vi.stubGlobal('fetch', fetchMock);
        const promise = uploadPost('/api/v1/posts', form(), {});
        last().respond(419, { message: 'Session expirée' });
        await tick();
        await tick();
        expect(fetchMock).toHaveBeenCalledWith('/sanctum/csrf-cookie', expect.objectContaining({ method: 'GET' }));
        expect(Xhr.instances).toHaveLength(2);
        last().respond(201, { data: { id: 'y' } });
        await expect(promise).resolves.toMatchObject({ status: 201 });
    });

    it('422 : ApiError avec les erreurs par champ', async () => {
        const promise = uploadPost('/api/v1/posts', form(), {});
        last().respond(422, { message: 'Données invalides.', code: 'validation_failed', errors: { 'images.1': ['Illisible'], video_description: ['Trop courte'] } });
        const error = await promise.catch((cause: unknown) => cause) as ApiError;
        expect(error).toBeInstanceOf(ApiError);
        expect(error.status).toBe(422);
        expect(error.errors).toEqual({ 'images.1': ['Illisible'], video_description: ['Trop courte'] });
    });

    it('413 : « vidéo trop lourde » avec la limite annoncée, rattachée au champ video', async () => {
        const promise = uploadPost('/api/v1/posts', form(), { maxMb: 24 });
        last().respond(413, {});
        const error = await promise.catch((cause: unknown) => cause) as ApiError;
        expect(error.status).toBe(413);
        expect(error.errors.video[0]).toBe('Vidéo trop lourde (24 Mo maximum).');
    });

    it('429 : garde la phrase du serveur et le délai', async () => {
        const promise = uploadPost('/api/v1/posts', form(), {});
        last().respond(429, { message: 'Tu as atteint la limite de vidéos envoyées par jour (5). Réessaie un peu plus tard.', code: 'too_many_requests' }, '3412');
        const error = await promise.catch((cause: unknown) => cause) as ApiError;
        expect(error.status).toBe(429);
        expect(error.message).toBe('Tu as atteint la limite de vidéos envoyées par jour (5). Réessaie un peu plus tard.');
        expect(error.retryAfter).toBe(3412);
    });

    it('réseau coupé : ApiError de statut 0 (jamais mise en file pour un média)', async () => {
        const promise = uploadPost('/api/v1/posts', form(), {});
        last().onerror?.();
        const error = await promise.catch((cause: unknown) => cause) as ApiError;
        expect(error.status).toBe(0);
    });
});
