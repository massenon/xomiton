// @vitest-environment jsdom
// Page /qui-suivre (lot qui-suivre, P8-06 ; prototype who-to-follow.php) : onglets et compteurs, suggestions (rôle ·
// zone, inviter, suivre / ne plus suivre), filtres (visite partielle Inertia), invitations reçues (accepter, refuser),
// envoyées (annuler), ?invite= : confirmation avant tout envoi.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { makeInvitation, makeSuggestion, paged } from '@/test/network';
import WhoToFollow from './WhoToFollow.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const base = (overrides: Record<string, unknown> = {}) => ({
    title: 'Qui suivre et invitations',
    tab: 'suggestions',
    filters: { q: null, zone: null, role: null },
    zones: [{ id: 4, name: 'Zou' }, { id: 5, name: 'Borgou' }],
    roles: ['membre', 'pair', 'equipe'],
    counts: { received: 2, sent: 0 },
    suggestions: paged([makeSuggestion(7, { pseudo: 'mariam-bj' }), makeSuggestion(8, { pseudo: 'nadia', zone: null, role: 'peer', role_label: 'Pair animateur·rice', can: { follow: false, invite: true } })]),
    invitations: paged([]),
    invite: null,
    can: { access: true, invite: true, follow: true },
    ...overrides,
});

