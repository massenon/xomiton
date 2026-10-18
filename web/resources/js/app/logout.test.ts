// @vitest-environment jsdom
// Déconnexion Vue (P6-03) : brouillons du compte effacés et clé d'historique retirée avant l'envoi de POST /deconnexion.
import { describe, expect, it, vi } from 'vitest';

const calls: string[] = [];
vi.mock('@inertiajs/vue3', () => ({
    router: {
        clearHistory: () => calls.push('clearHistory'),
        post: (url: string) => calls.push(`post:${url}`),
    },
}));
vi.mock('@/stores/drafts', () => ({
    useDraftsStore: () => ({ clearAccount: async () => { calls.push('clearAccount'); } }),
}));

describe('logout()', () => {
    it('efface les brouillons, puis l’historique chiffré, puis envoie la déconnexion', async () => {
        const { logout } = await import('./logout');
        await logout();
        expect(calls).toEqual(['clearAccount', 'clearHistory', 'post:/deconnexion']);
    });
});
