// Liens du gabarit membre (P6-03) : lien actif par chemin (sous-chemins compris), routes Ziggy avec repli, permissions,
// liens des notifications (visite Inertia, chargement complet ou écartés), plancher de la cloche (D7).
import { afterEach, describe, expect, it } from 'vitest';
import { BELL_MIN_SECONDS, HEADER_MENUS, LINKS, SIDENAV_SECTIONS, allowed, hrefOf, isActive, notificationTarget, pathOf } from './memberNav';

afterEach(() => {
    delete (globalThis as { window?: unknown }).window;
});

describe('memberNav', () => {
    it('normalise le chemin courant (requête, fragment, barre finale)', () => {
        expect(pathOf('/fil/12?vue=1#post')).toBe('/fil/12');
        expect(pathOf('https://xomiton.bj/groupes/')).toBe('/groupes');
        expect(pathOf('/')).toBe('/');
    });

    it('lien actif sur son chemin et ses sous-chemins seulement', () => {
        expect(isActive(LINKS.fil, '/fil')).toBe(true);
        expect(isActive(LINKS.fil, '/fil/42')).toBe(true);
        expect(isActive(LINKS.fil, '/filtres')).toBe(false);
        expect(isActive(LINKS.pages, '/pages/creer')).toBe(true);
        expect(isActive(LINKS.messages, '/fil')).toBe(false);
    });

    it('lit les routes de Ziggy quand elles sont écrites dans la page, sinon le chemin', () => {
        expect(hrefOf(LINKS.urgences)).toBe('/urgences');
        (globalThis as { window?: unknown }).window = { route: (name: string) => `/app/${name}` };
        expect(hrefOf(LINKS.urgences)).toBe('/app/urgences');
    });

    it('réserve l’espace pair et la collecte à peer_space.access ; pages membres en Inertia, crise en lien simple', () => {
        const can = (ability: string) => ability === 'peer_space.access';
        expect(allowed(LINKS.espacePair, () => false)).toBe(false);
        expect(allowed(LINKS.espacePair, can)).toBe(true);
        expect(SIDENAV_SECTIONS.find((s) => s.id === 'peer')?.ability).toBe('peer_space.access');
        expect([LINKS.sos, LINKS.signaler, LINKS.urgences, LINKS.thematiques].some((l) => l.inertia)).toBe(false);
        expect([LINKS.fil, LINKS.messages, LINKS.annuaire, LINKS.assistant].every((l) => l.inertia)).toBe(true);
        expect(HEADER_MENUS.map((m) => m.id)).toEqual(['menuApprendre', 'menuCommunaute', 'menuAide']);
    });

    it('ouvre une notification en visite Inertia vers une page membre, en chargement complet sinon, jamais un autre schéma', () => {
        expect(notificationTarget('/groupes/3?onglet=membres#c')).toEqual({ kind: 'inertia', url: '/groupes/3?onglet=membres#c' });
        expect(notificationTarget('http://xomiton.local/fil/12')).toEqual({ kind: 'inertia', url: '/fil/12' });
        expect(notificationTarget('/ressources/porter-plainte')).toEqual({ kind: 'location', url: '/ressources/porter-plainte' });
        expect(notificationTarget('https://exemple.org/guide')).toEqual({ kind: 'location', url: 'https://exemple.org/guide' });
        for (const unsafe of ['javascript:alert(1)', ' JavaScript:alert(1)', 'data:text/html,<p>x</p>', 'vbscript:x', 'mailto:a@b.c', '#', '', null, undefined]) {
            expect(notificationTarget(unsafe)).toBeNull();
        }
    });

    it('cloche relue toutes les 15 s au moins : 10 s est réservé au tableau de garde (D7)', () => {
        expect(BELL_MIN_SECONDS).toBe(15);
    });
});
