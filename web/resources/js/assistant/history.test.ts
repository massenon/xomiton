// @vitest-environment jsdom
// Historique de l'assistant (P8-11) : sessionStorage par compte, lecture tolérante, effacement d'un compte ou de tous
// (déconnexion, app/logout.ts), stockage refusé sans erreur.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { forgetHistory, forgetOtherHistories, historyKey, loadHistory, saveHistory } from './history';

vi.mock('@inertiajs/vue3', () => ({ router: { clearHistory: () => undefined, post: () => undefined } }));
vi.mock('@/stores/drafts', () => ({ useDraftsStore: () => ({ clearAccount: async () => undefined }) }));

afterEach(() => {
    sessionStorage.clear();
    vi.restoreAllMocks();
});

const chat = { messages: [{ id: 1, who: 'me' as const, text: 'Bonjour' }], conversationId: 12, persist: true };

describe('historique de l’assistant', () => {
    it('garde la conversation par compte et la relit', () => {
        saveHistory(historyKey(7), chat);

        expect(loadHistory(historyKey(7))).toEqual(chat);
        expect(loadHistory(historyKey(8))).toBeNull();
        expect(historyKey(7)).toBe('xm_assistant_v1:7');
    });

    it('ignore un contenu illisible ou des messages mal formés', () => {
        sessionStorage.setItem(historyKey(7), '{pas du json');
        expect(loadHistory(historyKey(7))).toBeNull();

        sessionStorage.setItem(historyKey(7), JSON.stringify({ messages: [{ id: 1, who: 'pirate', text: 'x' }, { id: 2, who: 'bot', text: 'ok' }], conversationId: 'x' }));
        expect(loadHistory(historyKey(7))).toEqual({ messages: [{ id: 2, who: 'bot', text: 'ok' }], conversationId: null, persist: false });
    });

    it('efface un compte ou tous les comptes de l’onglet, sans toucher au reste', () => {
        saveHistory(historyKey(7), chat);
        saveHistory(historyKey(8), chat);
        sessionStorage.setItem('autre', '1');

        forgetHistory(historyKey(7));
        expect(sessionStorage.getItem(historyKey(7))).toBeNull();
        expect(sessionStorage.getItem(historyKey(8))).not.toBeNull();

        forgetHistory();
        expect(sessionStorage.getItem(historyKey(8))).toBeNull();
        expect(sessionStorage.getItem('autre')).toBe('1');
    });

    it('efface les historiques des autres comptes, garde celui du compte et le reste de l’onglet', () => {
        saveHistory(historyKey(7), chat);
        saveHistory(historyKey(8), chat);
        saveHistory(historyKey(null), chat);
        sessionStorage.setItem('autre', '1');

        forgetOtherHistories(historyKey(7));

        expect(sessionStorage.getItem(historyKey(7))).not.toBeNull();
        expect(sessionStorage.getItem(historyKey(8))).toBeNull();
        expect(sessionStorage.getItem(historyKey(null))).toBeNull();
        expect(sessionStorage.getItem('autre')).toBe('1');
    });

    it('ne lève rien quand le stockage est refusé', () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('QuotaExceededError'); });
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('SecurityError'); });

        expect(() => saveHistory(historyKey(7), chat)).not.toThrow();
        expect(loadHistory(historyKey(7))).toBeNull();
    });

    it('est effacé à la déconnexion', async () => {
        saveHistory(historyKey(7), chat);
        const { logout } = await import('@/app/logout');

        await logout();

        expect(sessionStorage.getItem(historyKey(7))).toBeNull();
    });
});
