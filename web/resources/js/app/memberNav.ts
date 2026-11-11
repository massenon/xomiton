// Liens du gabarit membre Vue (P6-03) : une seule source pour l'en-tête (prototype partials/header.php l.40-73) et le
// sidenav (partials/sidenav.php). Chaque lien : nom de route Laravel (Ziggy, groupe « member » ; repli sur le chemin),
// icône Bootstrap Icons, clé de texte (lang/fr/menus.php), `inertia` (page de l'espace membre : <Link>, visite Inertia ;
// sinon <a> vers une page Blade publique ou de crise, chargée entièrement), `danger` (SOS), `ability` (permission
// lue dans les props partagées `can`, la décision restant au serveur). Lien actif : même chemin ou sous-chemin
// (/fil actif sur /fil/12), signalé par la classe `active` et `aria-current="page"`.
import { route } from './routes';

/** Cadence plancher de la cloche (`ui.bell_poll_seconds`) : 15 à 30 s (D7), 10 s étant réservé au tableau de garde. */
export const BELL_MIN_SECONDS = 15;

export interface NavLink {
    key: string;
    route: string;
    path: string;
    icon: string;
    label: string;
    inertia?: boolean;
    danger?: boolean;
    ability?: string;
    hash?: string;
}

export type MenuEntry = NavLink | 'divider';

export interface HeaderMenu {
    id: string;
    label: string;
    entries: MenuEntry[];
}

export interface NavSection {
    id: string;
    title: string;
    links: NavLink[];
    ability?: string;
}

const link = (key: string, name: string, path: string, icon: string, label: string, extra: Partial<NavLink> = {}): NavLink =>
    ({ key, route: name, path, icon, label, ...extra });

const member = { inertia: true };

export const LINKS = {
    fil: link('fil', 'fil', '/fil', 'house-heart', 'menus.member_more.feed_full', member),
    profil: link('profil', 'profil', '/profil', 'person-circle', 'menus.member.profile', member),
    messages: link('messages', 'messages', '/messages', 'chat-dots', 'menus.member_more.messages_full', member),
    groupes: link('groupes', 'groupes.index', '/groupes', 'people', 'menus.member.groups', member),
    pages: link('pages', 'pages.index', '/pages', 'flag', 'menus.member.pages', member),
    evenements: link('evenements', 'evenements.index', '/evenements', 'calendar-event', 'menus.member.events', member),
    forums: link('forums', 'forums.index', '/forums', 'chat-square-text', 'menus.member.forums', member),
    quiSuivre: link('qui-suivre', 'qui-suivre', '/qui-suivre', 'person-plus', 'menus.member.who_to_follow', member),
    thematiques: link('thematiques', 'thematiques.index', '/thematiques', 'grid', 'menus.member.thematiques'),
    ressources: link('ressources', 'ressources.index', '/ressources', 'journal-text', 'menus.member.ressources'),
    doGlegbe: link('do-glegbe', 'do-glegbe', '/do-glegbe', 'droplet-half', 'menus.member_more.do_glegbe'),
    violences: link('violences', 'violences', '/violences', 'shield-exclamation', 'menus.member.violences'),
    lois: link('lois', 'lois', '/lois', 'bank', 'menus.member.lois'),
    ccc: link('ccc', 'ccc', '/ccc', 'collection-play', 'menus.member.ccc', member),
    sos: link('sos', 'sos', '/sos', 'broadcast', 'menus.member.sos', { danger: true }),
    signaler: link('signaler', 'signaler', '/signaler', 'megaphone', 'menus.member.report'),
    urgences: link('urgences', 'urgences', '/urgences', 'telephone-fill', 'menus.member.emergency'),
    annuaire: link('annuaire', 'annuaire', '/annuaire', 'person-lines-fill', 'menus.member.directory', member),
    assistant: link('assistant', 'assistant', '/assistant', 'robot', 'menus.member.assistant', member),
    espacePair: link('espace-pair', 'espace-pair', '/espace-pair', 'clipboard2-pulse', 'menus.member.peer_space', { ...member, ability: 'peer_space.access' }),
    collecte: link('collecte', 'collecte', '/collecte', 'pencil-square', 'menus.member_more.collecte', { ...member, ability: 'peer_space.access' }),
    parametres: link('parametres', 'parametres', '/parametres', 'gear', 'menus.member.settings', member),
    aide: link('aide', 'aide.index', '/aide', 'life-preserver', 'menus.member.help_center'),
    notifications: link('notifications', 'notifications', '/notifications', 'bell', 'menus.member.notifications', member),
} satisfies Record<string, NavLink>;