describe('Page /qui-suivre — suggestions', () => {
    it('affiche onglets, compteurs et cartes, puis invite sans recharger', async () => {
        const invitation = makeInvitation(1, 'sent', { person: { ...makeSuggestion(7, { pseudo: 'mariam-bj' }) } });
        const { calls } = fakeFetch(() => json({ data: invitation, meta: { created: true, counts: { received: 2, sent: 1 } } }, 201));
        await mountInertia(WhoToFollow, { url: '/qui-suivre', pageProps: base() });

        expect(screen.getByRole('link', { name: /Suggestions/ }).getAttribute('aria-current')).toBe('page');
        expect(screen.getByRole('link', { name: /Invitations reçues/ }).getAttribute('href')).toBe('/qui-suivre?tab=recues');
        expect(screen.getByRole('link', { name: /Invitations reçues/ }).textContent).toContain('2 en attente');
        expect(screen.getByText('Membre · Zou')).toBeTruthy();
        expect(screen.getByText('Pair animateur·rice')).toBeTruthy();
        expect(screen.getAllByRole('button', { name: /^Suivre/ })).toHaveLength(1);

        await fireEvent.click(screen.getByRole('button', { name: 'Envoyer une invitation à mariam-bj' }));
        await waitFor(() => expect((screen.getByRole('button', { name: /Invitation envoyée/ }) as HTMLButtonElement).disabled).toBe(true));
        expect(calls[0]).toMatchObject({ url: '/api/v1/invitations', method: 'POST', body: { user_id: 7 } });
        expect(screen.getByRole('link', { name: /Invitations envoyées/ }).textContent).toContain('1 en attente');
    });

    it('suit puis ne suit plus une personne', async () => {
        const { calls } = fakeFetch((call) => json({ data: { user_id: 7, following: call.method === 'POST', changed: true } }));
        await mountInertia(WhoToFollow, { url: '/qui-suivre', pageProps: base() });

        await fireEvent.click(screen.getByRole('button', { name: 'Suivre mariam-bj' }));
        await waitFor(() => expect(screen.getByRole('button', { name: 'Ne plus suivre mariam-bj' }).textContent).toContain('Abonné·e'));
        await fireEvent.click(screen.getByRole('button', { name: 'Ne plus suivre mariam-bj' }));
        await waitFor(() => expect(screen.getByRole('button', { name: 'Suivre mariam-bj' })).toBeTruthy());
        expect(calls.map((call) => [call.method, call.url])).toEqual([['POST', '/api/v1/follows'], ['DELETE', '/api/v1/follows/7']]);
    });

    it('filtre par pseudonyme, zone et profil (visite partielle, adresse gardée)', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({}));
        await mountInertia(WhoToFollow, { url: '/qui-suivre', pageProps: base() });

        await fireEvent.update(screen.getByRole('searchbox', { name: 'Rechercher' }), ' mar ');
        await fireEvent.update(screen.getByRole('combobox', { name: 'Zone' }), '4');
        await fireEvent.update(screen.getByRole('combobox', { name: 'Profil' }), 'pair');
        await fireEvent.click(screen.getByRole('button', { name: 'Filtrer' }));

        expect(get).toHaveBeenCalledWith('/qui-suivre', { q: 'mar', zone: '4', role: 'pair' }, expect.objectContaining({ only: expect.arrayContaining(['suggestions', 'filters']) }));
    });

    it('charge la suite par curseur, sans trou après un geste, puis rend le focus au premier ajout', async () => {
        const invitation = makeInvitation(1, 'sent', { person: makeSuggestion(7) });
        const { calls } = fakeFetch((call) => (call.method === 'POST'
            ? json({ data: invitation, meta: { created: true, counts: { received: 2, sent: 1 } } }, 201)
            : json(paged([makeSuggestion(9, { pseudo: 'zoe' })]))));
        await mountInertia(WhoToFollow, { url: '/qui-suivre', pageProps: base({ suggestions: paged([makeSuggestion(7, { pseudo: 'mariam-bj' })], 'curseur-1') }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Envoyer une invitation à mariam-bj' }));
        await waitFor(() => expect(screen.getByRole('button', { name: /Invitation envoyée/ })).toBeTruthy());
        const more = screen.getByRole('button', { name: 'Charger plus' });
        more.focus();
        await fireEvent.click(more);

        await waitFor(() => expect(screen.getByText('zoe')).toBeTruthy());
        expect(calls[1].url).toBe('/api/v1/follows/suggestions?cursor=curseur-1');
        expect(screen.queryByRole('button', { name: 'Charger plus' })).toBeNull();
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Envoyer une invitation à zoe' })));
    });

    it('laisse avancer deux gestes sur deux cartes à la fois', async () => {
        const pending: ((response: Response) => void)[] = [];
        const { calls } = fakeFetch(() => new Promise<Response>((resolve) => { pending.push(resolve); }));
        await mountInertia(WhoToFollow, { url: '/qui-suivre', pageProps: base() });

        await fireEvent.click(screen.getByRole('button', { name: 'Envoyer une invitation à mariam-bj' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Envoyer une invitation à nadia' }));
        await waitFor(() => expect(calls).toHaveLength(2));
        expect((screen.getByRole('button', { name: 'Envoyer une invitation à mariam-bj' }) as HTMLButtonElement).disabled).toBe(true);
        pending.forEach((resolve, index) => resolve(json({ data: makeInvitation(index + 1, 'sent'), meta: { created: true, counts: { received: 2, sent: index + 1 } } }, 201)));
        await waitFor(() => expect(screen.getAllByRole('button', { name: /Invitation envoyée/ })).toHaveLength(2));
    });

    it('n’affiche pas « Envoyer une invitation » sans e-mail confirmé', async () => {
        fakeFetch(() => json({}));
        await mountInertia(WhoToFollow, { url: '/qui-suivre', pageProps: base({ can: { access: true, invite: false, follow: true } }) });

        expect(screen.queryByRole('button', { name: /Envoyer une invitation/ })).toBeNull();
        expect(screen.getByText('Confirme ton adresse e-mail pour envoyer des invitations.')).toBeTruthy();
    });
});

describe('Page /qui-suivre — invitations', () => {
    it('accepte et refuse une invitation reçue, compteurs repris du serveur', async () => {
        const { calls } = fakeFetch((call) => json({ data: makeInvitation(call.url.includes('/1/') ? 1 : 2, 'received', { status: call.url.endsWith('accept') ? 'acceptee' : 'refusee' }), meta: { counts: { received: call.url.includes('/1/') ? 1 : 0, sent: 0 } } }));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?tab=recues', pageProps: base({ tab: 'received', invitations: paged([makeInvitation(1, 'received'), makeInvitation(2, 'received')]) }) });

        expect(screen.getAllByText('Pair animateur·rice · Zou · il y a 2 h')).toHaveLength(2);
        await fireEvent.click(screen.getByRole('button', { name: 'Accepter l’invitation de personne-1' }));
        await waitFor(() => expect(screen.queryByText('personne-1')).toBeNull());
        await fireEvent.click(screen.getByRole('button', { name: 'Refuser l’invitation de personne-2' }));
        await waitFor(() => expect(screen.getByText('Aucune invitation en attente.')).toBeTruthy());

        expect(calls.map((call) => [call.method, call.url])).toEqual([['POST', '/api/v1/invitations/1/accept'], ['POST', '/api/v1/invitations/2/refuse']]);
        expect(screen.getByRole('link', { name: /Invitations reçues/ }).textContent).toContain('aucune en attente');
    });

    it('rend le focus à la ligne suivante, puis au titre quand la liste se vide', async () => {
        fakeFetch((call) => json({ data: makeInvitation(call.url.includes('/1/') ? 1 : 2, 'received', { status: 'acceptee' }), meta: { counts: { received: 1, sent: 0 } } }));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?tab=recues', pageProps: base({ tab: 'received', invitations: paged([makeInvitation(1, 'received'), makeInvitation(2, 'received')]) }) });

        const first = screen.getByRole('button', { name: 'Accepter l’invitation de personne-1' });
        first.focus();
        await fireEvent.click(first);
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('button', { name: 'Accepter l’invitation de personne-2' })));

        await fireEvent.click(screen.getByRole('button', { name: 'Accepter l’invitation de personne-2' }));
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('heading', { level: 1 })));
    });

    it('charge les invitations suivantes par curseur après une réponse, sans en sauter', async () => {
        const { calls } = fakeFetch((call) => (call.method === 'POST'
            ? json({ data: makeInvitation(1, 'received', { status: 'refusee' }), meta: { counts: { received: 2, sent: 0 } } })
            : json({ ...paged([makeInvitation(3, 'received')]), meta: { next_cursor: null, counts: { received: 2, sent: 0 } } })));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?tab=recues', pageProps: base({ tab: 'received', counts: { received: 3, sent: 0 }, invitations: paged([makeInvitation(1, 'received'), makeInvitation(2, 'received')], 'curseur-2') }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Refuser l’invitation de personne-1' }));
        await waitFor(() => expect(screen.queryByText('personne-1')).toBeNull());
        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));

        await waitFor(() => expect(screen.getByText('personne-3')).toBeTruthy());
        expect(calls[1].url).toBe('/api/v1/invitations?box=received&cursor=curseur-2');
        expect(screen.getByText('personne-2')).toBeTruthy();
    });

    it('annule une invitation envoyée puis propose les suggestions', async () => {
        const { calls } = fakeFetch(() => new Response(null, { status: 204 }));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?tab=envoyees', pageProps: base({ tab: 'sent', counts: { received: 0, sent: 1 }, invitations: paged([makeInvitation(3, 'sent')]) }) });

        expect(screen.getByText('Pair animateur·rice · envoyée il y a 2 h')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Annuler l’invitation envoyée à personne-3' }));
        await waitFor(() => expect(screen.getByText(/Aucune invitation envoyée/)).toBeTruthy());
        expect(calls[0]).toMatchObject({ url: '/api/v1/invitations/3', method: 'DELETE' });
        expect(screen.getByRole('link', { name: 'Découvre les suggestions' }).getAttribute('href')).toBe('/qui-suivre');
        expect(screen.getByRole('link', { name: /Invitations envoyées/ }).textContent).toContain('aucune en attente');
    });

    it('retire une invitation devenue introuvable (annulée ou bloquée entre-temps)', async () => {
        fakeFetch(() => json({ message: 'Introuvable.', code: 'not_found' }, 404));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?tab=recues', pageProps: base({ tab: 'received', invitations: paged([makeInvitation(1, 'received')]) }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Accepter l’invitation de personne-1' }));
        await waitFor(() => expect(screen.getByText('Aucune invitation en attente.')).toBeTruthy());
    });
});

