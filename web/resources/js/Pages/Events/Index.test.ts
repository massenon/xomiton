// @vitest-environment jsdom
// Page /evenements des membres (lot evenements, P8-04) : cartes du prototype events.php (pastille de date, heure · ville,
// participant·es), « Je participe ? » par l'API (carte mise à jour, toast), « Charger plus » par page, modale « Créer un
// événement » chargée à la demande (titre ≥ 3 et date à venir vérifiés avant l'envoi, catégorie envoyée par son id).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { attending, eventOptions, makeEvent } from '@/test/events';
import Index from './Index.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.classList.remove('modal-open');
});

const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));
const page = (data = [makeEvent()], meta = { current_page: 1, last_page: 1 }) => ({ data, meta });
const props = (over: Record<string, unknown> = {}) => ({ title: 'Événements', filter: 'upcoming', categories: [{ slug: 'atelier', label: 'Atelier' }], events: page(), create: eventOptions(), ...over });

describe('Page /evenements', () => {
    it('affiche les cartes et répond « Je participe » par l’API', async () => {
        const event = makeEvent();
        const { calls } = fakeFetch(() => json({ data: attending(event) }));
        await mountInertia(Index, { url: '/evenements', pageProps: props({ events: page([event]) }) });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Événements');
        expect(screen.getByRole('link', { name: 'À venir' }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByText('34 participant·es', { exact: false })).toBeTruthy();
        expect(screen.getByText('nov.')).toBeTruthy();
        expect(document.body.textContent).not.toContain('Maison des jeunes');

        const button = screen.getByRole('button', { name: /Je participe à/ });
        expect(button.getAttribute('aria-pressed')).toBe('false');
        await fireEvent.click(button);
        await waitFor(() => expect(screen.getByRole('button', { name: /Je participe à/ }).getAttribute('aria-pressed')).toBe('true'));
        expect(calls[0]).toMatchObject({ url: `/api/v1/events/${event.id}/rsvp`, method: 'POST', body: { going: true, reminder: true } });
        expect(screen.getByText('35 participant·es', { exact: false })).toBeTruthy();
        // La carte ne montre jamais l'adresse exacte, même livrée par l'API à une participante.
        expect(document.body.textContent).not.toContain('Maison des jeunes');
    });

    it('charge la page suivante sans doublon et affiche l’état vide de « Mes participations »', async () => {
        const next = makeEvent({ id: '01JE1000000000000000000002', title: 'Webinaire : connaître ses droits' });
        const { calls } = fakeFetch(() => json(page([next], { current_page: 2, last_page: 2 })));
        await mountInertia(Index, { url: '/evenements', pageProps: props({ events: page([makeEvent()], { current_page: 1, last_page: 2 }) }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getByText('Webinaire : connaître ses droits')).toBeTruthy());
        expect(calls[0].url).toBe('/api/v1/events?filter=upcoming&page=2');
        expect(screen.queryByRole('button', { name: 'Charger plus' })).toBeNull();

        unmountInertia();
        await mountInertia(Index, { url: '/evenements?filter=going', pageProps: props({ filter: 'going', events: page([]) }) });
        expect(screen.getByText('Tu ne participes à aucun événement à venir.', { exact: false })).toBeTruthy();
    });

    it('crée un événement depuis la modale : titre trop court et date passée refusés sans appel, puis envoi', async () => {
        const created = makeEvent({ id: '01JE1000000000000000000009', title: 'Causerie de Kandi' });
        const { calls } = fakeFetch(() => json({ data: created }, 201));
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        await mountInertia(Index, { url: '/evenements', pageProps: props({ events: page([]) }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Créer un événement' }));
        await waitFor(() => expect(document.querySelector('#modalCreateEvent')).not.toBeNull());
        await shown();
        const form = document.querySelector('#modalCreateEvent form') as HTMLFormElement;
        await fireEvent.update(document.querySelector('#f-title') as HTMLInputElement, 'ab');
        await fireEvent.update(document.querySelector('#f-date') as HTMLInputElement, '2020-01-01');
        await fireEvent.submit(form);
        expect(form.textContent).toContain('Le titre doit contenir au moins 3 caractères.');
        expect(form.textContent).toContain('Choisis une date et une heure à venir.');
        expect(calls).toHaveLength(0);

        await fireEvent.update(document.querySelector('#f-title') as HTMLInputElement, 'Causerie de Kandi');
        await fireEvent.update(document.querySelector('#f-date') as HTMLInputElement, '2030-11-07');
        await fireEvent.update(document.querySelector('#f-place') as HTMLInputElement, 'Kandi');
        await fireEvent.update(document.querySelector('#f-address') as HTMLInputElement, 'Centre des femmes');
        await fireEvent.update(document.querySelector('#f-category') as HTMLSelectElement, 'c5');
        await fireEvent.submit(form);
        await flushPromises();
        expect(calls[0]).toMatchObject({
            url: '/api/v1/events',
            method: 'POST',
            body: { title: 'Causerie de Kandi', starts_at: '2030-11-07T15:00', ends_at: null, place: 'Kandi', address: 'Centre des femmes', is_online: false, online_url: null, category_id: 5, group: null, is_public: false },
        });
        await waitFor(() => expect(visit).toHaveBeenCalledWith(created.url));
        await waitFor(() => expect(document.querySelector('#modalCreateEvent')).toBeNull());
    });
});
