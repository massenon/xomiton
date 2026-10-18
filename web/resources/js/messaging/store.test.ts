// @vitest-environment jsdom
// Store `messaging` (P8-01) : envoi direct, réseau coupé → boîte d'envoi (même client_uuid en Idempotency-Key), hors
// connexion → file directe, refus → « non envoyé », sondage qui confirme les messages en attente sans doublon,
// réaffichage des messages de la boîte d'envoi après un rechargement, fin de rejeu.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ApiError, type Api } from '@/app/useApi';
import { freshPinia } from '@/test/helpers';
import { messagesUrl, useMessagingStore } from './store';
import type { ChatMessage, Conversation } from './types';

const ULID = '01JABCDEFGHJKMNPQRSTVWXYZ0';

const conversation = (extra: Partial<Conversation> = {}): Conversation => ({
    id: ULID, kind: 'private', name: 'Chantal', title: null, avatar_url: null, other: null, participants: [], participants_count: 2,
    last_message: null, last_message_at: '2026-10-18T09:00:00Z', unread_count: 1, muted: false, muted_until: null, role: 'member',
    can_send: true, can_manage: false, blocked_by_me: false, others_count: 1, created_at: '2026-10-01T10:00:00Z', ...extra,
});

const server = (id: number, uuid: string, extra: Partial<ChatMessage> = {}): ChatMessage => ({
    id, client_uuid: uuid, body: 'Bonjour', deleted: false, mine: true, author: null, attachment: null, status: 'sent',
    created_at: '2026-10-18T10:00:00Z', ...extra,
});

const fakeApi = (handlers: Partial<Record<'get' | 'post' | 'delete', (...args: unknown[]) => Promise<unknown>>> = {}) => ({
    get: vi.fn(handlers.get ?? (async () => ({ status: 200, data: { data: [], meta: {} }, headers: new Headers() }))),
    post: vi.fn(handlers.post ?? (async () => ({ status: 200, data: { data: {} }, headers: new Headers() }))),
    delete: vi.fn(handlers.delete ?? (async () => ({ status: 200, data: { data: {} }, headers: new Headers() }))),
}) as unknown as Api & Record<'get' | 'post' | 'delete', ReturnType<typeof vi.fn>>;

const fakeOutbox = (entries: Array<Record<string, unknown>> = []) => ({
    add: vi.fn(async () => undefined),
    entries: vi.fn(async () => entries),
    remove: vi.fn(async () => 0),
});

const setOnline = (value: boolean) => Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => value });

beforeEach(() => {
    freshPinia();
    setOnline(true);
});
afterEach(() => setOnline(true));

describe('envoi', () => {
    it('envoie directement avec client_uuid = Idempotency-Key et remplace le message en attente', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        let sentUuid = '';
        const api = fakeApi({
            post: async (_url, body, options) => {
                sentUuid = (body as { client_uuid: string }).client_uuid;
                expect((options as { idempotencyKey: string }).idempotencyKey).toBe(sentUuid);
                return { status: 201, data: { data: server(42, sentUuid) }, headers: new Headers() };
            },
        });
        const outbox = fakeOutbox();

        const result = await store.send(api, outbox, ULID, 'Bonjour');

        expect(api.post).toHaveBeenCalledWith(messagesUrl(ULID), { body: 'Bonjour', client_uuid: sentUuid }, expect.anything());
        expect(result.id).toBe(42);
        expect(store.visibleMessages(ULID).map((m) => m.id)).toEqual([42]);
        expect(store.thread(ULID).pending).toHaveLength(0);
        expect(outbox.add).not.toHaveBeenCalled();
        expect(store.conversations[0].last_message?.preview).toBe('Bonjour');
    });

    it('réseau coupé pendant l’envoi : confié à la boîte d’envoi, gardé « en attente » (horloge grisée)', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const api = fakeApi({ post: async () => { throw new ApiError(0, 'network', 'Hors ligne'); } });
        const outbox = fakeOutbox();

        const result = await store.send(api, outbox, ULID, 'Hors ligne');

        expect(result.state).toBe('pending');
        expect(outbox.add).toHaveBeenCalledWith(expect.objectContaining({
            id: result.client_uuid, type: 'message', url: messagesUrl(ULID), idempotencyKey: result.client_uuid,
            body: { body: 'Hors ligne', client_uuid: result.client_uuid },
        }));
        expect(store.visibleMessages(ULID)).toHaveLength(1);
    });

    it('hors connexion : file directe, sans essai réseau', async () => {
        setOnline(false);
        const store = useMessagingStore();
        store.init([conversation()], null);
        const api = fakeApi();
        const outbox = fakeOutbox();

        await store.send(api, outbox, ULID, 'Plus tard');

        expect(api.post).not.toHaveBeenCalled();
        expect(outbox.add).toHaveBeenCalledTimes(1);
    });

    it('refus (403) : « non envoyé » avec le message du serveur, réessai avec le même client_uuid', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const uuids: string[] = [];
        let refuse = true;
        const api = fakeApi({
            post: async (_url, body) => {
                uuids.push((body as { client_uuid: string }).client_uuid);
                if (refuse) throw new ApiError(403, 'conversation_unavailable', 'Cette conversation n’est plus disponible.');
                return { status: 201, data: { data: server(7, uuids[0]) }, headers: new Headers() };
            },
        });
        const outbox = fakeOutbox();

        await expect(store.send(api, outbox, ULID, 'Bonjour')).rejects.toBeInstanceOf(ApiError);
        expect(store.thread(ULID).pending[0]).toMatchObject({ state: 'failed', error: 'Cette conversation n’est plus disponible.' });

        refuse = false;
        await store.retry(api, outbox, ULID, uuids[0]);
        expect(uuids[1]).toBe(uuids[0]);
        expect(store.visibleMessages(ULID).map((m) => m.id)).toEqual([7]);
    });
});