describe('Page /qui-suivre?invite=', () => {
    it('demande confirmation : rien n’est envoyé avant le geste', async () => {
        const person = makeSuggestion(9, { pseudo: 'awa-zou' });
        const { calls } = fakeFetch(() => json({ data: makeInvitation(5, 'sent', { person }), meta: { created: true, counts: { received: 2, sent: 1 } } }, 201));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?invite=9', pageProps: base({ invite: { status: 'ready', person } }) });

        const heading = screen.getByRole('heading', { name: /^Envoyer une invitation à awa-zou\s\?$/ });
        expect(document.activeElement).toBe(heading);
        expect(calls).toHaveLength(0);

        await fireEvent.click(screen.getByRole('button', { name: /Envoyer l’invitation/ }));
        await waitFor(() => expect(screen.queryByRole('heading', { name: /Envoyer une invitation à awa-zou/ })).toBeNull());
        expect(calls[0]).toMatchObject({ url: '/api/v1/invitations', method: 'POST', body: { user_id: 9 } });
    });

    it('rend le focus au titre quand on ferme le panneau', async () => {
        fakeFetch(() => json({}));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?invite=9', pageProps: base({ invite: { status: 'ready', person: makeSuggestion(9, { pseudo: 'awa-zou' }) } }) });

        const dismiss = screen.getByRole('button', { name: 'Pas maintenant' });
        dismiss.focus();
        await fireEvent.click(dismiss);
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('heading', { level: 1 })));
        expect(screen.queryByRole('heading', { name: /Envoyer une invitation à awa-zou/ })).toBeNull();
    });

    it('annonce sobrement une invitation impossible, sans nommer la personne', async () => {
        fakeFetch(() => json({}));
        await mountInertia(WhoToFollow, { url: '/qui-suivre?invite=99', pageProps: base({ invite: { status: 'unavailable', person: null } }) });

        expect(screen.getByText('Cette personne ne peut pas être invitée pour le moment.')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Envoyer l’invitation/ })).toBeNull();
    });
});
