// @vitest-environment jsdom
// Page /messages (P8-01) montée dans une vraie application Inertia : liste fournie par le serveur, `?c=` ouvre la
// conversation (GET messages), lecture marquée (POST read) et compteur « Messages » de l'en-tête mis à jour, envoi
// direct avec Idempotency-Key = client_uuid ; `?with=` confirmé avant toute création (personne injoignable : avis
// neutre) ; suppression pour tout le monde confirmée (le sondage court est couvert par messaging/poller.test.ts).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { useNotificationsStore } from '@/stores/notifications';
import { useUiStore } from '@/stores/ui';
import Messages from './Messages.vue';

const ULID = '01JABCDEFGHJKMNPQRSTVWXYZ0';

const conversation = {
    id: ULID, kind: 'private', name: 'Chantal', title: null, avatar_url: null, other: { id: 2, pseudo: 'Chantal', avatar_url: null },
    participants: [], participants_count: 2,
    last_message: { id: 3, preview: 'Parfait, à 15 h', preview_hidden: false, mine: false, author: null, deleted: false, attachment: false, at: '2026-10-18T09:00:00Z' },
    last_message_at: '2026-10-18T09:00:00Z', unread_count: 1, muted: false, muted_until: null, role: 'member', can_send: true,
    can_manage: false, blocked_by_me: false, others_count: 1, created_at: '2026-10-01T10:00:00Z',
};

const config = {
    poll_seconds: 4, list_poll_seconds: 15, body_max_length: 1000, recipient_search_min: 2, group_title_min: 3,
    mute_durations: ['8h', '1w', 'always'], report_reasons: [{ value: 'harcelement', label: 'Harcèlement' }], attachment_accept: 'image/jpeg',
};

/** Laisse finir les transitions Bootstrap de la modale (show / hide) avant le démontage du test. */
const settle = () => new Promise((resolve) => { setTimeout(resolve, 60); });

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

let calls: Array<{ url: string; method: string; headers: Record<string, string>; body: unknown }> = [];

beforeEach(() => {
    calls = [];
    vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
        const url = String(input);
        const method = init.method ?? 'GET';
        calls.push({ url, method, headers: (init.headers ?? {}) as Record<string, string>, body: init.body ? JSON.parse(String(init.body)) : null });
        if (url.includes('/messages') && method === 'GET') {
            return json({ data: [{ id: 3, client_uuid: 'x', body: 'Parfait, à 15 h', deleted: false, mine: false, author: { id: 2, pseudo: 'Chantal', avatar_url: null }, attachment: null, status: null, created_at: '2026-10-18T09:00:00Z' }], meta: { read_up_to: 0, next_cursor: null } });
        }
        if (url.endsWith('/read')) return json({ data: { last_read_message_id: 3, unread_count: 0, unread_conversations: 0 } });
        if (url.endsWith('/api/v1/conversations') && method === 'POST') {
            return json({ data: { ...conversation, id: '01JABCDEFGHJKMNPQRSTVWXYZ9', name: 'Bea', last_message: null, unread_count: 0 } }, 201);
        }
        if (url.endsWith('/messages') && method === 'POST') {
            const body = JSON.parse(String(init.body)) as { body: string; client_uuid: string };
            return json({ data: { id: 4, client_uuid: body.client_uuid, body: body.body, deleted: false, mine: true, author: null, attachment: null, status: 'sent', created_at: new Date().toISOString() } }, 201);
        }
        return json({ data: [conversation], meta: {} });
    }));
});

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
});

