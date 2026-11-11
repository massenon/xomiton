// @vitest-environment jsdom
// Store drafts (P6-05) : brouillons IndexedDB par compte (base de /signaler, clés vue:<compte>:<nom>), durée de vie,
// purge et effacement à la déconnexion, sans toucher au brouillon de /signaler.
import 'fake-indexeddb/auto';
import { openDB } from 'idb';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { freshPinia } from '@/test/helpers';
import { DRAFT_DB, DRAFT_STORE, sanitizeDraft, setDraftOpener, useDraftsStore } from './drafts';

const DAY = 86_400_000;
let dbName = '';

beforeEach(() => {
    freshPinia();
    dbName = `${DRAFT_DB}-${Math.random().toString(36).slice(2)}`;
    setDraftOpener(() => openDB(dbName, 1, { upgrade(db) { db.createObjectStore(DRAFT_STORE); } }));
});
afterEach(() => setDraftOpener(null));

describe('store drafts', () => {
    it('enregistre et relit un brouillon du compte, jamais celui d’un autre compte', async () => {
        const drafts = useDraftsStore();
        drafts.configure('acct-a', 7);
        await drafts.save('post', { body: 'Bonjour', tags: ['a'] });
        expect(await drafts.load('post')).toEqual({ body: 'Bonjour', tags: ['a'] });
        drafts.configure('acct-b', 7);
        expect(await drafts.load('post')).toBeNull();
    });

    it('expire après ttlDays ; purge les brouillons Vue expirés sans toucher à /signaler', async () => {
        const drafts = useDraftsStore();
        drafts.configure('acct', 2);
        const now = Date.now();
        await drafts.save('old', 'x', now - 3 * DAY);
        await drafts.save('fresh', 'y', now);
        const db = await openDB(dbName, 1);
        await db.put(DRAFT_STORE, { story: 'brouillon du signalement' }, 'signaler');
        expect(await drafts.purge(now)).toBe(1);
        expect(await drafts.load('fresh', now)).toBe('y');
        expect(await db.get(DRAFT_STORE, 'signaler')).toEqual({ story: 'brouillon du signalement' });
        await drafts.save('stale', 'z', now - 3 * DAY);
        expect(await drafts.load('stale', now)).toBeNull();
        expect(await db.get(DRAFT_STORE, 'vue:acct:stale')).toBeUndefined();
    });

    it('un brouillon peut avoir sa propre durée de vie, plus courte que celle du store, jamais plus longue', async () => {
        const drafts = useDraftsStore();
        drafts.configure('acct', 7);
        const now = Date.now();
        await drafts.save('collecte', { kind: 'VBG' }, now - 2 * DAY, [], 1);
        await drafts.save('long', 'a', now - 2 * DAY, [], 30);
        await drafts.save('plain', 'b', now - 2 * DAY);
        expect(await drafts.purge(now)).toBe(1);
        expect(await drafts.load('collecte', now)).toBeNull();
        expect(await drafts.load('long', now)).toBe('a');
        expect(await drafts.load('plain', now)).toBe('b');
        await drafts.save('too-long', 'c', now - 8 * DAY, [], 30);
        expect(await drafts.load('too-long', now)).toBeNull();
    });

    it('n’enregistre ni secret, ni coordonnées, ni position, ni preuve, ni fichier, à toute profondeur', async () => {
        const drafts = useDraftsStore();
        drafts.configure('acct', 7);
        const file = new Blob(['x'], { type: 'image/png' });
        await drafts.save('report', {
            body: 'Texte', password: 'secret', contact: '+229 01 00 00 00', email: 'a@b.bj',
            place: { label: 'Cotonou', latitude: 6.36, longitude: 2.42 }, evidence: [file], cover: file,
            tags: ['email', 'contact'], note: 'gardée',
        }, Date.now(), ['note']);
        expect(await drafts.load('report')).toEqual({ body: 'Texte', place: { label: 'Cotonou' }, tags: ['email', 'contact'] });
        expect(sanitizeDraft('simple')).toBe('simple');
        expect(sanitizeDraft(undefined)).toBeNull();
    });

    it('clearAccount efface les brouillons du compte seulement', async () => {
        const drafts = useDraftsStore();
        drafts.configure('a', 7);
        await drafts.save('post', 1);
        await drafts.save('comment', 2);
        drafts.configure('b', 7);
        await drafts.save('post', 3);
        drafts.configure('a', 7);
        await drafts.clearAccount();
        expect(await drafts.load('post')).toBeNull();
        drafts.configure('b', 7);
        expect(await drafts.load('post')).toBe(3);
        await drafts.remove('post');
        expect(await drafts.load('post')).toBeNull();
    });
});
