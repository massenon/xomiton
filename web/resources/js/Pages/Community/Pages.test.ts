// @vitest-environment jsdom
// Pages /pages, /pages/creer, /pages/{ulid} (lot groupes-pages, P8-03) : cartes (catégorie, « Page vérifiée »,
// abonné·es), Suivre la page (compteur repris du serveur), filtres de catégorie (visite partielle Inertia), création
// (nom ≥ 3 vérifié avant l'envoi, puis fiche de la page), bandeau « en attente » pour la propriétaire.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { PAGE_ID, makePage } from '@/test/community';
import PageCreate from './PageCreate.vue';
import PageShow from './PageShow.vue';
import Pages from './Pages.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('Page /pages', () => {
    it('suit une page et filtre par catégorie', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        const { calls } = fakeFetch(() => json({ data: { following: true, followers_count: 42, changed: true } }));
        await mountInertia(Pages, { url: '/pages', pageProps: { title: 'Pages', tab: 'all', categories: ['Santé', 'Juridique'], pages: { data: [makePage()], meta: { current_page: 1, last_page: 1 } }, can_create: true } });

        expect(screen.getByText('Page vérifiée')).toBeTruthy();
        expect(screen.getByText('41 abonné·es')).toBeTruthy();
        expect(screen.getByRole('link', { name: /Créer une page/ }).getAttribute('href')).toBe('/pages/creer');

        await fireEvent.click(screen.getByRole('button', { name: /Suivre la page/ }));
        await waitFor(() => expect(screen.getByRole('button', { name: /Abonné·e/ }).hasAttribute('aria-pressed')).toBe(false));
        expect(calls[0]).toMatchObject({ url: `/api/v1/pages/${PAGE_ID}/follow`, method: 'POST' });
        expect(screen.getByText('42 abonné·es')).toBeTruthy();

        await fireEvent.click(screen.getByRole('link', { name: 'Juridique' }));
        expect(get).toHaveBeenCalledWith('/pages', { categorie: 'Juridique' }, expect.objectContaining({ only: ['pages', 'tab', 'category', 'q', 'categories'] }));
    });

    it('montre une page en attente dans « Mes pages », sans bouton Suivre', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Pages, { url: '/pages?tab=mine', pageProps: { title: 'Pages', tab: 'mine', pages: { data: [makePage({ status: 'pending', status_label: 'En attente de vérification', is_mine: true, can: { follow: false, update: true } })], meta: { current_page: 1, last_page: 1 } } } });

        expect(screen.getByText('En attente de vérification')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Suivre la page/ })).toBeNull();
    });
});

describe('Page /pages/creer', () => {
    it('refuse un nom trop court sans appel puis crée la page et ouvre sa fiche', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        const created = makePage({ status: 'pending', is_mine: true });
        const { calls } = fakeFetch(() => json({ data: created }, 201));
        await mountInertia(PageCreate, { url: '/pages/creer', pageProps: { title: 'Créer une page', categories: ['Organisation', 'Santé'], limits: { name_min: 3, name_max: 80, description_max: 400 } } });

        const name = screen.getByLabelText(/Nom de la page/) as HTMLInputElement;
        await fireEvent.update(name, 'ab');
        await fireEvent.submit(name.form as HTMLFormElement);
        expect(screen.getAllByText('Le nom de la page doit contenir au moins 3 caractères.').length).toBeGreaterThan(0);
        expect(calls).toHaveLength(0);

        await fireEvent.update(name, 'Clinique amie de Parakou');
        await fireEvent.update(screen.getByLabelText(/Catégorie/) as HTMLSelectElement, 'Santé');
        await fireEvent.submit(name.form as HTMLFormElement);
        await waitFor(() => expect(visit).toHaveBeenCalledWith(created.url));
        expect(calls[0]).toMatchObject({ url: '/api/v1/pages', method: 'POST', body: { name: 'Clinique amie de Parakou', category: 'Santé', description: null, website: null } });
    });
});

describe('Page /pages/{ulid}', () => {
    it('affiche le bandeau d’attente à la propriétaire et le site en lien externe', async () => {
        await mountInertia(PageShow, { url: `/pages/${PAGE_ID}`, pageProps: { title: 'Clinique', organization: makePage({ status: 'pending', is_mine: true, can: { follow: false, update: true } }) } });

        expect(screen.getByRole('status').textContent).toContain('Ta page attend la vérification');
        expect(screen.queryByRole('button', { name: /Suivre la page/ })).toBeNull();
        const link = screen.getByRole('link', { name: /Site web/ });
        expect(link.getAttribute('rel')).toBe('noopener noreferrer');
        expect(link.getAttribute('target')).toBe('_blank');
    });
});
