// @vitest-environment jsdom
// Page /groupes/{ulid} (lot groupes-pages, P8-02) : fiche réduite d'un groupe privé (ni onglets Publications /
// Membres, ni description : FR-SOC-05), membre — onglets sans rechargement (?tab=), publications du groupe et « Charger
// plus » par curseur, « Discussion du groupe » vers /messages?c=, quitter avec confirmation ; équipe — demandes en
// attente acceptées depuis l'onglet Membres.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { composerOptions, fakeFetch, json, makePost } from '@/test/feed';
import { GROUP_ID, makeGroup, makeMember, memberOf } from '@/test/community';
import GroupShow from './GroupShow.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));

describe('Page /groupes/{ulid}', () => {
    it('groupe privé d’une non-membre : fiche réduite, demande d’adhésion', async () => {
        const group = makeGroup({ privacy: 'prive', privacy_label: 'Privé', restricted: true, description: null });
        const { calls } = fakeFetch(() => json({ data: { ...group, membership: { status: 'demande', status_label: 'Demande en attente', role: null, role_label: null }, can: { ...group.can, join: false, cancel_request: true } } }));
        await mountInertia(GroupShow, { url: `/groupes/${GROUP_ID}`, pageProps: { title: group.name, tab: 'about', group, posts: null, members: null, events: [], composer: null } });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe(group.name);
        expect(screen.getByText('Groupe privé')).toBeTruthy();
        expect(screen.queryByRole('link', { name: 'Publications' })).toBeNull();
        expect(screen.queryByRole('link', { name: 'Membres' })).toBeNull();
        expect(screen.getByText('Ce groupe est privé. Son contenu est réservé à ses membres.')).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Demander à rejoindre' }));
        await waitFor(() => expect(screen.getByText(/Ta demande est envoyée/)).toBeTruthy());
        expect(calls[0]).toMatchObject({ url: `/api/v1/groups/${GROUP_ID}/join`, method: 'POST' });
    });

    it('membre : publications, suite par curseur, discussion du groupe et départ confirmé', async () => {
        const group = memberOf(makeGroup());
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined as never);
        const { calls } = fakeFetch((call) => {
            if (call.url.includes('/posts')) return json({ data: [makePost({ id: '01JC0000000000000000000002', body: 'Deuxième' })], meta: { next_cursor: null } });
            if (call.url.endsWith('/conversation')) return json({ data: { id: '01JC3000000000000000000001', url: '/messages?c=01JC3000000000000000000001' } }, 201);
            return json({ data: { ...makeGroup(), members_count: 12 } });
        });
        await mountInertia(GroupShow, { url: `/groupes/${GROUP_ID}?tab=posts`, pageProps: {
            title: group.name, tab: 'posts', group,
            posts: { data: [makePost({ body: 'Rendez-vous samedi' })], meta: { next_cursor: 'c2' } },
            members: { data: [makeMember(1, { is_owner: true, role_label: 'Propriétaire' })], hidden_count: 0 },
            events: [{ id: 'e1', title: 'Causerie', href: '/evenements/e1', meta: '20 oct.' }],
            composer: composerOptions({ group: GROUP_ID, visibilities: [{ value: 'public', label: 'Membres du groupe' }] }),
        } });

        expect(screen.getByText('Rendez-vous samedi')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Écrire dans le groupe…' })).toBeTruthy();
        expect(screen.getByText('Causerie')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getByText('Deuxième')).toBeTruthy());
        expect(calls[0].url).toBe(`/api/v1/groups/${GROUP_ID}/posts?cursor=c2`);

        await fireEvent.click(screen.getByRole('link', { name: 'À propos' }));
        expect(screen.getByText('Écoute et entraide entre pair·es.')).toBeTruthy();
        expect(window.location.search).toBe('');

        await fireEvent.click(screen.getByRole('button', { name: 'Discussion du groupe' }));
        await waitFor(() => expect(visit).toHaveBeenCalledWith('/messages?c=01JC3000000000000000000001'));

        await fireEvent.click(screen.getByRole('button', { name: /Membre – Quitter/ }));
        await shown();
        await fireEvent.click(screen.getByRole('button', { name: 'Quitter le groupe' }));
        await flushPromises();
        expect(calls.at(-1)).toMatchObject({ url: `/api/v1/groups/${GROUP_ID}/leave`, method: 'POST' });
        await waitFor(() => expect(reload).toHaveBeenCalled());
        await waitFor(() => expect(document.querySelector('#modalLeaveGroup')).toBeNull());
        expect(screen.getByRole('button', { name: /Rejoindre le groupe/ })).toBeTruthy();
    });

    it('équipe : accepte une demande depuis l’onglet Membres', async () => {
        const group = { ...memberOf(makeGroup(), 'admin'), is_owner: true, pending_requests: 1 };
        const request = makeMember(9, { pseudo: 'Bea', status: 'demande', can: { accept: true, remove: true, change_role: false, make_admin: false } });
        let accepted = false;
        const admitted = makeMember(9, { pseudo: 'Bea', can: { accept: false, remove: true, change_role: true, make_admin: true } });
        const { calls } = fakeFetch((call) => {
            if (call.method === 'GET' && call.url.includes('status=requested')) return json({ data: [request], meta: { current_page: 1, last_page: 1, pending_requests: 1 } });
            if (call.method === 'GET') return json({ data: [makeMember(1, { is_me: true, is_owner: true, role_label: 'Propriétaire' }), ...(accepted ? [admitted] : [])], meta: { current_page: 1, last_page: 1, hidden_count: 0 } });
            accepted = true;
            return json({ data: { ...group, members_count: group.members_count + 1, pending_requests: 0 } });
        });
        await mountInertia(GroupShow, { url: `/groupes/${GROUP_ID}?tab=members`, pageProps: { title: group.name, tab: 'members', group, posts: { data: [], meta: { next_cursor: null } }, members: { data: [], hidden_count: 0 }, events: [], composer: null } });

        await waitFor(() => expect(screen.getByText('Bea')).toBeTruthy());
        await fireEvent.click(screen.getByRole('button', { name: 'Accepter – Bea' }));
        await waitFor(() => expect(screen.getByText('Aucune demande en attente.')).toBeTruthy());
        expect(calls.find((call) => call.method === 'POST')?.url).toBe(`/api/v1/groups/${GROUP_ID}/members/9/accept`);
        // Liste relue après l'acceptation : la nouvelle membre a ses droits (menu Nommer admin…) sans rechargement.
        await waitFor(() => expect(screen.getByRole('button', { name: 'Actions pour Bea' })).toBeTruthy());
        expect(screen.getAllByText('Bea')).toHaveLength(1);
    });
});
