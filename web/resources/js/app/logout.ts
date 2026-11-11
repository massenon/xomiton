// Déconnexion depuis l'espace Vue (P6-03 : menu du compte). Avant l'envoi : brouillons du compte effacés de l'appareil
// (store `drafts`, IndexedDB en clair), clé de l'historique chiffré retirée (router.clearHistory()) et conversation de
// l'assistant effacée de l'onglet (sessionStorage `xm_assistant_v1*`) ; le serveur (Auth\LogoutController) ferme la
// session, appelle Inertia::clearHistory() et répond 409 X-Inertia-Location : l'accueil est rechargé entièrement. Le
// service worker efface en parallèle ses pages gardées (LOGOUT_PATHS, sw/worker.js).
import { router } from '@inertiajs/vue3';
import { route } from './routes';
import { useDraftsStore } from '@/stores/drafts';

export async function logout(): Promise<void> {
    try {
        await useDraftsStore().clearAccount();
    } catch {
        // Base indisponible : l'expiration à `draft_days` jours reste la seule purge.
    }
    try {
        router.clearHistory();
    } catch {
        // Historique non chiffré (contexte non sécurisé) : rien à effacer.
    }
    forgetAssistantHistory();
    router.post(route('deconnexion', undefined, '/deconnexion'));
}

/** Préfixe des clés de la conversation de l'assistant (HISTORY_PREFIX d'assistant/history.ts, vérifié par logout.test.ts). */
export const ASSISTANT_HISTORY_PREFIX = 'xm_assistant_v1';

/**
 * Conversation de l'assistant gardée dans l'onglet (P8-11), tous comptes : effacée ici sans charger le module de la page
 * (ni téléchargement de plus sur un réseau lent, ni échec d'import qui la laisserait dans l'onglet).
 */
function forgetAssistantHistory(): void {
    try {
        const store = window.sessionStorage;
        const keys: string[] = [];
        for (let i = 0; i < store.length; i += 1) {
            const name = store.key(i);
            if (name && name.startsWith(ASSISTANT_HISTORY_PREFIX)) keys.push(name);
        }
        keys.forEach((name) => store.removeItem(name));
    } catch {
        // Stockage indisponible : rien n'a pu y être gardé.
    }
}