describe('page Messages', () => {
    it('ouvre la conversation de ?c=, marque lu, met à jour l’en-tête et envoie avec Idempotency-Key', async () => {
        const { pinia } = await mountInertia(Messages, {
            url: `/messages?c=${ULID}`,
            pageProps: { title: 'Messages', conversations: [conversation], conversationsMeta: { current_page: 1, last_page: 1 }, selected: ULID, with: null, config },
        });
        await flushPromises();

        expect(document.querySelector('[data-xm-chat-name]')?.textContent).toBe('Chantal');
        expect(document.querySelector('[data-xm-messages]')?.textContent).toContain('Parfait, à 15 h');
        expect(calls.some((c) => c.url.endsWith(`/conversations/${ULID}/read`) && c.method === 'POST')).toBe(true);
        expect(useNotificationsStore(pinia).messages).toBe(0);
        expect(document.querySelector('.xm-chat')?.classList.contains('show-pane')).toBe(true);

        const input = document.getElementById('chatInput') as HTMLTextAreaElement;
        input.value = 'Je viendrai';
        input.dispatchEvent(new Event('input'));
        input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        await flushPromises();

        const post = calls.find((c) => c.method === 'POST' && c.url.endsWith(`/conversations/${ULID}/messages`));
        expect(post?.body).toMatchObject({ body: 'Je viendrai' });
        expect(post?.headers['Idempotency-Key']).toBe((post?.body as { client_uuid: string }).client_uuid);
        expect(document.querySelector('[data-xm-messages]')?.textContent).toContain('Je viendrai');
        expect(document.body.textContent?.toLowerCase()).not.toContain('bout en bout');
    });

    it('?with= : demande confirmation avant de créer la conversation (un lien seul ne contacte personne)', async () => {
        await mountInertia(Messages, {
            url: '/messages?with=7',
            pageProps: { title: 'Messages', conversations: [], conversationsMeta: { current_page: 1, last_page: 1 }, selected: null, with: { id: 7, pseudo: 'Bea', avatar_url: null }, withUnavailable: false, config },
        });
        await flushPromises();
        await settle();

        expect(calls.some((c) => c.method === 'POST' && c.url.endsWith('/api/v1/conversations'))).toBe(false);
        expect(document.body.textContent).toContain('Écrire à Bea ?'.replace(' ?', '\u00a0?'));
        const start = [...document.querySelectorAll('button')].find((b) => b.textContent?.trim() === 'Écrire à Bea') as HTMLButtonElement;
        expect(start).toBeTruthy();
        start.click();
        await flushPromises();

        const post = calls.find((c) => c.method === 'POST' && c.url.endsWith('/api/v1/conversations'));
        expect(post?.body).toEqual({ kind: 'private', user_id: 7 });
        expect(document.querySelector('[data-xm-chat-name]')?.textContent).toBe('Bea');
        await settle();
    });

    it('?with= vers une personne injoignable : aucune création, avis neutre', async () => {
        const { pinia } = await mountInertia(Messages, {
            url: '/messages?with=9',
            pageProps: { title: 'Messages', conversations: [], conversationsMeta: { current_page: 1, last_page: 1 }, selected: null, with: null, withUnavailable: true, config },
        });
        await flushPromises();

        expect(calls.some((c) => c.method === 'POST')).toBe(false);
        expect(useUiStore(pinia).toasts.map((toast) => toast.message)).toContain('Cette personne ne peut pas recevoir de message pour le moment.');
    });

    it('supprime son message pour tout le monde après confirmation (DELETE) et l’affiche « Message supprimé »', async () => {
        const mine = { id: 5, client_uuid: 'm', body: 'À retirer', deleted: false, mine: true, author: null, attachment: null, status: 'sent', created_at: '2026-10-18T09:05:00Z' };
        vi.stubGlobal('fetch', vi.fn(async (input: RequestInfo | URL, init: RequestInit = {}) => {
            const url = String(input);
            const method = init.method ?? 'GET';
            calls.push({ url, method, headers: (init.headers ?? {}) as Record<string, string>, body: null });
            if (method === 'DELETE') return json({ data: { ...mine, body: null, deleted: true } });
            if (url.includes('/messages')) return json({ data: [mine], meta: { read_up_to: 0, next_cursor: null } });
            if (url.endsWith('/read')) return json({ data: { last_read_message_id: 5, unread_count: 0, unread_conversations: 0 } });
            return json({ data: [conversation], meta: {} });
        }));
        await mountInertia(Messages, {
            url: `/messages?c=${ULID}`,
            pageProps: { title: 'Messages', conversations: [conversation], conversationsMeta: { current_page: 1, last_page: 1 }, selected: ULID, with: null, config },
        });
        await flushPromises();

        const link = [...document.querySelectorAll('[data-xm-messages] button')].find((b) => b.textContent?.includes('Supprimer pour tout le monde')) as HTMLButtonElement;
        link.click();
        await flushPromises();
        await settle();
        const confirmButton = [...document.querySelectorAll('.modal button')].find((b) => b.textContent?.trim() === 'Supprimer pour tout le monde') as HTMLButtonElement;
        confirmButton.click();
        await flushPromises();

        expect(calls.some((c) => c.method === 'DELETE' && c.url.endsWith(`/conversations/${ULID}/messages/5`))).toBe(true);
        expect(document.querySelector('[data-xm-messages]')?.textContent).toContain('Message supprimé');
        expect(document.querySelector('[data-xm-messages]')?.textContent).not.toContain('À retirer');
        await settle();
    });
});