/** Menus déroulants de l'en-tête, entre « Fil » et « Espace pair » (partials/header.php). */
export const HEADER_MENUS: HeaderMenu[] = [
    { id: 'menuApprendre', label: 'menus.member.learn', entries: [LINKS.thematiques, LINKS.ressources, LINKS.violences, LINKS.lois, 'divider', LINKS.ccc] },
    { id: 'menuCommunaute', label: 'menus.member.community', entries: [LINKS.groupes, LINKS.pages, LINKS.evenements, LINKS.forums, LINKS.quiSuivre] },
    { id: 'menuAide', label: 'menus.member.help_protection', entries: [LINKS.sos, LINKS.signaler, LINKS.urgences, LINKS.annuaire, LINKS.assistant] },
];

/** Sections du sidenav (partials/sidenav.php) ; le compte (paramètres, aide, administration, déconnexion) suit. */
export const SIDENAV_SECTIONS: NavSection[] = [
    { id: 'social', title: 'menus.member_sections.social', links: [LINKS.fil, LINKS.profil, LINKS.messages, LINKS.groupes, LINKS.pages, LINKS.evenements, LINKS.forums, { ...LINKS.quiSuivre, label: 'menus.member_more.who_to_follow_full' }] },
    { id: 'learn', title: 'menus.member_sections.learn', links: [LINKS.thematiques, LINKS.ressources, LINKS.doGlegbe, { ...LINKS.violences, label: 'menus.member_more.violences_short' }, LINKS.ccc] },
    { id: 'help', title: 'menus.member_sections.help', links: [{ ...LINKS.sos, label: 'menus.member_more.sos_full' }, LINKS.signaler, LINKS.urgences, LINKS.annuaire, LINKS.assistant] },
    { id: 'peer', title: 'menus.member_sections.peer', links: [LINKS.espacePair, LINKS.collecte], ability: 'peer_space.access' },
];

export const ACCOUNT_LINKS: NavLink[] = [LINKS.parametres, LINKS.aide];

export function hrefOf(item: NavLink): string {
    return route(item.route, undefined, item.path) + (item.hash ?? '');
}

/** Chemin sans requête, fragment ni barre finale : « /fil/12?x=1 » → « /fil/12 ». */
export function pathOf(url: string): string {
    let path = url;
    try {
        path = new URL(url, 'http://xomiton.local').pathname;
    } catch {
        path = url.split(/[?#]/)[0] ?? '/';
    }
    return path.length > 1 ? path.replace(/\/+$/, '') : path;
}

export function isActive(item: NavLink, currentUrl: string): boolean {
    const current = pathOf(currentUrl);
    const target = pathOf(hrefOf(item));
    return current === target || current.startsWith(`${target}/`);
}

/** Lien permis : sans permission requise, ou permission présente dans `can`. */
export function allowed(item: { ability?: string }, can: (ability: string) => boolean): boolean {
    return !item.ability || can(item.ability);
}

/** Chargement complet d'une adresse (objet remplaçable en test : `window.location` n'y est pas modifiable). */
export const browser = {
    assign(url: string): void {
        window.location.assign(url);
    },
};

/**
 * Lien d'une notification (venu de l'API) : `inertia` pour une page de l'espace membre du même site (visite Inertia,
 * le gabarit persiste), `location` pour le reste en http(s) ou relatif (chargement complet), null pour tout autre
 * schéma (`javascript:`, `data:`…) ou une adresse illisible.
 */
export function notificationTarget(href: string | null | undefined): { kind: 'inertia' | 'location'; url: string } | null {
    if (!href || href === '#') return null;
    const origin = typeof window !== 'undefined' && window.location?.origin && window.location.origin !== 'null' ? window.location.origin : 'http://xomiton.local';
    let target: URL;
    try {
        target = new URL(href, origin);
    } catch {
        return null;
    }
    if (target.protocol !== 'http:' && target.protocol !== 'https:') return null;
    if (target.origin !== origin) return { kind: 'location', url: target.href };
    const local = `${target.pathname}${target.search}${target.hash}`;
    const member = Object.values(LINKS).some((item) => item.inertia && isActive(item, target.pathname));
    return { kind: member ? 'inertia' : 'location', url: local };
}
