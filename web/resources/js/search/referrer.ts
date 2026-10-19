// Politique de référent de la page /recherche (lot recherche-membre, P8-13, Q84). Avec la politique du site
// (`strict-origin-when-cross-origin`, SecurityHeaders), chaque requête de même origine émise pendant que l'adresse est
// `/recherche?q=…` (avatars, couvertures, sondage de la cloche, clic vers une ressource) recopierait l'adresse complète,
// terme compris, dans l'en-tête Referer, donc dans le champ Referer des journaux d'accès du serveur web. Sur /recherche,
// la politique devient `strict-origin` (l'origine seule : Sanctum reconnaît toujours le domaine de l'espace Vue, ce que
// `no-referrer` empêcherait) : en-tête posé par ForgetSearchTerms au chargement complet, balise `<meta name="referrer">`
// tenue à jour à chaque visite Inertia (entrée sur /recherche dès le départ de la visite, retour à la politique du site
// une fois l'adresse changée). La politique du site revient ailleurs : `back()` de Laravel lit le Referer complet.
import type { router } from '@inertiajs/vue3';

export const SEARCH_REFERRER_POLICY = 'strict-origin';
export const SITE_REFERRER_POLICY = 'strict-origin-when-cross-origin';

/** Chemin de /recherche (`base` : adresse de la route, absolue ou non). */
export function isSearchPath(url: string | URL, base: string, origin = 'http://localhost'): boolean {
    try {
        return new URL(url, origin).pathname.replace(/\/+$/, '') === new URL(base, origin).pathname.replace(/\/+$/, '');
    } catch {
        return false;
    }
}

/** Pose la politique du document par la balise `<meta name="referrer">` (créée au besoin, une seule). */
export function applyReferrerPolicy(policy: string, doc: Document = document): void {
    let meta = doc.head.querySelector<HTMLMetaElement>('meta[name="referrer"]');
    if (!meta) {
        meta = doc.createElement('meta');
        meta.name = 'referrer';
        doc.head.appendChild(meta);
    }
    if (meta.content !== policy) meta.content = policy;
}

type Off = () => void;
type RouterEvents = Pick<typeof router, 'on'>;

/**
 * Suit les visites Inertia de l'espace membre : `strict-origin` dès le départ d'une visite vers /recherche (avant que
 * l'adresse ne change) et tant que l'adresse est celle de /recherche, politique du site sinon. Renvoie le désabonnement.
 */
export function bindSearchReferrer(router: RouterEvents, base: string, win: Pick<Window, 'location' | 'document'> = window): Off {
    const sync = (url: string) => applyReferrerPolicy(isSearchPath(url, base, win.location.origin) ? SEARCH_REFERRER_POLICY : SITE_REFERRER_POLICY, win.document);
    const offStart = router.on('start', (event) => {
        if (isSearchPath(event.detail.visit.url, base, win.location.origin)) applyReferrerPolicy(SEARCH_REFERRER_POLICY, win.document);
    });
    const offNavigate = router.on('navigate', (event) => sync(event.detail.page.url));
    const offFinish = router.on('finish', () => sync(win.location.href));
    sync(win.location.href);
    return () => {
        offStart();
        offNavigate();
        offFinish();
    };
}
