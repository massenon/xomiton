// @vitest-environment jsdom
// Page /groupes (lot groupes-pages, P8-02) : cartes du prototype groups.php (confidentialité, membres · zone),
// Rejoindre (groupe public → « Membre ») et Demander à rejoindre (groupe privé → « Demande envoyée ») par l'API,
// « Charger plus » par page, modale « Créer un groupe » chargée à la demande (nom ≥ 3 vérifié avant l'envoi, zone
// envoyée par son id).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { createOptions, makeGroup, memberOf } from '@/test/community';
import Groups from './Groups.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    document.body.classList.remove('modal-open');
});

/** Modale ouverte et focus piégé (transition Bootstrap terminée). */
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));

const list = (data = [makeGroup()], meta = { current_page: 1, last_page: 1 }) => ({ data, meta });

describe('Page /groupes', () => {
    it('affiche les cartes et rejoint un groupe public', async () => {
        const group = makeGroup();
        const { calls } = fakeFetch(() => json({ data: memberOf(group) }));
        await mountInertia(Groups, { url: '/groupes', pageProps: { title: 'Groupes', tab: 'discover', q: null, groups: list([group]), create: createOptions() } });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Groupes');
        expect(screen.getByText('12 membres', { exact: false })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Découvrir' }).getAttribute('aria-current')).toBe('page');

        await fireEvent.click(screen.getByRole('button', { name: /Rejoindre/ }));
        await waitFor(() => expect(screen.getByRole('link', { name: /Membre/ })).toBeTruthy());
        expect(calls[0]).toMatchObject({ url: `/api/v1/groups/${group.id}/join`, method: 'POST' });
        expect(screen.getByText('13 membres', { exact: false })).toBeTruthy();
    });

    it('demande à rejoindre un groupe privé et charge la page suivante', async () => {
        const secret = makeGroup({ id: '01JC1000000000000000000002', name: 'Cercle privé', privacy: 'prive', privacy_label: 'Privé', restricted: true, description: null });
        const next = makeGroup({ id: '01JC1000000000000000000003', name: 'Cercle de Parakou' });
        const { calls } = fakeFetch((call) => (call.method === 'GET'
            ? json(list([next], { current_page: 2, last_page: 2 }))
            : json({ data: { ...secret, membership: { status: 'demande', status_label: 'Demande en attente', role: null, role_label: null }, can: { ...secret.can, join: false, cancel_request: true } } })));
        await mountInertia(Groups, { url: '/groupes', pageProps: { title: 'Groupes', groups: list([secret], { current_page: 1, last_page: 2 }), create: createOptions() } });

        await fireEvent.click(screen.getByRole('button', { name: /Demander à rejoindre/ }));
        await waitFor(() => expect(screen.getByRole('button', { name: /Demande envoyée/ })).toBeTruthy());

        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getByText('Cercle de Parakou')).toBeTruthy());
        expect(calls[1].url).toBe('/api/v1/groups?tab=discover&page=2');
        expect(screen.queryByRole('button', { name: 'Charger plus' })).toBeNull();
    });

    it('crée un groupe depuis la modale : nom trop court refusé sans appel, puis envoi', async () => {
        const created = makeGroup({ id: '01JC1000000000000000000009', name: 'Cercle de Kandi', privacy: 'prive' });
        const { calls } = fakeFetch(() => json({ data: created }, 201));
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        await mountInertia(Groups, { url: '/groupes', pageProps: { title: 'Groupes', groups: list([]), create: createOptions() } });
        expect(screen.getByText('Aucun groupe trouvé.')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Créer un groupe' }));
        await waitFor(() => expect(document.querySelector('#modalCreateGroup')).not.toBeNull());
        await shown();
        const name = document.querySelector('#f-name') as HTMLInputElement;
        await fireEvent.update(name, 'ab');
        await fireEvent.submit(document.querySelector('#modalCreateGroup form') as HTMLFormElement);
        expect(document.querySelector('#modalCreateGroup')?.textContent).toContain('Le nom du groupe doit contenir au moins 3 caractères.');
        expect(calls).toHaveLength(0);

        await fireEvent.update(name, 'Cercle de Kandi');
        await fireEvent.update(document.querySelector('#f-zone_id') as HTMLSelectElement, 'z12');
        await fireEvent.submit(document.querySelector('#modalCreateGroup form') as HTMLFormElement);
        await flushPromises();
        expect(calls[0]).toMatchObject({ url: '/api/v1/groups', method: 'POST', body: { name: 'Cercle de Kandi', description: null, privacy: 'prive', zone_id: 12 } });
        await waitFor(() => expect(visit).toHaveBeenCalledWith(created.url));
        await waitFor(() => expect(document.querySelector('#modalCreateGroup')).toBeNull());
    });
});
