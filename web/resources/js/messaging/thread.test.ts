// Fil de la messagerie (P8-01) : fusion des messages par id, messages en attente confirmés par client_uuid (deux rejeux
// n'en affichent qu'un), « Vu », séparateurs de jour, filtre local et tri de la liste.
import { describe, expect, it } from 'vitest';
import { applyReadCursor, dayLabel, filterConversations, groupByDay, lastId, mergeMessages, reconcilePending, unreadConversations, upsertConversations } from './thread';
import type { ChatMessage, Conversation } from './types';

const msg = (id: number | null, extra: Partial<ChatMessage> = {}): ChatMessage => ({
    id, client_uuid: `uuid-${id}`, body: `m${id}`, deleted: false, mine: false, author: null, attachment: null, status: null,
    created_at: '2026-10-18T10:00:00+01:00', ...extra,
});

const conv = (id: string, extra: Partial<Conversation> = {}): Conversation => ({
    id, kind: 'private', name: id, title: null, avatar_url: null, other: null, participants: [], participants_count: 2,
    last_message: null, last_message_at: null, unread_count: 0, muted: false, muted_until: null, role: 'member', can_send: true,
    can_manage: false, blocked_by_me: false, others_count: 1, created_at: '2026-10-01T10:00:00+01:00', ...extra,
});

describe('fusion des messages', () => {
    it('dédoublonne par id, garde la version la plus récente et trie du plus ancien au plus récent', () => {
        const merged = mergeMessages([msg(3), msg(1)], [msg(2), msg(3, { body: 'modifié' })]);
        expect(merged.map((m) => m.id)).toEqual([1, 2, 3]);
        expect(merged[2].body).toBe('modifié');
        expect(lastId(merged)).toBe(3);
        expect(lastId([])).toBe(0);
    });

    it('retire les messages en attente que le serveur a rendus (même client_uuid), une seule fois même rejoués', () => {
        const pending = [msg(null, { client_uuid: 'a', state: 'pending', mine: true }), msg(null, { client_uuid: 'b', state: 'pending', mine: true })];
        const server = mergeMessages([], [msg(10, { client_uuid: 'a', mine: true }), msg(10, { client_uuid: 'a', mine: true })]);
        expect(server).toHaveLength(1);
        expect(reconcilePending(pending, server).map((m) => m.client_uuid)).toEqual(['b']);
    });

    it('marque « Vu » ses propres messages jusqu’au curseur de lecture de l’autre personne', () => {
        const marked = applyReadCursor([msg(1, { mine: true }), msg(2, { mine: true }), msg(3)], 1);
        expect(marked.map((m) => m.status)).toEqual(['seen', 'sent', null]);
        expect(applyReadCursor([msg(1, { mine: true })], null)[0].status).toBeNull();
    });
});

describe('jours et liste', () => {
    it('groupe par jour et nomme « Aujourd’hui » / « Hier »', () => {
        const now = new Date('2026-10-18T12:00:00');
        const groups = groupByDay([msg(1, { created_at: '2026-10-17T09:00:00' }), msg(2, { created_at: '2026-10-18T08:00:00' }), msg(3, { created_at: '2026-10-18T09:00:00' })], now);
        expect(groups.map((g) => g.messages.length)).toEqual([1, 2]);
        const labels = { today: 'Aujourd’hui', yesterday: 'Hier' };
        expect(dayLabel('2026-10-18T08:00:00', labels, now)).toBe('Aujourd’hui');
        expect(dayLabel('2026-10-17T08:00:00', labels, now)).toBe('Hier');
        expect(dayLabel('2026-10-10T08:00:00', labels, now)).toContain('octobre');
    });

    it('filtre localement par nom ou pseudonyme et trie par dernière activité', () => {
        const list = upsertConversations([conv('a', { last_message_at: '2026-10-18T09:00:00Z' })], [conv('b', { name: 'Chantal', last_message_at: '2026-10-18T10:00:00Z' })]);
        expect(list.map((c) => c.id)).toEqual(['b', 'a']);
        expect(filterConversations(list, 'chan').map((c) => c.id)).toEqual(['b']);
        expect(filterConversations(list, '  ')).toHaveLength(2);
        expect(unreadConversations([conv('a', { unread_count: 2 }), conv('b')])).toBe(1);
    });
});
