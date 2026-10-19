// @vitest-environment jsdom
// Déconnexion Vue (P6-03) : brouillons du compte effacés, clé d'historique retirée et conversation de l'assistant effacée
// de l'onglet (sans charger son module) avant l'envoi de POST /deconnexion.
import { describe, expect, it, vi } from 'vitest';
import { HISTORY_PREFIX, historyKey } from '@/assistant/history';

const calls: string[] = [];
vi.mock('@inertiajs/vue3', () => ({
    router: {
        clearHistory: () => calls.push('clearHistory'),
        post: (url: string) => calls.push(`post:${url}:${sessionStorage.length}`),
    },
}));
vi.mock('@/stores/drafts', () => ({
    useDraftsStore: () => ({ clearAccount: async () => { calls.push('clearAccount'); } }),
}));

describe('logout()', () => {
    it('efface les brouillons, l’historique chiffré et la conversation de l’assistant, puis envoie la déconnexion', async () => {
        sessionStorage.setItem(historyKey(7), '{}');
        sessionStorage.setItem(historyKey(8), '{}');
        sessionStorage.setItem('autre', '1');
        const { logout, ASSISTANT_HISTORY_PREFIX } = await import('./logout');

        await logout();

        expect(ASSISTANT_HISTORY_PREFIX).toBe(HISTORY_PREFIX);
        // Une seule clé restante au moment du POST : celle qui n'appartient pas à l'assistant.
        expect(calls).toEqual(['clearAccount', 'clearHistory', 'post:/deconnexion:1']);
        expect(sessionStorage.getItem('autre')).toBe('1');
    });
});
