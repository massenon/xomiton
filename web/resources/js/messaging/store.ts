// Store Pinia `messaging` (P8-01) : liste des conversations, fils ouverts, messages en attente d'envoi. Chargé avec la
// page /messages seulement (import() de Pages/Member/Messages.vue, jamais par app.ts). API : docs/API.md §12.
//
// Envoi : message local « en attente » (horloge grisée) avec un `client_uuid` neuf, essai direct si le réseau est là
// (Idempotency-Key = client_uuid) ; réseau coupé, 408, 429 ou 5xx → confié à la boîte d'envoi commune (store `outbox`,
// shared/outbox.js : IndexedDB, rejeu au retour du réseau avec la même clé) et gardé « en attente » jusqu'à ce que le
// serveur rende ce `client_uuid` (sondage court ou fin de rejeu) : deux rejeux ne créent jamais deux messages (unique
// `(conversation_id, client_uuid)` côté serveur, ADR-32) ; refus (403, 404, 422) → « non envoyé », à réessayer ou retirer.
// Après un rechargement, les messages encore dans la boîte d'envoi **du compte de la page** sont réaffichés « en
// attente », leur pièce jointe reprise de l'entrée IndexedDB (sinon « Réessayer » est masqué : jamais d'envoi tronqué).
// Sondage : liste avec l'empreinte du passage précédent (`since` → `meta.unchanged`, réponse vide), fil ouvert avec
// `after` + `since` (`meta.deleted_ids` : suppressions pour tout le monde appliquées sans rechargement).
import { defineStore } from 'pinia';
import { ApiError, type Api } from '@/app/useApi';
import { uuidV4 } from './uuid';
import { applyReadCursor, lastId, mergeMessages, reconcilePending, unreadConversations, upsertConversations } from './thread';
import type { ChatMessage, Conversation, MessagesMeta, MessagingConfig, Participant } from './types';

export const CONVERSATIONS_ENDPOINT = '/api/v1/conversations';
export const OUTBOX_TYPE = 'message';

export interface OutboxLike {
    add: (entry: Record<string, unknown>) => Promise<unknown>;
    entries?: (type?: string) => Promise<Array<Record<string, unknown>>>;
    remove?: (id: string) => Promise<unknown>;
}

interface Thread {
    messages: ChatMessage[];
    pending: ChatMessage[];
    nextCursor: string | null;
    loaded: boolean;
    loading: boolean;
    readUpTo: number | null;
    error: string | null;
    /** `meta.checked_at` du dernier passage (horloge du serveur), renvoyé en `since`. */
    checkedAt: string | null;
}

const emptyThread = (): Thread => ({ messages: [], pending: [], nextCursor: null, loaded: false, loading: false, readUpTo: null, error: null, checkedAt: null });

/** Messages supprimés pour tout le monde rapportés par le sondage : corps et pièce jointe retirés du fil local. */
export function applyDeleted(messages: ChatMessage[], ids: number[] | undefined): ChatMessage[] {
    if (!ids || ids.length === 0) return messages;
    const gone = new Set(ids);
    return messages.map((m) => (m.id !== null && gone.has(m.id) && !m.deleted ? { ...m, deleted: true, body: null, attachment: null } : m));
}

export const messagesUrl = (id: string) => `${CONVERSATIONS_ENDPOINT}/${encodeURIComponent(id)}/messages`;
const URL_PATTERN = /\/api\/v1\/conversations\/([0-9A-Za-z]{26})\/messages$/;

/** Erreur passagère : le message part par la boîte d'envoi (réseau coupé, délai, débit, serveur). */
export const isTransient = (error: unknown): boolean => error instanceof ApiError
    && (error.status === 0 || error.status === 408 || error.status === 425 || error.status === 429 || error.status >= 500);

const online = () => typeof navigator === 'undefined' || navigator.onLine !== false;

/** Fichiers joints des messages encore en attente (hors état réactif : un File n'a rien à faire dans Pinia). */
const pendingFiles = new Map<string, File>();