describe('sondage et boîte d’envoi', () => {
    it('confirme par le sondage un message rejoué deux fois : un seul message affiché', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const outbox = fakeOutbox();
        const offline = fakeApi({ post: async () => { throw new ApiError(503, 'maintenance', 'Maintenance'); } });
        const local = await store.send(offline, outbox, ULID, 'Bonjour');
        store.thread(ULID).loaded = true;
        const uuid = local.client_uuid as string;
        const api = fakeApi({
            get: async (_url, options) => {
                expect((options as { query: { after: number } }).query.after).toBe(0);
                return { status: 200, data: { data: [server(5, uuid), server(5, uuid)], meta: { read_up_to: 5 } }, headers: new Headers() };
            },
        });

        await store.pollThread(api, ULID);

        const shown = store.visibleMessages(ULID);
        expect(shown).toHaveLength(1);
        expect(shown[0]).toMatchObject({ id: 5, status: 'seen' });
    });

    it('réaffiche les messages restés dans la boîte d’envoi après un rechargement, puis les confirme à la fin du rejeu', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const outbox = fakeOutbox([
            { id: 'u-1', type: 'message', url: messagesUrl(ULID), body: JSON.stringify({ body: 'Écrit hors ligne', client_uuid: 'u-1' }), createdAt: Date.parse('2026-10-18T08:00:00Z') },
            { id: 'u-2', type: 'message', url: '/api/v1/autre', body: '{}' },
        ]);

        await store.restorePending(outbox);
        expect(store.thread(ULID).pending).toHaveLength(1);
        expect(store.thread(ULID).pending[0]).toMatchObject({ client_uuid: 'u-1', body: 'Écrit hors ligne', state: 'pending' });

        store.applyFlush({ sent: [{ id: 'u-1', type: 'message', data: server(9, 'u-1', { body: 'Écrit hors ligne' }) }], failed: [] });
        expect(store.thread(ULID).pending).toHaveLength(0);
        expect(store.visibleMessages(ULID).map((m) => m.id)).toEqual([9]);
    });

    it('retire un message abandonné de l’écran et de la boîte d’envoi', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const outbox = fakeOutbox();
        const local = await store.send(fakeApi({ post: async () => { throw new ApiError(0, 'network', ''); } }), outbox, ULID, 'x');

        await store.discard(outbox, ULID, local.client_uuid as string);

        expect(store.visibleMessages(ULID)).toHaveLength(0);
        expect(outbox.remove).toHaveBeenCalledWith(local.client_uuid);
    });

    it('marque lu : non lus remis à zéro et compteur de l’en-tête rendu par le serveur', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const api = fakeApi({ post: async () => ({ status: 200, data: { data: { unread_conversations: 0 } }, headers: new Headers() }) });

        await expect(store.markRead(api, ULID)).resolves.toBe(0);
        expect(store.conversations[0].unread_count).toBe(0);
        expect(store.unreadTotal).toBe(0);
    });
});

