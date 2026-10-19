// @vitest-environment jsdom
// Gabarit membre Vue (P6-03) : en-tête XmHeader (menus, lien actif, compteurs dans les noms accessibles, cloche branchée
// sur l'API P6-07 avec polling et région polite, liens des notifications, menu du compte, déconnexion), sidenav XmSidenav
// (sections, permission pair, carte profil réelle, offcanvas refermé à chaque visite, aria-expanded du bouton « Menu »),
// bandeau XmVerifyBanner, colonne XmRightColumn, gabarit MemberLayout (repères, lien d'évitement et focus après visite
// sur le contenu de la page, après le sidenav) et page provisoire Member/Placeholder.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { fireEvent } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { browser } from '@/app/memberNav';
import { inertiaEvent, memberProps, mountInertia, unmountInertia } from '@/test/inertia';
import { Offcanvas } from '@/shared/bootstrap.js';
import XmHeader from './XmHeader.vue';
import XmRightColumn from './XmRightColumn.vue';
import XmSidenav from './XmSidenav.vue';
import XmVerifyBanner from './XmVerifyBanner.vue';
import MemberLayout from '@/layouts/MemberLayout.vue';
import Placeholder from '@/Pages/Member/Placeholder.vue';

const { logout } = vi.hoisted(() => ({ logout: vi.fn() }));
vi.mock('@/app/logout', () => ({ logout }));

const page = (component: unknown, props: Record<string, unknown> = {}) => defineComponent({ render: () => h(component as never, props) });
const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
const $ = <T extends Element = HTMLElement>(selector: string) => document.querySelector<T>(selector);
const $$ = (selector: string) => [...document.querySelectorAll<HTMLElement>(selector)];
const text = (el: Element | null) => (el?.textContent ?? '').replace(/\s+/g, ' ').trim();
const flush = async () => { for (let i = 0; i < 5; i += 1) await Promise.resolve(); await nextTick(); };

let fetchMock: ReturnType<typeof vi.fn>;

beforeEach(() => {
    fetchMock = vi.fn(async () => json({ data: { unread: 0 } }));
    vi.stubGlobal('fetch', fetchMock);
    logout.mockClear();
});

