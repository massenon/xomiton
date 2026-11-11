// @vitest-environment jsdom
// Page /fil (lot fil-ecrans, P7-03) : accès rapides SOS · Signaler · Assistant · Urgences, carte « Créer une
// publication » (pastille Événement désactivée avec mention, Sondage selon polls.create, modales chargées à la
// demande), « À la une », filtres (lien actif, visite partielle `only: feed, tag`, squelettes), liste par curseur avec
// « Charger plus » (GET /api/v1/feed), fin de liste, état vide, colonne droite ;
// publication mise en file envoyée au rejeu (annonce, fil relu) ou refusée (modale rouverte avec contenu et erreurs).
import 'fake-indexeddb/auto';
import { openDB } from 'idb';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { composerOptions, fakeFetch, json, makePost } from '@/test/feed';
import { eventOptions } from '@/test/events';
import { DRAFT_POST } from '@/feed/composer';
import { DRAFT_STORE, setDraftOpener, useDraftsStore } from '@/stores/drafts';
import { useOutboxStore, type FlushDetail } from '@/stores/outbox';
import { useUiStore } from '@/stores/ui';
import Index from './Index.vue';

beforeEach(() => {
    const name = `xomiton-drafts-${Math.random().toString(36).slice(2)}`;
    setDraftOpener(() => openDB(name, 1, { upgrade(db) { db.createObjectStore(DRAFT_STORE); } }));
});

afterEach(() => {
    setDraftOpener(null);
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
    document.body.className = '';
});

const first = makePost({ id: '01JC0000000000000000000001' });
const second = makePost({ id: '01JC0000000000000000000002', tag: 'question', tag_label: 'Question', body: 'Une question' });

const mount = (pageProps: Record<string, unknown> = {}, url = '/fil') => mountInertia(Index, {
    url,
    pageProps: {
        title: 'Fil d’actualité',
        tag: null,
        feed: { data: [first, second], meta: { next_cursor: 'c2', per_page: 2 } },
        featured: [{ slug: 'consentement', title: 'Consentement et relations', href: '/thematiques/consentement', cover_url: '/assets/images/thematiques/couverture.jpg', category: 'Santé' }],
        events: [{ id: '01JEV', title: 'Causerie en ligne', href: '/evenements/01JEV', image: null, meta: '02/11 · En ligne' }],
        composer: composerOptions(),
        ...pageProps,
    },
});

