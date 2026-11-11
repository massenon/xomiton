// @vitest-environment jsdom
// Page /recherche (lot recherche-membre, P8-13) : vue d'ensemble par section (h2, compteur, résultats des quatre
// familles, « Voir les n résultats » vers ?type=), pastilles de familles (aria-current), formulaire (visite Inertia,
// terme assaini), une famille avec « Charger plus » par l'API (nombre annoncé), messages terme trop court, aucun
// résultat, recherche indisponible ; compteurs des familles lus avec leur unité ; mode discret : terme hors de
// l'adresse (recherche par l'API, aucune visite) ; aucun terme écrit dans le stockage du navigateur.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { memberProps, mountInertia, unmountInertia } from '@/test/inertia';
import { holdTerm } from '@/search/suggest';
import { fakeFetch, json } from '@/test/feed';
import type { SearchHit, SearchSection } from '@/search/types';
import Index from './Index.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const TYPES = [
    { type: 'ressources', label: 'Ressources', icon: 'bi-folder2-open' },
    { type: 'thematiques', label: 'Thématiques', icon: 'bi-journal-text' },
    { type: 'contacts', label: 'Contacts', icon: 'bi-person-vcard' },
    { type: 'membres', label: 'Membres', icon: 'bi-people' },
] as const;

const resource = (id: number): SearchHit => ({
    type: 'ressources', icon: 'bi-journal-bookmark', id, slug: `guide-${id}`, title: `Guide Zemidjan ${id}`, kind: 'guide', kind_label: 'Guide',
    summary: 'Se déplacer en sécurité.', category: { slug: 'securite', label: 'Sécurité', icon: null, color: null }, file_size_label: null, duration_minutes: null, url: `/ressources/guide-${id}`,
});
const contact: SearchHit = {
    type: 'contacts', id: 9, name: 'Centre Zemidjan', role: 'juriste', role_label: 'Juriste', role_color: 'info', org: 'Clinique', phone: '+229 01 97 00 00 01',
    tel: 'tel:+22901970000001', whatsapp: null, whatsapp_url: null, email: null, availability: null, zone: { id: 4, name: 'Zou' }, commune: null, national: false,
    is_public: true, can: { callback: true }, url: '/annuaire?q=Centre+Zemidjan',
};
const member: SearchHit = { type: 'membres', id: 5, pseudo: 'zemidjan_awa', avatar_url: null, role: 'member', role_label: 'Membre', zone: null, profile_url: '/membres/zemidjan_awa' };

const section = (type: (typeof TYPES)[number], items: SearchHit[], total = items.length): SearchSection => ({ ...type, total, more: total > items.length, items });

const props = (overrides: Record<string, unknown> = {}) => ({
    title: 'Recherche',
    q: 'zemidjan',
    type: null,
    types: TYPES,
    sections: [section(TYPES[0], [resource(1)], 6), section(TYPES[1], []), section(TYPES[2], [contact]), section(TYPES[3], [member])],
    pagination: null,
    too_short: false,
    failed: false,
    limits: { min_length: 2, max_length: 80 },
    ...overrides,
});