afterEach(() => {
    unmountInertia();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('XmHeader', () => {
    it('porte les menus du prototype dans un en-tête nommé, lien actif marqué aria-current', async () => {
        await mountInertia(page(XmHeader), { url: '/fil/12?vue=1' });
        expect($('header#memberHeader nav')?.getAttribute('aria-label')).toBe('Navigation de mon espace');
        const fil = $$('#memberNavbar .navbar-nav > li > a.nav-link')[0];
        expect(text(fil)).toBe('Fil');
        expect(fil.getAttribute('href')).toBe('/fil');
        expect(fil.getAttribute('aria-current')).toBe('page');
        expect(fil.className).toContain('active');
        expect($$('.dropdown-toggle').map(text)).toEqual(['Apprendre', 'Communauté', 'Aide & protection']);
        const learn = $$('[aria-labelledby="menuApprendre"] li');
        expect(learn.map(text)).toEqual(['Thématiques', 'Ressources', 'Violences : comprendre', 'Lois et codes du Bénin', '', 'Outils CCC interactifs']);
        expect(learn[4].className).toBe('dropdown-divider');
        const sos = $('[aria-labelledby="menuAide"] a.dropdown-item') as HTMLElement;
        expect([text(sos), sos.getAttribute('href'), sos.className.includes('text-danger')]).toEqual(['SOS (position GPS)', '/sos', true]);
        expect($('#memberNavbar a[href="/espace-pair"]')).toBeNull();
        expect($('form[role="search"]')?.getAttribute('action')).toBe('/recherche');
        expect($('label[for="memberSearch"]')).not.toBeNull();
    });

    it('montre « Espace pair » avec la permission et marque l’entrée de menu active', async () => {
        await mountInertia(page(XmHeader), { url: '/groupes', props: memberProps({ can: { 'peer_space.access': true } }) });
        expect(text($('#memberNavbar a[href="/espace-pair"]'))).toBe('Espace pair');
        const groups = $('[aria-labelledby="menuCommunaute"] a[href="/groupes"]') as HTMLElement;
        expect(groups.getAttribute('aria-current')).toBe('page');
        expect($('#memberNavbar .navbar-nav > li > a.nav-link[href="/fil"]')?.hasAttribute('aria-current')).toBe(false);
    });

    it('affiche les compteurs dans le nom accessible des boutons messages et cloche', async () => {
        await mountInertia(page(XmHeader), { props: memberProps({ counters: { notifications: 3, messages: 2 } }) });
        const messages = $('a[href="/messages"]') as HTMLElement;
        expect(messages.getAttribute('aria-label')).toBe('Messages, 2 conversations non lues');
        expect(text($('[data-xm-messages-count]'))).toBe('2');
        expect(text($('#memberBell .badge'))).toContain('3');
        expect($('#memberBell > button')?.getAttribute('aria-label')).toBe('Notifications, 3 non lues');
    });

    it('sans non-lus : noms accessibles simples', async () => {
        await mountInertia(page(XmHeader));
        expect($('a[href="/messages"]')?.getAttribute('aria-label')).toBe('Messages');
        expect($('#memberBell > button')?.getAttribute('aria-label')).toBe('Notifications');
        const live = $('[data-xm-bell-live]') as HTMLElement;
        expect([live.getAttribute('role'), live.getAttribute('aria-live'), text(live)]).toEqual(['status', 'polite', '']);
    });

    it('relit le compteur de la cloche toutes les bell_poll_seconds (15 s au moins, D7), jamais hors ligne ; hausse annoncée', async () => {
        vi.useFakeTimers();
        fetchMock.mockImplementation(async () => json({ data: { unread: 7 } }));
        await mountInertia(page(XmHeader), { props: memberProps({ ui: { bell_poll_seconds: 5 } }) });
        await vi.advanceTimersByTimeAsync(10000);
        expect(fetchMock).not.toHaveBeenCalled();
        await vi.advanceTimersByTimeAsync(5000);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/notifications/unread-count');
        await flush();
        expect(text($('#memberBell .badge'))).toContain('7');
        expect(text($('[data-xm-bell-live]'))).toBe('7 notifications non lues');
        expect($('#memberBell > button')?.getAttribute('aria-label')).toBe('Notifications, 7 non lues');

        const { useUiStore } = await import('@/stores/ui');
        useUiStore().setOnline(false);
        await vi.advanceTimersByTimeAsync(15000);
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });

    it('n’annonce pas une baisse du compteur (lecture)', async () => {
        await mountInertia(page(XmHeader), { props: memberProps({ counters: { notifications: 4, messages: 0 } }) });
        const { useNotificationsStore } = await import('@/stores/notifications');
        useNotificationsStore().unread = 2;
        await flush();
        expect(text($('[data-xm-bell-live]'))).toBe('');
        useNotificationsStore().unread = 3;
        await flush();
        expect(text($('[data-xm-bell-live]'))).toBe('3 notifications non lues');
    });

    it('charge la liste à l’ouverture et enregistre la lecture avant d’ouvrir le lien', async () => {
        fetchMock.mockImplementation(async (url: string) => (String(url).startsWith('/api/v1/notifications?')
            ? json({ data: [{ id: 'n1', title: 'Nouvelle fiche publiée', url: '/ressources/porter-plainte', read: false }], meta: { unread: 1 } })
            : json({ meta: { unread: 0 } })));
        await mountInertia(page(XmHeader));
        const toggle = $('#memberBell > button') as HTMLElement;
        toggle.dispatchEvent(new Event('show.bs.dropdown', { bubbles: true }));
        await vi.waitFor(() => expect($('#memberBell a[data-notif-id="n1"]')).not.toBeNull());
        expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/notifications?filter=all');
        const item = $('#memberBell a[data-notif-id="n1"]') as HTMLAnchorElement;
        expect(text(item)).toContain('Nouvelle fiche publiée');

        const assign = vi.spyOn(browser, 'assign').mockImplementation(() => undefined);
        const click = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        item.dispatchEvent(click);
        expect(click.defaultPrevented).toBe(true);
        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        expect(fetchMock.mock.calls.map(([url, init]) => [String(url), (init as RequestInit).method])).toContainEqual(['/api/v1/notifications/n1/read', 'POST']);
        // Page Blade publique : chargement complet, après l'enregistrement de la lecture.
        await vi.waitFor(() => expect(assign).toHaveBeenCalledWith('/ressources/porter-plainte'));
        assign.mockRestore();
    });

    it('ouvre une notification de l’espace membre en visite Inertia ; schéma douteux écarté', async () => {
        fetchMock.mockImplementation(async (url: string) => (String(url).startsWith('/api/v1/notifications?')
            ? json({ data: [
                { id: 'n2', title: 'Nouveau message dans un groupe', url: '/groupes/3', read: false },
                { id: 'n3', title: 'Ancienne', url: '/fil/12', read: true },
                { id: 'n4', title: 'Douteuse', url: 'javascript:alert(1)', read: false },
            ], meta: { unread: 2 } })
            : json({ meta: { unread: 1 } })));
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        const assign = vi.spyOn(browser, 'assign').mockImplementation(() => undefined);
        await mountInertia(page(XmHeader));
        ($('#memberBell > button') as HTMLElement).dispatchEvent(new Event('show.bs.dropdown', { bubbles: true }));
        await vi.waitFor(() => expect($('#memberBell a[data-notif-id="n2"]')).not.toBeNull());

        ($('#memberBell a[data-notif-id="n2"]') as HTMLElement).dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 }));
        await vi.waitFor(() => expect(visit).toHaveBeenCalledWith('/groupes/3'));

        await fireEvent.click($$('#memberBell [role="tab"]')[2]); // onglet « Toutes »
        const read = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        ($('#memberBell a[data-notif-id="n3"]') as HTMLElement).dispatchEvent(read);
        expect(read.defaultPrevented).toBe(true);
        expect(visit).toHaveBeenLastCalledWith('/fil/12');

        expect($('#memberBell a[data-notif-id="n4"]')?.getAttribute('href')).toBe('#');
        const unsafe = new MouseEvent('click', { bubbles: true, cancelable: true, button: 0 });
        ($('#memberBell a[data-notif-id="n4"]') as HTMLElement).dispatchEvent(unsafe);
        expect(unsafe.defaultPrevented).toBe(true);
        await vi.waitFor(() => expect(fetchMock.mock.calls.map(([url]) => String(url))).toContain('/api/v1/notifications/n4/read'));
        expect(visit).toHaveBeenCalledTimes(2);
        expect(assign).not.toHaveBeenCalled();
        visit.mockRestore();
        assign.mockRestore();
    });

    it('menu du compte : rôle, administration si permise, déconnexion par app/logout.ts', async () => {
        await mountInertia(page(XmHeader), { props: memberProps({ shell: { admin_url: '/admin' } }) });
        expect(text($('[aria-labelledby="profileDropdown"]'))).toContain('Awa_S');
        expect(text($('[aria-labelledby="profileDropdown"]'))).toContain('Membre');
        expect($('[aria-labelledby="profileDropdown"] a[href="/admin"]')).not.toBeNull();
        expect($('#profileDropdown')?.getAttribute('aria-label')).toBe('Mon compte');
        await fireEvent.click($('#memberHeader [data-xm-logout]') as HTMLElement);
        expect(logout).toHaveBeenCalledTimes(1);
    });

    it('referme le menu replié au début d’une visite Inertia', async () => {
        await mountInertia(page(XmHeader));
        const collapse = $('#memberNavbar') as HTMLElement;
        collapse.classList.add('show');
        inertiaEvent('start', { visit: {} });
        expect(collapse.classList.contains('show')).toBe(false);
    });
});

