// @vitest-environment jsdom
// Page /profil et /membres/{pseudo} (lot profil, P8-07) : en-tête du prototype (pseudonyme seul, RG-02), six onglets
// dont Publications avec compteur (Activité : son profil seulement), menu Modifier le profil / Confidentialité /
// Télécharger mes données, « Charger plus » par l'API, colonne Présentation · Médias · Connexions, connexions
// recherchées par l'API, et **aucun champ sensible** sur le profil d'une autre personne sans choix explicite.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json, makePost } from '@/test/feed';
import { makeProfile, makeProgressive, makeTabs, otherProfile, selfLinks } from '@/test/profile';
import Profile from './Profile.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    window.sessionStorage.clear();
});

const otherLinks = { media_library: null, who_to_follow: null, events: '/evenements' };

describe('Page /profil', () => {
    it('son profil : « Changer… » sont de vrais boutons, « Retirer la couverture » annonce le résultat', async () => {
        const { calls } = fakeFetch(() => json({ data: { avatar_url: null, cover_url: '/assets/images/xomiton/profile-cover.jpg', completion: 57, has_avatar: false, has_cover: false } }));
        await mountInertia(Profile, { url: '/profil', pageProps: {
            title: 'Mon profil', tab: 'about', tabs: makeTabs('/profil'), profile: makeProfile({ has_cover: true }), links: selfLinks, panel: null,
            side: { media: [], connections: [] },
        } });

        const change = screen.getByRole('button', { name: /Changer la couverture/ });
        change.focus();
        expect(document.activeElement).toBe(change);
        expect(screen.getByRole('button', { name: 'Changer l’avatar' })).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Retirer l’avatar' })).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: /Retirer la couverture/ }));
        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0]).toMatchObject({ method: 'DELETE', url: '/api/v1/me/profile/cover' });
        await waitFor(() => expect(screen.queryByRole('button', { name: /Retirer la couverture/ })).toBeNull());
        expect(Array.from(document.querySelectorAll('[role="status"]')).some((node) => node.textContent?.includes('Image retirée.'))).toBe(true);
    });

    it('son profil sans e-mail confirmé : aucun bouton « Changer… », explication à la place', async () => {
        await mountInertia(Profile, { url: '/profil', pageProps: {
            title: 'Mon profil', tab: 'about', tabs: makeTabs('/profil'), profile: makeProfile({ can_change_images: false }), links: selfLinks, panel: null,
            side: { media: [], connections: [] },
        } });

        expect(screen.queryByRole('button', { name: /Changer la couverture/ })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Changer l’avatar' })).toBeNull();
        expect(screen.getByText(/Confirme ton adresse e-mail/)).toBeTruthy();
    });

    it('son profil : en-tête, six onglets avec compteur, menu, publications et suite par l’API', async () => {
        const { calls } = fakeFetch(() => json({ data: [makePost({ id: '01JC0000000000000000000009', body: 'Plus ancienne' })], meta: { next_cursor: null } }));
        await mountInertia(Profile, { url: '/profil', pageProps: {
            title: 'Mon profil', tab: 'posts', tabs: makeTabs('/profil'), profile: makeProfile(), links: selfLinks,
            panel: { data: [makePost({ body: 'Bonjour la communauté' })], meta: { next_cursor: 'c2' } },
            side: { media: [], connections: [{ id: 3, pseudo: 'Cyd', avatar_url: null, role_label: 'Membre', zone_label: null, url: '/membres/Cyd', message_url: '/messages?with=3' }] },
        } });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('Awa_S');
        const tabs = screen.getByRole('navigation', { name: 'Sections du profil' });
        expect(tabs.querySelectorAll('a')).toHaveLength(6);
        expect(screen.getByRole('link', { name: /Publications\s*3/ }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('link', { name: 'Activité' })).toBeTruthy();
        expect(screen.getByRole('link', { name: /Modifier le profil/ }).getAttribute('href')).toBe('/parametres');
        expect(screen.getByRole('link', { name: 'Télécharger mes données' }).getAttribute('href')).toBe('/parametres#donnees');
        expect(screen.getByText('Profil complété à 57 %')).toBeTruthy();
        expect(screen.getByText('Bonjour la communauté')).toBeTruthy();
        expect(screen.getByText('Cyd')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getByText('Plus ancienne')).toBeTruthy());
        expect(calls[0]?.url).toBe('/api/v1/users/7/posts?cursor=c2');
    });

    it('profil d’une autre personne : pas d’onglet Activité, message, aucun champ sensible non partagé', async () => {
        await mountInertia(Profile, { url: '/membres/Bea_K/a-propos', pageProps: {
            title: 'Profil de Bea_K', tab: 'about', tabs: makeTabs('/membres/Bea_K', false), profile: otherProfile({ sensitive: [] }), links: otherLinks, panel: null,
        } });

        expect(screen.queryByRole('link', { name: 'Activité' })).toBeNull();
        expect(screen.queryByText('Profil complété', { exact: false })).toBeNull();
        expect(screen.getByRole('link', { name: /Envoyer un message/ }).getAttribute('href')).toBe('/messages?with=9');
        expect(screen.getByText('Tu suis ce profil')).toBeTruthy();
        expect(screen.queryByText('Informations personnelles')).toBeNull();
        expect(document.body.textContent).not.toMatch(/Orientation|Identité de genre|Lesbienne/);
        expect(screen.queryByText('Confidentialité de ton profil')).toBeNull();
    });

    it('montre un champ sensible seulement quand le serveur le sert (choix explicite de la personne)', async () => {
        await mountInertia(Profile, { url: '/membres/Bea_K/a-propos', pageProps: {
            title: 'Profil de Bea_K', tab: 'about', tabs: makeTabs('/membres/Bea_K', false), links: otherLinks, panel: null,
            profile: otherProfile({ sensitive: [{ field: 'ally_status', label: 'Allié·e ou personne ressource ?', value: 'ressource', value_label: 'Personne ressource', shared: true }] }),
        } });

        expect(screen.getByText('Informations personnelles')).toBeTruthy();
        expect(screen.getByText('Personne ressource')).toBeTruthy();
        expect(screen.queryByText('Visible sur ton profil')).toBeNull();
    });

    it('connexions : recherche sur le pseudonyme par l’API, sans rechargement', async () => {
        const { calls } = fakeFetch(() => json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 24, total: 0, q: 'zz' } }));
        await mountInertia(Profile, { url: '/profil/connexions', pageProps: {
            title: 'Mon profil', tab: 'connections', tabs: makeTabs('/profil'), profile: makeProfile(), links: selfLinks,
            panel: { data: [{ id: 3, pseudo: 'Cyd', avatar_url: null, role_label: 'Membre', zone_label: 'Littoral', url: '/membres/Cyd', message_url: '/messages?with=3' }], meta: { current_page: 1, last_page: 1, per_page: 24, total: 1, q: null } },
        } });

        expect(screen.getByRole('link', { name: 'Cyd' }).getAttribute('href')).toBe('/membres/Cyd');
        await fireEvent.update(screen.getByRole('searchbox', { name: 'Rechercher une connexion' }), 'zz');
        await fireEvent.submit(screen.getByRole('search'));
        await waitFor(() => expect(screen.getByText('Aucune connexion ne correspond à ta recherche.')).toBeTruthy());
        expect(calls[0]?.url).toBe('/api/v1/members/Awa_S/connections?q=zz&page=1');
    });

    it('journal d’activité de son profil et bandeau progressif chargé à la demande, masqué par « Plus tard »', async () => {
        await mountInertia(Profile, { url: '/profil/activite', pageProps: {
            title: 'Mon profil', tab: 'activity', tabs: makeTabs('/profil'), profile: makeProfile(), links: selfLinks,
            panel: { data: [{ id: 'audit-1', type: 'login', icon: 'box-arrow-in-right', label: 'Connexion', detail: null, href: null, at: new Date().toISOString() }] },
            progressive: makeProgressive('age'),
        } });

        expect(screen.getByRole('heading', { name: 'Journal de mon activité' })).toBeTruthy();
        expect(screen.getByText('Connexion')).toBeTruthy();
        await waitFor(() => expect(screen.getByRole('heading', { name: 'Complète ton profil, c’est facultatif' })).toBeTruthy());

        await fireEvent.click(screen.getByRole('button', { name: 'Plus tard' }));
        await waitFor(() => expect(screen.queryByRole('heading', { name: 'Complète ton profil, c’est facultatif' })).toBeNull());
        expect(window.sessionStorage.getItem('xm_profile_banner_dismissed')).toBe('1');
    });
});