export const useMessagingStore = defineStore('messaging', {
    state: () => ({
        conversations: [] as Conversation[],
        lastPage: 1,
        activeId: null as string | null,
        threads: {} as Record<string, Thread>,
        search: '',
        filter: 'all' as 'all' | 'unread',
        me: null as Participant | null,
        config: null as MessagingConfig | null,
        listError: null as string | null,
        /** Empreinte de la liste affichée (`meta.fingerprint`) et nombre de passages sans changement (repli à 30 s). */
        listFingerprint: null as string | null,
        listUnchanged: 0,
    }),
    getters: {
        active(state): Conversation | null {
            return state.conversations.find((c) => c.id === state.activeId) ?? null;
        },
        unreadTotal(state): number {
            return unreadConversations(state.conversations);
        },
    },
    actions: {
        init(conversations: Conversation[], config: MessagingConfig | null, lastPage = 1) {
            this.conversations = upsertConversations([], conversations);
            this.config = config;
            this.lastPage = lastPage;
        },
        thread(id: string): Thread {
            if (!this.threads[id]) this.threads[id] = emptyThread();
            return this.threads[id];
        },
        visibleMessages(id: string): ChatMessage[] {
            const thread = this.threads[id];
            if (!thread) return [];
            return [...thread.messages, ...thread.pending];
        },
        upsert(conversation: Conversation) {
            this.conversations = upsertConversations(this.conversations, [conversation]);
        },
        forget(id: string) {
            this.conversations = this.conversations.filter((c) => c.id !== id);
            delete this.threads[id];
            if (this.activeId === id) this.activeId = null;
        },

        /** Rend vrai si la liste a changé (faux : empreinte identique, rien à redessiner). */
        async refreshList(api: Api): Promise<boolean> {
            const query: Record<string, string> = {};
            if (this.search.trim() !== '') query.q = this.search.trim();
            if (this.filter === 'unread') query.filter = 'unread';
            if (this.listFingerprint) query.since = this.listFingerprint;
            try {
                const { data } = await api.get<{ data: Conversation[]; meta?: { last_page?: number; unchanged?: boolean; fingerprint?: string } }>(CONVERSATIONS_ENDPOINT, { query, silent: true });
                this.listFingerprint = typeof data?.meta?.fingerprint === 'string' ? data.meta.fingerprint : null;
                if (data?.meta?.unchanged === true) {
                    this.listUnchanged += 1;
                    this.listError = null;
                    return false;
                }
                this.listUnchanged = 0;
                const items = Array.isArray(data?.data) ? data.data : [];
                // Conversation ouverte gardée même si un filtre la retire (on ne referme pas ce qu'on lit).
                const keep = this.conversations.filter((c) => c.id === this.activeId && !items.some((i) => i.id === c.id));
                this.conversations = upsertConversations(keep, items);
                this.lastPage = Number(data?.meta?.last_page) || 1;
                this.listError = null;
                return true;
            } catch (error) {
                this.listError = error instanceof Error ? error.message : null;
                throw error;
            }
        },

        async loadThread(api: Api, id: string) {
            const thread = this.thread(id);
            thread.loading = true;
            try {
                const { data } = await api.get<{ data: ChatMessage[]; meta?: MessagesMeta }>(messagesUrl(id), { silent: true });
                const items = (Array.isArray(data?.data) ? data.data : []).slice().reverse();
                thread.messages = applyReadCursor(mergeMessages(thread.messages, items), data?.meta?.read_up_to);
                thread.pending = reconcilePending(thread.pending, thread.messages);
                thread.nextCursor = data?.meta?.next_cursor ?? null;
                thread.readUpTo = data?.meta?.read_up_to ?? null;
                thread.checkedAt = data?.meta?.checked_at ?? null;
                thread.loaded = true;
                thread.error = null;
            } catch (error) {
                thread.error = error instanceof Error ? error.message : 'error';
                throw error;
            } finally {
                thread.loading = false;
            }
        },

        async loadOlder(api: Api, id: string) {
            const thread = this.thread(id);
            if (!thread.nextCursor || thread.loading) return;
            thread.loading = true;
            try {
                const { data } = await api.get<{ data: ChatMessage[]; meta?: MessagesMeta }>(messagesUrl(id), { query: { cursor: thread.nextCursor }, silent: true });
                const items = (Array.isArray(data?.data) ? data.data : []).slice().reverse();
                thread.messages = applyReadCursor(mergeMessages(items, thread.messages), thread.readUpTo);
                thread.nextCursor = data?.meta?.next_cursor ?? null;
            } finally {
                thread.loading = false;
            }
        },

        /** Sondage court de la conversation ouverte : nouveaux messages, « Vu », messages en attente confirmés. */
        async pollThread(api: Api, id: string): Promise<ChatMessage[]> {
            const thread = this.thread(id);
            if (!thread.loaded) {
                await this.loadThread(api, id);
                return [];
            }
            const query: Record<string, string | number> = { after: lastId(thread.messages) };
            if (thread.checkedAt) query.since = thread.checkedAt;
            const { data } = await api.get<{ data: ChatMessage[]; meta?: MessagesMeta }>(messagesUrl(id), { query, silent: true });
            const items = Array.isArray(data?.data) ? data.data : [];
            const deleted = Array.isArray(data?.meta?.deleted_ids) ? data.meta.deleted_ids : [];
            thread.readUpTo = data?.meta?.read_up_to ?? thread.readUpTo;
            thread.checkedAt = data?.meta?.checked_at ?? thread.checkedAt;
            thread.messages = applyReadCursor(applyDeleted(mergeMessages(thread.messages, items), deleted), thread.readUpTo);
            const conversation = this.conversations.find((c) => c.id === id);
            if (conversation?.last_message && deleted.includes(conversation.last_message.id)) {
                this.upsert({ ...conversation, last_message: { ...conversation.last_message, deleted: true, preview: null, attachment: false } });
            }
            thread.pending = reconcilePending(thread.pending, thread.messages);
            if (items.length > 0) this.touch(id, items[items.length - 1]);
            return items.filter((m) => !m.mine);
        },

        /** Dernière activité de la conversation après un message (aperçu local, masqué si le serveur le masque). */
        touch(id: string, message: ChatMessage) {
            const conversation = this.conversations.find((c) => c.id === id);
            if (!conversation || message.id === null) return;
            const hidden = conversation.last_message?.preview_hidden ?? false;
            this.upsert({
                ...conversation,
                last_message_at: message.created_at ?? new Date().toISOString(),
                last_message: {
                    id: message.id,
                    preview: hidden ? null : (message.body || null),
                    preview_hidden: hidden,
                    mine: message.mine,
                    author: conversation.kind === 'group' && !message.mine ? message.author?.pseudo ?? null : null,
                    deleted: message.deleted,
                    attachment: message.attachment !== null,
                    at: message.created_at,
                },
            });
        },

        async markRead(api: Api, id: string): Promise<number | null> {
            const conversation = this.conversations.find((c) => c.id === id);
            const { data } = await api.post<{ data: { unread_conversations: number } }>(`${CONVERSATIONS_ENDPOINT}/${encodeURIComponent(id)}/read`, {}, { silent: true });
            if (conversation) this.upsert({ ...conversation, unread_count: 0 });
            const count = Number(data?.data?.unread_conversations);
            return Number.isFinite(count) ? count : null;
        },

        /** Envoie (ou met en file) un message ; rend le message local ou confirmé. */
        async send(api: Api, outbox: OutboxLike, id: string, body: string, file: File | null = null): Promise<ChatMessage> {
            const uuid = uuidV4();
            const local: ChatMessage = {
                id: null, client_uuid: uuid, body, deleted: false, mine: true, author: this.me, attachment: null, status: null,
                created_at: new Date().toISOString(), state: 'pending', error: null, file_name: file?.name ?? null,
            };
            this.thread(id).pending.push(local);
            if (file) pendingFiles.set(uuid, file);
            return this.deliver(api, outbox, id, local, file);
        },

        /**
         * Nouvel essai d'un message « non envoyé », avec le même `client_uuid` (jamais de doublon). Pièce jointe introuvable
         * (rechargement sans Blob dans l'entrée) : rien n'est renvoyé, « Réessayer » est masqué.
         */
        async retry(api: Api, outbox: OutboxLike, id: string, uuid: string): Promise<ChatMessage | null> {
            const local = this.thread(id).pending.find((m) => m.client_uuid === uuid);
            if (!local) return null;
            const file = pendingFiles.get(uuid) ?? null;
            if (local.file_name && !file) {
                this.setPending(id, uuid, { state: 'failed', retryable: false });
                return null;
            }
            return this.deliver(api, outbox, id, local, file);
        },

        async deliver(api: Api, outbox: OutboxLike, id: string, local: ChatMessage, file: File | null = null): Promise<ChatMessage> {
            const uuid = local.client_uuid as string;
            const payload = file ? toForm(local.body ?? '', uuid, file) : { body: local.body ?? '', client_uuid: uuid };
            this.setPending(id, uuid, { state: 'pending', error: null });
            if (!online()) {
                await this.queue(outbox, id, uuid, local.body ?? '', file);
                return local;
            }
            try {
                const { data } = await api.post<{ data: ChatMessage }>(messagesUrl(id), payload, { idempotencyKey: uuid, silent: true });
                this.confirm(id, data.data);
                return data.data;
            } catch (error) {
                if (isTransient(error)) {
                    await this.queue(outbox, id, uuid, local.body ?? '', file);
                    return local;
                }
                this.setPending(id, uuid, { state: 'failed', error: error instanceof Error ? error.message : null });
                throw error;
            }
        },

        async queue(outbox: OutboxLike, id: string, uuid: string, body: string, file: File | null) {
            await outbox.add({
                id: uuid,
                type: OUTBOX_TYPE,
                url: messagesUrl(id),
                idempotencyKey: uuid,
                body: file ? { form: [['body', body], ['client_uuid', uuid], ['attachment', file]] } : { body, client_uuid: uuid },
            });
        },

        setPending(id: string, uuid: string, patch: Partial<ChatMessage>) {
            const thread = this.thread(id);
            thread.pending = thread.pending.map((m) => (m.client_uuid === uuid ? { ...m, ...patch } : m));
        },

        /** Message rendu par le serveur (envoi direct ou rejeu de la boîte d'envoi). */
        confirm(id: string, message: ChatMessage) {
            const thread = this.thread(id);
            thread.messages = applyReadCursor(mergeMessages(thread.messages, [message]), thread.readUpTo);
            thread.pending = reconcilePending(thread.pending, thread.messages);
            if (message.client_uuid) pendingFiles.delete(message.client_uuid);
            this.touch(id, message);
        },

        /** Fin d'un passage de la boîte d'envoi (`xm:outbox:flushed`) : confirme ou signale les messages rejoués. */
        applyFlush(detail: { sent?: Array<{ id: string; type: string; data?: unknown }>; failed?: Array<{ id: string; type: string }> }) {
            const locate = (uuid: string) => Object.entries(this.threads).find(([, t]) => t.pending.some((m) => m.client_uuid === uuid))?.[0];
            (detail.sent ?? []).filter((r) => r.type === OUTBOX_TYPE).forEach((result) => {
                const id = locate(result.id);
                const data = result.data as ChatMessage | undefined;
                if (id && data && typeof data === 'object' && 'id' in data) this.confirm(id, data);
            });
            (detail.failed ?? []).filter((r) => r.type === OUTBOX_TYPE).forEach((result) => {
                const id = locate(result.id);
                if (id) this.setPending(id, result.id, { state: 'failed' });
            });
        },

        /**
         * Messages encore dans la boîte d'envoi (après un rechargement) : réaffichés « en attente », seulement ceux du compte
         * de la page (`account` : empreinte `session.outboxAccount` ; appareil partagé) ; le fichier joint est repris de
         * l'entrée (Blob gardé par IndexedDB) pour qu'un « Réessayer » le renvoie.
         */
        async restorePending(outbox: OutboxLike, account: string | null = null) {
            if (!outbox.entries) return;
            const entries = await outbox.entries(OUTBOX_TYPE).catch(() => [] as Array<Record<string, unknown>>);
            entries.forEach((entry) => {
                if (account !== null && entry.account !== account) return;
                const match = typeof entry.url === 'string' ? URL_PATTERN.exec(entry.url) : null;
                if (!match) return;
                const { body, uuid, fileName, file } = fromEntry(entry);
                if (!uuid) return;
                const thread = this.thread(match[1]);
                if (thread.pending.some((m) => m.client_uuid === uuid) || thread.messages.some((m) => m.client_uuid === uuid)) return;
                if (file) pendingFiles.set(uuid, file);
                const created = Number(entry.createdAt);
                thread.pending.push({
                    id: null, client_uuid: uuid, body, deleted: false, mine: true, author: this.me, attachment: null, status: null,
                    created_at: Number.isFinite(created) && created > 0 ? new Date(created).toISOString() : new Date().toISOString(),
                    state: 'pending', error: null, file_name: fileName, retryable: !fileName || file !== null,
                });
            });
        },

        async discard(outbox: OutboxLike, id: string, uuid: string) {
            const thread = this.thread(id);
            thread.pending = thread.pending.filter((m) => m.client_uuid !== uuid);
            pendingFiles.delete(uuid);
            await outbox.remove?.(uuid);
        },

        async create(api: Api, payload: { kind: 'private'; user_id: number } | { kind: 'group'; title: string; user_ids: number[] }): Promise<Conversation> {
            const { data } = await api.post<{ data: Conversation }>(CONVERSATIONS_ENDPOINT, payload, { silent: true });
            this.upsert(data.data);
            return data.data;
        },

        async act(api: Api, id: string, action: 'block' | 'unblock' | 'mute' | 'unmute', duration?: string): Promise<Conversation> {
            const base = `${CONVERSATIONS_ENDPOINT}/${encodeURIComponent(id)}`;
            const request = {
                block: () => api.post<{ data: Conversation }>(`${base}/block`, {}, { silent: true }),
                unblock: () => api.delete<{ data: Conversation }>(`${base}/block`, undefined, { silent: true }),
                mute: () => api.post<{ data: Conversation }>(`${base}/mute`, { duration: duration ?? 'always' }, { silent: true }),
                unmute: () => api.delete<{ data: Conversation }>(`${base}/mute`, undefined, { silent: true }),
            }[action];
            const { data } = await request();
            this.upsert(data.data);
            return data.data;
        },

        async leave(api: Api, id: string) {
            await api.post(`${CONVERSATIONS_ENDPOINT}/${encodeURIComponent(id)}/leave`, {}, { silent: true });
            this.forget(id);
        },

        async report(api: Api, id: string, reason: string, details: string | null, messageId: number | null = null) {
            await api.post(`${CONVERSATIONS_ENDPOINT}/${encodeURIComponent(id)}/report`, { reason, details, message_id: messageId }, { silent: true });
        },

        async deleteMessage(api: Api, id: string, messageId: number) {
            const { data } = await api.delete<{ data: ChatMessage }>(`${messagesUrl(id)}/${messageId}`, undefined, { silent: true });
            this.confirm(id, data.data);
        },
    },
});