describe('XmSidenav', () => {
    it('carte profil réelle, sections du prototype, lien actif, déconnexion', async () => {
        await mountInertia(page(XmSidenav), { url: '/profil' });
        expect($('#memberSidenav nav')?.getAttribute('aria-label')).toBe('Menu de mon espace');
        expect(text($('#memberSidenav .card-body .text-center'))).toContain('@Awa_S · Littoral');
        expect($$('[data-xm-profile-stats] p').map(text)).toEqual(['24', '182', '96']);
        expect($$('.xm-nav-section').map(text)).toEqual(['Réseau social', 'Apprendre', 'Aide & protection', 'Compte']);
        const profile = $('.xm-side-link[href="/profil"]') as HTMLElement;
        expect(profile.getAttribute('aria-current')).toBe('page');
        expect($('.xm-side-link[href="/fil"]')?.hasAttribute('aria-current')).toBe(false);
        expect($('.xm-side-link[href="/sos"]')?.className).toContain('danger');
        expect($('.xm-side-link[href="/collecte"]')).toBeNull();
        await fireEvent.click($('#memberSidenav [data-xm-logout]') as HTMLElement);
        expect(logout).toHaveBeenCalledTimes(1);
    });

    it('section pair avec la permission ; département par défaut ; offcanvas refermé à chaque visite', async () => {
        await mountInertia(page(XmSidenav), { props: memberProps({ can: { 'peer_space.access': true }, shell: { zone: null } }) });
        expect($$('.xm-nav-section').map(text)).toContain('Pair animateur·rice');
        expect($('.xm-side-link[href="/collecte"]')).not.toBeNull();
        expect(text($('#memberSidenav .card-body .text-center'))).toContain('· Bénin');

        const offcanvas = $('#offcanvasSideNavbar') as HTMLElement;
        const toggle = $('[data-bs-target="#offcanvasSideNavbar"]') as HTMLElement;
        expect(toggle.getAttribute('aria-expanded')).toBe('false');
        Offcanvas.getOrCreateInstance(offcanvas).show();
        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(offcanvas.classList.contains('show')).toBe(true);
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        inertiaEvent('start', { visit: {} });
        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(offcanvas.classList.contains('show')).toBe(false);
        expect(toggle.getAttribute('aria-expanded')).toBe('false');
    });
});

