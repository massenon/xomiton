// Déconnexion depuis l'espace Vue (P6-03 : menu du compte). Avant l'envoi : brouillons du compte effacés de l'appareil
// (store `drafts`, IndexedDB en clair) et clé de l'historique chiffré retirée (router.clearHistory()) ; le serveur
// (Auth\LogoutController) ferme la session, appelle Inertia::clearHistory() et répond 409 X-Inertia-Location : l'accueil
// est rechargé entièrement. Le service worker efface en parallèle ses pages gardées (LOGOUT_PATHS, sw/worker.js).
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
    router.post(route('deconnexion', undefined, '/deconnexion'));
}