describe('revue messagerie : suppressions, liste allégée, boîte d’envoi par compte', () => {
    it('applique deleted_ids du sondage au fil ouvert et à l’aperçu, et renvoie checked_at en since', async () => {
        const store = useMessagingStore();
        store.init([conversation({ last_message: { id: 4, preview: 'Oups', preview_hidden: false, mine: false, author: null, deleted: false, attachment: false, at: null } })], null);
        const thread = store.thread(ULID);
        thread.loaded = true;
        thread.checkedAt = '2026-10-18T10:00:00+00:00';
        thread.messages = [server(3, 'a', { mine: false, body: 'Garde' }), server(4, 'b', { mine: false, body: 'Oups' })];
        const api = fakeApi({
            get: async (_url, options) => {
                expect((options as { query: Record<string, unknown> }).query).toEqual({ after: 4, since: '2026-10-18T10:00:00+00:00' });
                return { status: 200, data: { data: [], meta: { deleted_ids: [4], checked_at: '2026-10-18T10:00:04+00:00' } }, headers: new Headers() };
            },
        });

        await store.pollThread(api, ULID);

        expect(store.visibleMessages(ULID).map((m) => [m.id, m.deleted, m.body])).toEqual([[3, false, 'Garde'], [4, true, null]]);
        expect(thread.checkedAt).toBe('2026-10-18T10:00:04+00:00');
        expect(store.conversations[0].last_message).toMatchObject({ deleted: true, preview: null });
    });

    it('envoie l’empreinte de la liste ; réponse inchangée → liste gardée et passages comptés pour le repli', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const queries: Array<Record<string, string>> = [];
        let unchanged = false;
        const api = fakeApi({
            get: async (_url, options) => {
                queries.push({ ...(options as { query: Record<string, string> }).query });
                return { status: 200, data: { data: unchanged ? [] : [conversation({ name: 'Chantal M.' })], meta: { fingerprint: 'f1', unchanged } }, headers: new Headers() };
            },
        });

        await expect(store.refreshList(api)).resolves.toBe(true);
        unchanged = true;
        await expect(store.refreshList(api)).resolves.toBe(false);
        await store.refreshList(api);

        expect(queries[0].since).toBeUndefined();
        expect(queries[1].since).toBe('f1');
        expect(store.conversations.map((c) => c.name)).toEqual(['Chantal M.']);
        expect(store.listUnchanged).toBe(2);
    });

    it('ne réaffiche que les envois en attente du compte de la page et reprend leur pièce jointe pour « Réessayer »', async () => {
        const store = useMessagingStore();
        store.init([conversation()], null);
        const photo = new File(['jpeg'], 'photo.jpg', { type: 'image/jpeg' });
        const outbox = fakeOutbox([
            { id: 'u-1', type: 'message', account: 'moi', url: messagesUrl(ULID), body: { form: [['body', 'Avec photo'], ['client_uuid', 'u-1'], ['attachment', photo]] } },
            { id: 'u-2', type: 'message', account: 'autre', url: messagesUrl(ULID), body: JSON.stringify({ body: 'Pas à moi', client_uuid: 'u-2' }) },
            { id: 'u-3', type: 'message', account: 'moi', url: messagesUrl(ULID), body: { form: [['body', 'Fichier perdu'], ['client_uuid', 'u-3'], ['attachment', { name: 'perdu.pdf' }]] } },
        ]);

        await store.restorePending(outbox, 'moi');

        const pending = store.thread(ULID).pending;
        expect(pending.map((m) => m.client_uuid)).toEqual(['u-1', 'u-3']);
        expect(pending[0]).toMatchObject({ file_name: 'photo.jpg', retryable: true });
        expect(pending[1]).toMatchObject({ file_name: 'perdu.pdf', retryable: false });

        const sent: unknown[] = [];
        const api = fakeApi({ post: async (_url, body) => { sent.push(body); return { status: 201, data: { data: server(11, 'u-1') }, headers: new Headers() }; } });
        await store.retry(api, outbox, ULID, 'u-1');
        expect(sent[0]).toBeInstanceOf(FormData);
        expect((sent[0] as FormData).get('attachment')).toBeInstanceOf(File);

        await expect(store.retry(api, outbox, ULID, 'u-3')).resolves.toBeNull();
        expect(sent).toHaveLength(1); // jamais d'envoi tronqué sans la pièce jointe
        expect(store.thread(ULID).pending.find((m) => m.client_uuid === 'u-3')).toMatchObject({ state: 'failed', retryable: false });
    });
});