describe('XmVerifyBanner', () => {
    it('rappel tant que l’e-mail n’est pas confirmé, renvoi par formulaire natif avec jeton CSRF', async () => {
        await mountInertia(page(XmVerifyBanner), { props: memberProps({ auth: { user: { email_verified: false } } }) });
        const banner = $('.xm-verify-banner') as HTMLElement;
        expect(banner.getAttribute('role')).toBe('alert');
        expect(text(banner)).toContain('Confirme ton e-mail');
        expect($('.xm-verify-banner a')?.getAttribute('href')).toBe('/code-de-verification?purpose=signup');
        const form = $('.xm-verify-banner form') as HTMLFormElement;
        expect([form.getAttribute('method'), form.getAttribute('action')]).toEqual(['post', '/code-de-verification/renvoyer']);
        expect((form.querySelector('[name="_token"]') as HTMLInputElement).value).toBe('jeton-csrf');
        expect((form.querySelector('[name="purpose"]') as HTMLInputElement).value).toBe('signup');
    });

    it('rien une fois l’adresse confirmée', async () => {
        await mountInertia(page(XmVerifyBanner));
        expect($('.xm-verify-banner')).toBeNull();
    });
});

describe('XmRightColumn', () => {
    it('repère complémentaire nommé ; urgence toujours, suggestions et événements fournis par la page', async () => {
        const invited: unknown[] = [];
        await mountInertia(page(XmRightColumn, {
            suggestions: [{ id: 3, pseudo: 'Mariam_A', href: '/profil', meta: 'Membre · Borgou' }],
            events: [{ id: 9, title: 'Atelier « Dire non »', href: '/evenements', meta: '15/11 · Cotonou' }],
            onInvite: (id: unknown) => invited.push(id),
        }));
        const aside = $('aside') as HTMLElement;
        expect(aside.getAttribute('aria-label')).toBe('Informations complémentaires');
        expect($$('aside h2').map(text)).toEqual(['Qui suivre', 'Événements', 'En cas d’urgence']);
        expect($('aside a.btn-danger')?.getAttribute('href')).toBe('/urgences');
        await fireEvent.click($('aside button[title="Envoyer une invitation"]') as HTMLElement);
        expect(invited).toEqual([3]);
        expect($('aside button[title="Envoyer une invitation"]')?.getAttribute('aria-label')).toBe('Envoyer une invitation : Mariam_A');
    });

    it('sans données : seule la carte d’urgence', async () => {
        await mountInertia(page(XmRightColumn));
        expect($$('aside h2').map(text)).toEqual(['En cas d’urgence']);
    });
});

