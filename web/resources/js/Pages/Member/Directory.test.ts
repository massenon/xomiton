// @vitest-environment jsdom
// Page /annuaire (lot annuaire-membre, P8-12 ; prototype annuaire.php) : cartes de contact (Appeler tel:, WhatsApp,
// E-mail, fiche réservée au réseau sans demande de rappel), filtres (visite partielle, commune remise à zéro avec le
// département), carte par département (lien ?zone=, visite partielle), « Charger plus » par l'API, modale « Demander à
// être rappelé·e » chargée à la demande (contact obligatoire vérifié avant l'envoi, Idempotency-Key gardée au nouvel
// essai, code de suivi annoncé, erreurs 422 du serveur : champ, résumé, refus `contact_unavailable`).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import type { DirectoryContact } from '@/directory/types';
import Directory from './Directory.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.classList.remove('modal-open');
});

const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));

function contact(id: number, overrides: Partial<DirectoryContact> = {}): DirectoryContact {
    return {
        id,
        name: `HOUNSOU Awa ${id}`,
        role: 'juriste',
        role_label: 'Juriste',
        role_color: 'info',
        org: 'Clinique juridique',
        phone: '+229 01 97 00 00 0' + id,
        tel: `tel:+22901970000${id}`,
        whatsapp: '+229 01 97 00 00 0' + id,
        whatsapp_url: `https://wa.me/22901970000${id}`,
        email: `contact${id}@exemple.bj`,
        availability: 'Lun-Ven 8h-17h',
        zone: { id: 4, name: 'Zou' },
        commune: { id: 41, name: 'Abomey' },
        national: false,
        is_public: true,
        can: { callback: true },
        ...overrides,
    };
}

const base = (overrides: Record<string, unknown> = {}) => ({
    title: 'Annuaire',
    reach: 'public',
    filters: { q: null, role: null, zone: null, commune: null },
    roles: [{ value: 'juriste', label: 'Juriste' }, { value: 'psychologue', label: 'Psychologue' }],
    zones: [
        { id: 4, name: 'Zou', communes: [{ id: 41, name: 'Abomey' }, { id: 42, name: 'Bohicon' }] },
        { id: 8, name: 'Littoral', communes: [{ id: 81, name: 'Cotonou' }] },
    ],
    contacts: { data: [contact(1), contact(2, { zone: { id: 4, name: 'Zou' }, commune: { id: 41, name: 'Abomey' }, national: true, role: 'point_focal', role_label: 'Point focal', role_color: 'primary', email: null })], meta: { current_page: 1, last_page: 1, per_page: 24, total: 2 } },
    map: { zones: [{ id: 4, name: 'Zou', slug: 'zou', count: 1 }, { id: 8, name: 'Littoral', slug: 'littoral', count: 0 }], national: 1, total: 2 },
    limits: { search_max: 80, message_max: 1000, contact_max: 190 },
    ...overrides,
});