function toForm(body: string, uuid: string, file: File): FormData {
    const form = new FormData();
    form.append('body', body);
    form.append('client_uuid', uuid);
    form.append('attachment', file);
    return form;
}

export function fromEntry(entry: Record<string, unknown>): { body: string; uuid: string | null; fileName: string | null; file: File | null } {
    const raw = entry.body;
    if (typeof raw === 'string') {
        try {
            const parsed = JSON.parse(raw) as { body?: unknown; client_uuid?: unknown };
            return { body: typeof parsed.body === 'string' ? parsed.body : '', uuid: typeof parsed.client_uuid === 'string' ? parsed.client_uuid : null, fileName: null, file: null };
        } catch {
            return { body: '', uuid: null, fileName: null, file: null };
        }
    }
    if (raw && typeof raw === 'object' && !Array.isArray((raw as { form?: unknown }).form)) {
        const plain = raw as { body?: unknown; client_uuid?: unknown };
        return { body: typeof plain.body === 'string' ? plain.body : '', uuid: typeof plain.client_uuid === 'string' ? plain.client_uuid : null, fileName: null, file: null };
    }
    const form = raw && typeof raw === 'object' ? (raw as { form: Array<[string, unknown]> }).form : [];
    const value = (name: string) => form.find(([key]) => key === name)?.[1];
    const attachment = value('attachment') as { name?: string } | undefined;
    const fileName = attachment && typeof attachment.name === 'string' ? attachment.name : null;
    const file = typeof Blob !== 'undefined' && attachment instanceof Blob
        ? (attachment instanceof File ? attachment : new File([attachment], fileName ?? 'piece-jointe', { type: attachment.type }))
        : null;
    return {
        body: typeof value('body') === 'string' ? (value('body') as string) : '',
        uuid: typeof value('client_uuid') === 'string' ? (value('client_uuid') as string) : null,
        fileName,
        file,
    };
}