describe('MemberLayout + Member/Placeholder', () => {
    const withLayout = () => ({ ...Placeholder, layout: MemberLayout });

    it('repères, lien d’évitement en premier, colonnes du fil et SOS flottant', async () => {
        await mountInertia(withLayout(), { pageProps: { title: 'Fil d’actualité', prototype: 'fil.php', aside: true } });
        const app = $('#app') as HTMLElement;
        expect(app.firstElementChild?.className).toBe('xm-skip-link');
        expect($$('header').length).toBe(1);
        expect($('main#contenu')?.getAttribute('tabindex')).toBe('-1');
        expect($('main#contenu #memberSidenav')).not.toBeNull();
        expect(text($('main h1'))).toBe('Fil d’actualité');
        expect(text($('[data-xm-placeholder]'))).toContain('fil.php');
        expect($('[data-xm-placeholder]')?.className).toBe('col-md-8 col-lg-6 vstack gap-4');
        expect($('main aside.col-lg-3')).not.toBeNull();
        expect($('[data-xm-sos-fab] .xm-sos-wrap--fixed.xm-sos-fab #xmSosFab')).not.toBeNull();
        expect($('[data-xm-sos-fab]')?.getAttribute('data-outbox')).toBe('sos');

        // Lien d'évitement : début du contenu de la page, après le sidenav (carte profil et menu non repassés).
        await fireEvent.click($('.xm-skip-link') as HTMLElement);
        expect(document.activeElement).toBe($('[data-xm-placeholder]'));
        expect(document.activeElement?.getAttribute('tabindex')).toBe('-1');
        ($('#memberSearch') as HTMLElement).focus();
        expect($('[data-xm-placeholder]')?.hasAttribute('tabindex')).toBe(false);
    });

    it('sans colonne droite : contenu en col-lg-9', async () => {
        await mountInertia(withLayout(), { url: '/profil', pageProps: { title: 'Mon profil', prototype: 'my-profile.php', aside: false } });
        expect($('[data-xm-placeholder]')?.className).toBe('col-lg-9');
        expect($('main aside')).toBeNull();
    });

    it('focus sur le contenu de la page (après le sidenav) après une visite vers une autre adresse, jamais sans visite ni sur place', async () => {
        await mountInertia(withLayout(), { pageProps: { title: 'Fil', prototype: 'fil.php' } });
        const search = $('#memberSearch') as HTMLElement;
        search.focus();
        inertiaEvent('navigate', { page: { url: '/messages' } });
        expect(document.activeElement).toBe(search);
        inertiaEvent('start', { visit: {} });
        inertiaEvent('navigate', { page: { url: '/messages' } });
        expect(document.activeElement).toBe(search);
        inertiaEvent('start', { visit: {} });
        inertiaEvent('navigate', { page: { url: '/groupes' } });
        await nextTick();
        expect(document.activeElement).toBe($('[data-xm-placeholder]'));
        expect($('#memberSidenav')?.contains(document.activeElement)).toBe(false);
    });
});