describe('Page /annuaire', () => {
    it('affiche les fiches : appeler, WhatsApp, e-mail, lieu ou national, et le nombre de résultats', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Directory, { url: '/annuaire', pageProps: base() });

        expect(screen.getByRole('heading', { level: 1, name: 'Annuaire dynamique' })).toBeTruthy();
        expect(screen.getByRole('link', { name: /Urgences/ }).getAttribute('href')).toBe('/urgences');
        const first = screen.getByRole('article', { name: 'HOUNSOU Awa 1' });
        expect(within(first).getByRole('link', { name: /Appeler HOUNSOU Awa 1/ }).getAttribute('href')).toBe('tel:+229019700001');
        const whatsapp = within(first).getByRole('link', { name: /WhatsApp HOUNSOU Awa 1/ });
        expect(whatsapp.getAttribute('href')).toBe('https://wa.me/229019700001');
        expect(whatsapp.getAttribute('target')).toBe('_blank');
        expect(within(first).getByRole('link', { name: /E-mail/ }).getAttribute('href')).toBe('mailto:contact1@exemple.bj');
        expect(first.textContent).toContain('Abomey, Zou');
        // Point focal national rattaché à un département : « National », comme /urgences (champ `national`).
        const second = screen.getByRole('article', { name: 'HOUNSOU Awa 2' });
        expect(second.textContent).toContain('National');
        expect(second.textContent).not.toContain('Abomey, Zou');
        expect(within(second).queryByRole('link', { name: /E-mail/ })).toBeNull();
        // Rouge réservé à l'urgence (Q62) : couleur `role_color` faite par le serveur, appliquée telle quelle.
        expect(within(second).getByText('Point focal').className).toContain('text-primary');
        expect(screen.getByText('2 résultats')).toBeTruthy();
        expect(screen.getByText(/Bénin · par département · National : 1 contact/)).toBeTruthy();
    });

    it('marque une fiche réservée au réseau, sans demande de rappel, et le bandeau de discrétion', async () => {
        fakeFetch(() => json({}));
        await mountInertia(Directory, { url: '/annuaire', pageProps: base({ reach: 'network', contacts: { data: [contact(3, { is_public: false, can: { callback: false } })], meta: { current_page: 1, last_page: 1, per_page: 24, total: 1 } } }) });

        const card = screen.getByRole('article', { name: 'HOUNSOU Awa 3' });
        expect(within(card).getByText('Réservé au réseau')).toBeTruthy();
        expect(within(card).queryByRole('button', { name: /Demander à être rappelé·e/ })).toBeNull();
        expect(card.textContent).toContain('Pas de demande de rappel pour un contact réservé au réseau.');
        expect(screen.getByText(/contacts réservés au réseau/)).toBeTruthy();
    });

    it('filtre par recherche, rôle, département et commune (visite partielle) ; la commune suit le département', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({}));
        await mountInertia(Directory, { url: '/annuaire', pageProps: base() });

        const communeSelect = screen.getByRole('combobox', { name: 'Commune' }) as HTMLSelectElement;
        expect(communeSelect.disabled).toBe(true);
        await fireEvent.update(screen.getByRole('searchbox', { name: 'Recherche' }), ' clinique ');
        await fireEvent.update(screen.getByRole('combobox', { name: 'Rôle' }), 'juriste');
        await fireEvent.update(screen.getByRole('combobox', { name: 'Département' }), '4');
        await fireEvent.update(communeSelect, '42');
        await fireEvent.click(screen.getByRole('button', { name: 'Filtrer' }));
        expect(get).toHaveBeenLastCalledWith('/annuaire', { q: 'clinique', role: 'juriste', zone: '4', commune: '42' }, expect.objectContaining({ only: ['filters', 'contacts', 'map'], preserveState: true }));

        await fireEvent.update(screen.getByRole('combobox', { name: 'Département' }), '8');
        expect(communeSelect.value).toBe('');
        await fireEvent.click(screen.getByRole('link', { name: 'Réinitialiser' }));
        expect(get).toHaveBeenLastCalledWith('/annuaire', {}, expect.anything());
    });

    it('choisit un département sur la carte (lien ?zone= gardant les filtres appliqués)', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({}));
        await mountInertia(Directory, { url: '/annuaire?role=juriste', pageProps: base({ filters: { q: null, role: 'juriste', zone: null, commune: null } }) });

        const pin = screen.getByRole('link', { name: /^Zou\s: 1 contact$/ });
        expect(pin.getAttribute('href')).toBe('/annuaire?role=juriste&zone=4');
        expect(screen.queryByRole('link', { name: /Littoral/ })).toBeNull();
        await fireEvent.click(pin);
        expect(get).toHaveBeenLastCalledWith('/annuaire', { role: 'juriste', zone: '4' }, expect.anything());
    });

    it('charge la suite par l’API sans doublon', async () => {
        const { calls } = fakeFetch(() => json({ data: [contact(2), contact(5)], meta: { current_page: 2, last_page: 2, per_page: 24, total: 3 } }));
        await mountInertia(Directory, { url: '/annuaire', pageProps: base({ filters: { q: 'awa', role: null, zone: 4, commune: null }, contacts: { data: [contact(1), contact(2)], meta: { current_page: 1, last_page: 2, per_page: 24, total: 3 } } }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getByRole('article', { name: 'HOUNSOU Awa 5' })).toBeTruthy());
        expect(screen.getAllByRole('article')).toHaveLength(3);
        expect(calls[0].url).toBe('/api/v1/directory?q=awa&zone=4&page=2');
        expect(screen.queryByRole('button', { name: 'Charger plus' })).toBeNull();
    });

    it('demande un rappel : contact obligatoire, même clé au nouvel essai, code de suivi annoncé', async () => {
        let attempts = 0;
        const { calls } = fakeFetch(() => {
            attempts += 1;
            if (attempts === 1) return Promise.reject(new TypeError('Failed to fetch'));
            return json({ data: { id: '01JC', tracking_code: 'ABCDE23456', tracking_code_display: 'ABCDE-23456', routed: { role: 'Juriste', name: 'HOUNSOU Awa 1' } } }, 201);
        });
        await mountInertia(Directory, { url: '/annuaire', pageProps: base() });

        const card = screen.getByRole('article', { name: 'HOUNSOU Awa 1' });
        await fireEvent.click(within(card).getByRole('button', { name: /Demander à être rappelé·e/ }));
        await waitFor(() => expect(document.querySelector('#modalCallback')).not.toBeNull());
        await shown();
        const modal = document.querySelector('#modalCallback') as HTMLElement;
        expect(modal.textContent).toContain('Tu seras rappelé·e par HOUNSOU Awa 1 (Juriste).');
        expect((modal.querySelector('#f-anonymous') as HTMLInputElement).checked).toBe(true);

        await fireEvent.submit(modal.querySelector('form') as HTMLFormElement);
        expect(modal.textContent).toContain('Indique un numéro ou une adresse pour être rappelé·e.');
        expect(calls).toHaveLength(0);

        await fireEvent.update(modal.querySelector('#f-contact') as HTMLInputElement, ' +229 01 97 38 88 47 ');
        await fireEvent.submit(modal.querySelector('form') as HTMLFormElement);
        await flushPromises();
        await waitFor(() => expect(modal.textContent).toContain('Pas de connexion'));
        await fireEvent.submit(modal.querySelector('form') as HTMLFormElement);
        await flushPromises();

        expect(calls).toHaveLength(2);
        expect(calls[1]).toMatchObject({ url: '/api/v1/directory/1/callback', method: 'POST', body: { contact: '+229 01 97 38 88 47', message: null, anonymous: true } });
        expect(calls[1].headers['Idempotency-Key']).toMatch(/^[0-9a-f-]{36}$/);
        expect(calls[1].headers['Idempotency-Key']).toBe(calls[0].headers['Idempotency-Key']);
        await waitFor(() => expect(document.querySelector('#modalCallback')).toBeNull());
        const status = screen.getByText('Demande envoyée. HOUNSOU Awa 1 te recontactera.').closest('[role="status"]') as HTMLElement;
        expect(status.textContent).toContain('ABCDE-23456');
    });

    it('affiche les erreurs du serveur (422) dans la modale : champ, résumé « Ta demande… », refus contact_unavailable', async () => {
        let attempts = 0;
        fakeFetch(() => {
            attempts += 1;
            if (attempts === 1) return json({ message: 'Les données envoyées sont invalides.', code: 'validation_failed', errors: { contact: ['Indique un numéro de téléphone ou une adresse e-mail valable.'] } }, 422);
            return json({ message: 'Cette personne n’est plus disponible dans l’annuaire.', code: 'contact_unavailable' }, 422);
        });
        await mountInertia(Directory, { url: '/annuaire', pageProps: base() });

        await fireEvent.click(within(screen.getByRole('article', { name: 'HOUNSOU Awa 1' })).getByRole('button', { name: /Demander à être rappelé·e/ }));
        await waitFor(() => expect(document.querySelector('#modalCallback')).not.toBeNull());
        await shown();
        const modal = document.querySelector('#modalCallback') as HTMLElement;
        expect(modal.textContent).toContain('Téléphone ou e-mail, utilisé uniquement pour ce rappel.');

        await fireEvent.update(modal.querySelector('#f-contact') as HTMLInputElement, 'pas un numéro');
        await fireEvent.submit(modal.querySelector('form') as HTMLFormElement);
        await flushPromises();
        await waitFor(() => expect(modal.textContent).toContain('Ta demande n’est pas encore partie\u00a0:'));
        expect(modal.textContent).not.toContain('publication');
        expect(modal.textContent).toContain('Indique un numéro de téléphone ou une adresse e-mail valable.');
        expect((modal.querySelector('#f-contact') as HTMLInputElement).getAttribute('aria-invalid')).toBe('true');

        await fireEvent.update(modal.querySelector('#f-contact') as HTMLInputElement, '+229 01 97 38 88 47');
        await fireEvent.submit(modal.querySelector('form') as HTMLFormElement);
        await flushPromises();
        await waitFor(() => expect(modal.querySelector('.alert-danger')?.textContent).toContain('Cette personne n’est plus disponible dans l’annuaire.'));
        expect(document.querySelector('#modalCallback')).not.toBeNull();
    });
});