describe('Page /fil', () => {
    it('affiche les accès rapides, la carte de création et « À la une »', async () => {
        await mount();
        const quick = screen.getByRole('navigation', { name: 'Accès rapides' });
        expect([...quick.querySelectorAll('a')].map((a) => [a.textContent?.trim(), a.getAttribute('href')])).toEqual([
            ['SOS', '/sos'], ['Signaler', '/signaler'], ['Assistant chat', '/assistant'], ['Urgences', '/urgences'],
        ]);
        expect(screen.getByRole('button', { name: 'Partage tes pensées, Awa_S…' })).toBeTruthy();
        // Sans droit de création d'événement (`events_available: false`), la pastille « Événement » est masquée.
        expect(screen.queryByRole('button', { name: /Événement/ })).toBeNull();
        expect(screen.getByRole('button', { name: /Sondage/ })).toBeTruthy();
        expect(screen.getByRole('link', { name: /Consentement et relations/ }).getAttribute('href')).toBe('/thematiques/consentement');
        expect(screen.getByRole('link', { name: 'Causerie en ligne' })).toBeTruthy();
        expect(document.querySelectorAll('article[data-xm-post]')).toHaveLength(2);
    });

    it('affiche la carte « Qui suivre » et ouvre l’invitation à confirmer sur /qui-suivre (P8-06)', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        await mount({ who_to_follow: [{ id: 7, pseudo: 'mariam-bj', avatar_url: null, href: '/qui-suivre', meta: 'Membre · Zou', invite_url: '/qui-suivre?invite=7' }] });

        expect(screen.getByRole('heading', { name: 'Qui suivre' })).toBeTruthy();
        expect(screen.getByText('Membre · Zou')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: /mariam-bj/ }));
        expect(visit).toHaveBeenCalledWith('/qui-suivre?invite=7');
    });

    it('ouvre la modale « Créer un événement » depuis la pastille Événement (P8-04)', async () => {
        await mount({ composer: composerOptions({ events_available: true, event: eventOptions() }) });
        const pill = screen.getByRole('button', { name: /^Événement$/ });
        expect(pill.getAttribute('aria-disabled')).toBeNull();
        await fireEvent.click(pill);
        await waitFor(() => expect(document.getElementById('modalCreateEvent')).not.toBeNull());
        await waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));
        expect(document.querySelector('#modalCreateEvent #f-category')).not.toBeNull();
    });

    it('masque la carte de création sans posts.create et la pastille Sondage sans polls.create', async () => {
        await mount({ composer: composerOptions({ can_poll: false }) });
        expect(screen.queryByRole('button', { name: /Sondage/ })).toBeNull();
        unmountInertia();
        await mount({ composer: composerOptions({ can_post: false, can_poll: false }) });
        expect(screen.queryByRole('button', { name: /Partage tes pensées/ })).toBeNull();
    });

    it('signale le filtre actif et recharge seulement le fil, avec des squelettes pendant le chargement', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation((_url, _data, options) => { options?.onStart?.({} as never); });
        await mount();
        const filters = screen.getByRole('list', { name: 'Filtrer le fil par type' });
        expect(filters.querySelector('[aria-current="page"]')?.textContent).toBe('Tout');
        const questions = screen.getByRole('link', { name: 'Questions' });
        expect(questions.getAttribute('href')).toBe('/fil?tag=question');
        await fireEvent.click(questions);
        expect(get).toHaveBeenCalledWith('/fil', { tag: 'question' }, expect.objectContaining({ only: ['feed', 'tag'], preserveState: true, preserveScroll: true }));
        await waitFor(() => expect(document.querySelectorAll('#liste .placeholder').length).toBeGreaterThan(0));
        expect(document.querySelector('#liste')?.getAttribute('aria-busy')).toBe('true');
        expect(document.querySelectorAll('article[data-xm-post]')).toHaveLength(0);
    });

    it('charge la suite par curseur, sans doublon, puis annonce la fin de la liste', async () => {
        const third = makePost({ id: '01JC0000000000000000000003', body: 'Troisième' });
        const { calls } = fakeFetch(() => json({ data: [second, third], meta: { next_cursor: null, per_page: 2 } }));
        await mount({ tag: null });
        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(document.querySelectorAll('article[data-xm-post]')).toHaveLength(3));
        expect(calls[0].url).toBe('/api/v1/feed?cursor=c2');
        expect(screen.queryByRole('button', { name: 'Charger plus' })).toBeNull();
        expect(screen.getByText('Tu as tout vu : 3 éléments affichés.')).toBeTruthy();
        expect(document.querySelector('[role="status"][aria-live="polite"]')?.textContent).toContain('1 publication de plus.');
    });

    it('garde l’étiquette du filtre dans la suite et affiche l’état vide d’un filtre', async () => {
        await mount({ tag: 'sondage', feed: { data: [], meta: { next_cursor: null } } }, '/fil?tag=sondage');
        expect(screen.getByText('Aucune publication pour ce filtre.')).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Sondages' }).getAttribute('aria-current')).toBe('page');
    });

    it('ouvre la modale « Créer une publication » chargée à la demande', async () => {
        await mount();
        await fireEvent.click(screen.getByRole('button', { name: 'Partage tes pensées, Awa_S…' }));
        await waitFor(() => expect(document.getElementById('modalCreateFeed')).not.toBeNull());
        expect(document.getElementById('modalCreateFeedTitle')?.textContent).toBe('Créer une publication');
        await waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));
    });
    it('annonce une publication mise en file puis envoyée au rejeu et relit le fil', async () => {
        const reload = vi.spyOn(router, 'reload').mockImplementation(() => undefined);
        await mount();
        expect(document.querySelector('[data-xm-feed-page]')).not.toBeNull();
        const detail: FlushDetail = { sent: [{ id: 'k1', type: 'post', status: 201 }, { id: 's1', type: 'sos', status: 201 }], failed: [], pending: 0 };
        useOutboxStore().lastFlush = detail;
        await waitFor(() => expect(useUiStore().toasts.map((toast) => toast.message)).toContain('Ta publication mise en attente est en ligne.'));
        expect(reload).toHaveBeenCalledTimes(1);
        expect(reload).toHaveBeenCalledWith({ only: ['feed'] });
    });

    it('rouvre la modale d’une publication refusée au rejeu, avec son contenu et les erreurs du serveur', async () => {
        await mount();
        await useDraftsStore().save(DRAFT_POST, { body: 'Mon témoignage', tag: 'temoignage', visibility: 'public', is_anonymous: false, image_alt: '', outbox: 'k9' });
        const failed = { id: 'k9', type: 'post', status: 422, errors: { body: ['Ce texte contient des mots non autorisés par la charte.'] } };
        useOutboxStore().lastFlush = { sent: [], failed: [failed], pending: 0 };
        await waitFor(() => expect(document.getElementById('modalCreateFeed')).not.toBeNull());
        await waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));
        await waitFor(() => expect((screen.getByLabelText('Ton message') as HTMLTextAreaElement).value).toBe('Mon témoignage'));
        expect(screen.getAllByText('Ce texte contient des mots non autorisés par la charte.')).not.toHaveLength(0);
        expect(useUiStore().toasts.map((toast) => toast.message)).toContain('Ta publication mise en attente n’a pas pu partir. Vérifie son contenu, puis publie-la à nouveau.');
        // Le résumé d'erreurs prend le focus avant la fin de l'ouverture : la transition Bootstrap se termine avant le démontage.
        await new Promise((resolve) => setTimeout(resolve, 50));
    });
});
