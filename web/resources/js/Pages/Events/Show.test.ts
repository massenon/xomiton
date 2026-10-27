// @vitest-environment jsdom
// Fiche /evenements/{ulid} des membres (lot evenements, P8-04) : adresse exacte absente avant « Je participe » (mention
// « partagée avec les participant·es »), livrée par l'API après la réponse ; annuler ; rappel de la veille ; événement
// passé (badge, pas de bouton) ; nombre de participant·es sans leur liste.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { attending, makeEvent } from '@/test/events';
import { useUiStore } from '@/stores/ui';
import Show from './Show.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
});

const others = [{ id: '01JE1000000000000000000005', title: 'Séance de self-défense', href: '/evenements/01JE1000000000000000000005', image_url: '/x.jpg', meta: '24 octobre · Cotonou' }];

describe('Fiche /evenements/{ulid}', () => {
    it('révèle l’adresse exacte seulement après « Je participe », puis annule', async () => {
        const event = makeEvent();
        const { calls } = fakeFetch((call) => json({ data: (call.body as { going: boolean }).going ? attending(event) : { ...event, going_count: 34 } }));
        await mountInertia(Show, { url: `/evenements/${event.id}`, pageProps: { title: event.title, event, others } });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(event.title);
        expect(screen.getByText('L’adresse exacte est partagée avec les participant·es.')).toBeTruthy();
        expect(document.body.textContent).not.toContain('Maison des jeunes');
        expect(screen.getByText('Par discrétion', { exact: false })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Séance de self-défense' })).toBeTruthy();

        const button = screen.getByRole('button', { name: `Je participe à « ${event.title} »` });
        expect(button.hasAttribute('aria-pressed')).toBe(false);
        await fireEvent.click(button);
        await waitFor(() => expect(document.querySelector('[data-xm-exact-place]')?.textContent).toContain('Maison des jeunes, salle 3'));
        expect(calls[0]).toMatchObject({ url: `/api/v1/events/${event.id}/rsvp`, body: { going: true, reminder: true } });
        expect(screen.getByRole('switch', { name: 'Me rappeler l’événement la veille' })).toBeTruthy();
        expect(screen.getByText('35 participant·es')).toBeTruthy();

        const cancel = screen.getByRole('button', { name: `Annuler ma participation à « ${event.title} »` });
        expect(cancel.textContent).toContain('Je participe – annuler');
        await fireEvent.click(cancel);
        await waitFor(() => expect(document.querySelector('[data-xm-exact-place]')).toBeNull());
        expect(calls[1]).toMatchObject({ body: { going: false } });
    });

    it('retire le rappel de la veille sans annuler la participation', async () => {
        const event = attending(makeEvent());
        const { calls } = fakeFetch(() => json({ data: { ...event, rsvp: { status: 'going', reminder: false } } }));
        await mountInertia(Show, { url: `/evenements/${event.id}`, pageProps: { title: event.title, event } });

        const toggle = screen.getByRole('switch', { name: 'Me rappeler l’événement la veille' }) as HTMLInputElement;
        expect(toggle.checked).toBe(true);
        await fireEvent.click(toggle);
        await waitFor(() => expect(calls).toHaveLength(1));
        expect(calls[0]).toMatchObject({ body: { going: true, reminder: false } });
    });

    it('ne propose pas le rappel quand il est déjà parti, et ne le promet pas', async () => {
        const event = makeEvent({ reminder_available: false });
        fakeFetch(() => json({ data: attending(event) }));
        await mountInertia(Show, { url: `/evenements/${event.id}`, pageProps: { title: event.title, event } });

        await fireEvent.click(screen.getByRole('button', { name: /Je participe à/ }));
        await waitFor(() => expect(document.querySelector('[data-xm-exact-place]')).not.toBeNull());
        expect(screen.queryByRole('switch')).toBeNull();
        await waitFor(() => expect(useUiStore().toasts.map((toast) => toast.message)).toContain('Inscription confirmée.'));
    });

    it('affiche « Événement passé » sans bouton de participation', async () => {
        const event = makeEvent({ is_past: true, can: { rsvp: false, cancel: false, update: false } });
        await mountInertia(Show, { url: `/evenements/${event.id}`, pageProps: { title: event.title, event } });

        expect(screen.getByText('Événement passé')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Je participe/ })).toBeNull();
    });
});
