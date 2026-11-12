// @vitest-environment jsdom
// P18-12, P18-14 : pages « Proposer une ressource » et « Boîte à idées » (envoi à l'API, erreurs 422 sous les champs,
// confirmation annoncée, PDF seulement pour un document) ; P18-13 : bannière d'annonce globale du gabarit membre
// (prop partagée `announcement`, fermeture envoyée au serveur et mémorisée).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { memberProps, mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import MemberLayout from '@/layouts/MemberLayout.vue';
import ResourceProposal from './ResourceProposal.vue';
import Suggestion from './Suggestion.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const proposalProps = {
    title: 'Proposer une ressource',
    options: {
        kinds: [{ value: 'fiche', label: 'Fiche pratique' }, { value: 'guide', label: 'Guide' }, { value: 'pdf', label: 'Document PDF' }, { value: 'lien', label: 'Lien externe' }],
        categories: [{ value: 3, label: 'Santé' }],
        limits: { title_min: 5, title_max: 150, summary_min: 20, summary_max: 500, file_max_kb: 10240 },
    },
};

describe('Proposer une ressource', () => {
    it('envoie le formulaire en multipart et annonce la confirmation', async () => {
        const { calls } = fakeFetch(() => json({ data: { id: 1, status: 'proposee' } }, 201));
        await mountInertia(ResourceProposal, { url: '/proposer-une-ressource', pageProps: proposalProps });

        await fireEvent.update(screen.getByLabelText(/Titre/), 'Guide des démarches');
        await fireEvent.click(screen.getByLabelText('Lien externe'));
        await fireEvent.update(screen.getByLabelText(/En quelques mots/), 'Un guide utile pour les démarches.');
        await fireEvent.update(document.getElementById('f-external_url') as HTMLInputElement, 'https://exemple.org');
        await fireEvent.click(screen.getByRole('button', { name: /Envoyer ma proposition/ }));
        await flushPromises();

        expect(calls).toHaveLength(1);
        expect(calls[0].url).toBe('/api/v1/resource-proposals');
        const body = calls[0].body as FormData;
        expect(body.get('title')).toBe('Guide des démarches');
        expect(body.get('kind')).toBe('lien');
        expect(body.get('external_url')).toBe('https://exemple.org');
        expect(body.get('file')).toBeNull();
        await waitFor(() => expect(screen.getByRole('status').textContent).toContain('Ta proposition est arrivée'));
    });

    it('demande un PDF pour un document et montre les erreurs du serveur sous les champs', async () => {
        fakeFetch(() => json({ message: 'Données invalides.', errors: { file: ['Le fichier PDF est obligatoire.'], title: ['Titre trop court.'] } }, 422));
        await mountInertia(ResourceProposal, { url: '/proposer-une-ressource', pageProps: proposalProps });

        await fireEvent.click(screen.getByLabelText('Document PDF'));
        expect(screen.getByLabelText('Fichier PDF')).toBeTruthy();
        expect(document.getElementById('f-external_url')).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: /Envoyer ma proposition/ }));
        await flushPromises();

        expect(screen.getByText('Le fichier PDF est obligatoire.')).toBeTruthy();
        expect(screen.getByText('Titre trop court.')).toBeTruthy();
    });
});

describe('Boîte à idées', () => {
    it('envoie la catégorie, le message et l’anonymat', async () => {
        const { calls } = fakeFetch(() => json({ data: { id: 4, status: 'nouvelle' } }, 201));
        await mountInertia(Suggestion, {
            url: '/boite-a-idees',
            pageProps: {
                title: 'Boîte à idées',
                options: { categories: [{ value: 'fonctionnalite', label: 'Fonctionnalité' }, { value: 'securite', label: 'Sécurité' }], limits: { message_min: 10, message_max: 1000 } },
            },
        });

        await fireEvent.click(screen.getByLabelText('Sécurité'));
        await fireEvent.update(screen.getByLabelText(/Ton message/), 'Ajouter un bouton pour quitter vite.');
        await fireEvent.click(screen.getByRole('switch'));
        await fireEvent.click(screen.getByRole('button', { name: /Envoyer mon idée/ }));
        await flushPromises();

        expect(calls[0].url).toBe('/api/v1/suggestions');
        expect(calls[0].body).toEqual({ category: 'securite', message: 'Ajouter un bouton pour quitter vite.', is_anonymous: true });
        await waitFor(() => expect(screen.getByRole('status').textContent).toContain('Ton idée est bien arrivée'));
    });
});

describe('MemberLayout : annonce globale', () => {
    const page = defineComponent({ layout: MemberLayout, render: () => h('div', { class: 'col-lg-9' }, 'Contenu') });
    const announcement = { id: 9, title: 'Atelier ce samedi', message: 'Rejoins-nous.', level: 'important' as const, link: '/evenements', starts_at: null, ends_at: null };

    it('affiche la bannière avec son lien interne et mémorise la fermeture côté serveur', async () => {
        const { calls } = fakeFetch(() => new Response(null, { status: 204 }));
        await mountInertia(page, { props: memberProps({ announcement }) });

        const banner = document.querySelector('[data-xm-announcement]') as HTMLElement;
        expect(banner.textContent).toContain('Atelier ce samedi');
        expect(banner.classList.contains('alert-warning')).toBe(true);
        expect(banner.querySelector('a')?.getAttribute('href')).toBe('/evenements');

        await fireEvent.click(banner.querySelector('.btn-close') as HTMLElement);
        await flushPromises();
        expect(document.querySelector('[data-xm-announcement]')).toBeNull();
        expect(calls.map((call) => `${call.method} ${call.url}`)).toEqual(['POST /api/v1/announcements/9/dismiss']);
    });

    it('n’affiche rien sans annonce', async () => {
        await mountInertia(page, { props: memberProps() });
        expect(document.querySelector('[data-xm-announcement]')).toBeNull();
    });
});