describe('/recherche', () => {
    it('présente les familles trouvées, leurs résultats et le lien vers chaque famille', async () => {
        const setItem = vi.spyOn(Storage.prototype, 'setItem');
        fakeFetch(() => json({}));
        await mountInertia(Index, { url: '/recherche?q=zemidjan', pageProps: props() });

        expect(screen.getByRole('heading', { level: 1, name: 'Rechercher' })).toBeTruthy();
        expect(screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent?.trim())).toEqual(['Ressources', 'Contacts', 'Membres']);
        expect(screen.getByText('8 résultats')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Guide Zemidjan 1' }).getAttribute('href')).toBe('/ressources/guide-1');
        expect(screen.getByRole('link', { name: 'zemidjan_awa' }).getAttribute('href')).toBe('/membres/zemidjan_awa');
        const contacts = screen.getByRole('region', { name: 'Contacts' });
        expect(within(contacts).getByRole('link', { name: /Appeler/ }).getAttribute('href')).toBe('tel:+22901970000001');
        expect(within(contacts).getByRole('link', { name: /Voir dans l’annuaire/ }).getAttribute('href')).toBe('/annuaire?q=Centre+Zemidjan');
        expect(screen.getByRole('link', { name: /^Voir les 6.résultats dans «.Ressources.»$/ }).getAttribute('href')).toBe('/recherche?q=zemidjan&type=ressources');

        const nav = screen.getByRole('navigation', { name: 'Familles de résultats' });
        expect(within(nav).getByRole('link', { name: 'Tout' }).getAttribute('aria-current')).toBe('page');
        expect(within(nav).getByRole('link', { name: /Thématiques/ }).textContent).toContain('0');
        expect(within(nav).getByRole('link', { name: /Ressources/ }).querySelector('.badge .visually-hidden')?.textContent?.trim()).toBe('résultats');
        expect(screen.getByText(/Ton navigateur garde l’adresse de cette page/)).toBeTruthy();
        expect(screen.getByText(/Xomiton n’enregistre pas tes recherches/)).toBeTruthy();
        expect(setItem).not.toHaveBeenCalled();
    });

    it('relance la recherche en visite Inertia avec le terme assaini et garde la famille choisie', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({}));
        await mountInertia(Index, { url: '/recherche?q=zemidjan&type=contacts', pageProps: props({ type: 'contacts', sections: [section(TYPES[2], [contact])], pagination: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }) });

        await fireEvent.update(screen.getByRole('searchbox'), '  juriste \u0007 zou ');
        await fireEvent.submit(screen.getByRole('search'));
        expect(get).toHaveBeenCalledWith('/recherche', { q: 'juriste zou', type: 'contacts' }, expect.any(Object));

        await fireEvent.click(within(screen.getByRole('navigation')).getByRole('link', { name: 'Tout' }));
        expect(get).toHaveBeenLastCalledWith('/recherche', { q: 'zemidjan' }, expect.any(Object));
    });

    it('charge la suite d’une famille par l’API et annonce les résultats ajoutés', async () => {
        const { calls } = fakeFetch(() => json({ data: [resource(11), resource(12)], meta: { type: 'ressources', current_page: 2, last_page: 2, per_page: 10, total: 12, too_short: false } }));
        await mountInertia(Index, {
            url: '/recherche?q=zemidjan&type=ressources',
            pageProps: props({ type: 'ressources', sections: [section(TYPES[0], Array.from({ length: 10 }, (_, i) => resource(i + 1)), 12)], pagination: { current_page: 1, last_page: 2, per_page: 10, total: 12 } }),
        });

        expect(screen.getAllByRole('heading', { level: 3 })).toHaveLength(10);
        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getAllByRole('heading', { level: 3 })).toHaveLength(12));
        expect(calls[0].url).toBe('/api/v1/search?q=zemidjan&type=ressources&page=2');
        expect(screen.queryByRole('button', { name: 'Charger plus' })).toBeNull();
        expect(screen.getByText('2 résultats de plus affichés.')).toBeTruthy();
        expect(document.activeElement?.textContent).toBe('Guide Zemidjan 11');
    });

    it('annonce un terme trop court, l’absence de résultat et une recherche indisponible', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Index, { url: '/recherche?q=z', pageProps: props({ q: 'z', too_short: true, sections: TYPES.map((type) => section(type, [])) }) });
        expect(screen.getByText('Écris au moins 2 caractères.')).toBeTruthy();
        expect(screen.queryByRole('navigation', { name: 'Familles de résultats' })).toBeNull();
        unmountInertia();

        await mountInertia(Index, { url: '/recherche?q=xyz', pageProps: props({ q: 'xyz', sections: TYPES.map((type) => section(type, [])) }) });
        expect(screen.getByText('Aucun résultat. Essaie un autre mot, ou parcours les ressources.')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Parcourir les ressources' }).getAttribute('href')).toBe('/ressources');
        unmountInertia();

        await mountInertia(Index, { url: '/recherche?q=xyz', pageProps: props({ failed: true, sections: [] }) });
        expect(screen.getByRole('alert').textContent).toContain('momentanément indisponible');
    });

    it('en mode discret, cherche par l’API sans jamais mettre le terme dans l’adresse', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        const { calls } = fakeFetch((call) => (call.url.includes('type=')
            ? json({ data: [contact], meta: { type: 'contacts', current_page: 1, last_page: 1, per_page: 10, total: 1, too_short: false } })
            : json({ data: [section(TYPES[0], [resource(1)]), section(TYPES[1], []), section(TYPES[2], [contact]), section(TYPES[3], [])], meta: { too_short: false } })));
        holdTerm('zemidjan');
        await mountInertia(Index, {
            url: '/recherche',
            props: memberProps({ auth: { user: { profile: { discreet_mode: true } } } }),
            pageProps: props({ q: '', sections: TYPES.map((type) => section(type, [])) }),
        });

        await waitFor(() => expect(screen.getAllByRole('heading', { level: 2 }).map((heading) => heading.textContent?.trim())).toEqual(['Ressources', 'Contacts']));
        expect(calls[0].url).toBe('/api/v1/search?q=zemidjan');
        expect((screen.getByRole('searchbox') as HTMLInputElement).value).toBe('zemidjan');
        expect(screen.getByText(/Mode discret/)).toBeTruthy();
        const nav = screen.getByRole('navigation', { name: 'Familles de résultats' });
        expect(within(nav).getByRole('link', { name: /Contacts/ }).getAttribute('href')).toBe('/recherche?type=contacts');

        await fireEvent.click(within(nav).getByRole('link', { name: /Contacts/ }));
        await waitFor(() => expect(within(nav).getByRole('link', { name: /Contacts/ }).getAttribute('aria-current')).toBe('page'));
        expect(calls[1].url).toBe('/api/v1/search?q=zemidjan&type=contacts&page=1');

        await fireEvent.update(screen.getByRole('searchbox'), 'z');
        await fireEvent.submit(screen.getByRole('search'));
        expect(screen.getByText('Écris au moins 2 caractères.')).toBeTruthy();
        expect(calls).toHaveLength(2);
        expect(get).not.toHaveBeenCalled();
    });
});
