// @vitest-environment jsdom
// Page /espace-pair/dossiers (lot espace-pair, P9-08) : liste des dossiers de la pair·e (code, nature, date, lieu,
// violence, statut, nombre d'étapes) avec lien vers la fiche, filtres statut · nature · code en visite partielle,
// pagination, états vides, jamais de notes.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, within } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { makeCase } from '@/test/peer';
import Cases from './Cases.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const base = (overrides: Record<string, unknown> = {}) => ({
    title: 'Mes dossiers',
    filters: { status: null, kind: null, q: null },
    statuses: [{ value: 'ouvert', label: 'Ouvert' }, { value: 'en_cours', label: 'En cours' }, { value: 'clos', label: 'Clos' }],
    kinds: [{ value: 'VBG', label: 'Violence basée sur le genre' }, { value: 'VDH', label: 'Violation des droits humains' }],
    cases: {
        data: [makeCase({ id: 2, code: 'VDH-0003', kind: 'VDH', status: 'clos', status_label: 'Clos', status_badge: 'success', followups_count: 4, commune: null, notes: undefined }), makeCase({ id: 1, followups_count: 1 })],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
    },
    ...overrides,
});

describe('Page /espace-pair/dossiers', () => {
    it('liste ses dossiers avec lien vers la fiche, statut en toutes lettres et nombre d’étapes', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Cases, { url: '/espace-pair/dossiers', pageProps: base() });

        expect(screen.getByRole('heading', { level: 1, name: 'Mes dossiers' })).toBeTruthy();
        expect(screen.getByRole('heading', { level: 2, name: '2 dossiers' })).toBeTruthy();
        const rows = within(document.querySelector('[data-peer-cases]') as HTMLElement).getAllByRole('row').slice(1);
        expect(rows).toHaveLength(2);
        expect(rows[0].textContent).toContain('VDH-0003');
        expect(rows[0].textContent).toContain('Clos');
        expect(rows[0].textContent).toContain('Zou');
        expect(rows[1].textContent).toContain('Abomey, Zou');
        expect(rows[1].textContent).toContain('Violence physique');
        expect(within(rows[0]).getByRole('link', { name: /VDH-0003/ }).getAttribute('href')).toBe('/espace-pair/dossiers/VDH-0003');
        expect(document.body.textContent).not.toContain('Accueil réalisé.');
        expect(screen.getByRole('link', { name: 'Nouvelle collecte' }).getAttribute('href')).toBe('/collecte');
        expect(screen.queryByRole('navigation', { name: 'Pagination des dossiers' })).toBeNull();
    });

    it('filtre par code, statut et type (visite partielle) puis réinitialise', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({}));
        await mountInertia(Cases, { url: '/espace-pair/dossiers', pageProps: base() });

        await fireEvent.update(screen.getByRole('searchbox', { name: 'Code du dossier' }), ' vbg-00 ');
        await fireEvent.update(screen.getByRole('combobox', { name: 'Statut' }), 'clos');
        await fireEvent.update(screen.getByRole('combobox', { name: 'Type' }), 'VDH');
        await fireEvent.click(screen.getByRole('button', { name: 'Filtrer' }));
        expect(get).toHaveBeenLastCalledWith('/espace-pair/dossiers', { q: 'vbg-00', status: 'clos', kind: 'VDH' }, expect.objectContaining({ only: ['filters', 'cases'], preserveState: true }));
        expect(screen.queryByRole('link', { name: 'Réinitialiser' })).toBeNull();
    });

    it('montre les filtres appliqués et un lien de réinitialisation', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({}));
        await mountInertia(Cases, { url: '/espace-pair/dossiers?status=clos', pageProps: base({ filters: { status: 'clos', kind: null, q: null } }) });

        expect((screen.getByRole('combobox', { name: 'Statut' }) as HTMLSelectElement).value).toBe('clos');
        await fireEvent.click(screen.getByRole('link', { name: 'Réinitialiser' }));
        expect(get).toHaveBeenLastCalledWith('/espace-pair/dossiers', {}, expect.anything());
    });

    it('pagine en gardant les filtres', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({}));
        await mountInertia(Cases, {
            url: '/espace-pair/dossiers?status=clos&page=2',
            pageProps: base({ filters: { status: 'clos', kind: null, q: null }, cases: { data: [makeCase()], meta: { current_page: 2, last_page: 3, per_page: 15, total: 31 } } }),
        });

        const nav = screen.getByRole('navigation', { name: 'Pagination des dossiers' });
        expect(nav.textContent).toContain('Page 2 sur 3');
        expect(within(nav).getByRole('link', { name: 'Suivant' }).getAttribute('href')).toBe('/espace-pair/dossiers?status=clos&page=3');
        expect(within(nav).getByRole('link', { name: 'Précédent' }).getAttribute('href')).toBe('/espace-pair/dossiers?status=clos');
        expect(get).not.toHaveBeenCalled();
    });

    it('désactive Précédent sur la première page et Suivant sur la dernière (pas de lien)', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Cases, { url: '/espace-pair/dossiers', pageProps: base({ cases: { data: [makeCase()], meta: { current_page: 1, last_page: 2, per_page: 15, total: 20 } } }) });

        const nav = screen.getByRole('navigation', { name: 'Pagination des dossiers' });
        expect(within(nav).queryByRole('link', { name: 'Précédent' })).toBeNull();
        expect(nav.querySelector('[aria-disabled="true"]')?.textContent).toContain('Précédent');
        expect(within(nav).getByRole('link', { name: 'Suivant' }).getAttribute('href')).toBe('/espace-pair/dossiers?page=2');
    });

    it('annonce le total de résultats (région aria-live) et la page, et borne la recherche selon le serveur', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Cases, { url: '/espace-pair/dossiers', pageProps: base({ search_max: 9, cases: { data: [makeCase()], meta: { current_page: 2, last_page: 3, per_page: 15, total: 31 } } }) });

        const live = document.querySelector('[data-peer-results]') as HTMLElement;
        expect(live.getAttribute('aria-live')).toBe('polite');
        expect(live.textContent).toContain('31 dossiers');
        expect(live.textContent).toContain('Page 2 sur 3');
        expect(screen.getByRole('searchbox', { name: 'Code du dossier' }).getAttribute('maxlength')).toBe('9');
    });

    it('états vides : sans dossier (invite à collecter) et sans résultat de filtre', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Cases, { url: '/espace-pair/dossiers', pageProps: base({ cases: { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } } }) });
        expect(screen.getByText('Aucun dossier pour le moment')).toBeTruthy();
        expect(screen.getAllByRole('link', { name: 'Nouvelle collecte' }).length).toBeGreaterThan(0);
        unmountInertia();

        await mountInertia(Cases, { url: '/espace-pair/dossiers?q=zzz', pageProps: base({ filters: { status: null, kind: null, q: 'zzz' }, cases: { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } } }) });
        expect(screen.getByText('Aucun dossier ne correspond à ces critères.')).toBeTruthy();
    });
});
