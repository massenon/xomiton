// @vitest-environment jsdom
// Page /forums (lot forums, P8-05) : cartes des 5 forums du prototype forums.php (« N sujets · M messages »), sujets
// récents (auteur·rice ou « Membre anonyme » · forum, réponses et vues nommées pour les lecteurs d'écran), « Charger
// plus » par page, filtre par forum en visite partielle, modale « Nouveau sujet » chargée à la demande (titre ≥ 5 et
// message ≥ 10 vérifiés avant l'envoi, forum filtré présélectionné, interrupteur anonyme envoyé).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { anonymousAuthor, createOptions, makeForums, makeTopic } from '@/test/forums';
import Index from './Index.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
    document.body.className = '';
});

/** Modale ouverte et focus piégé (transition Bootstrap terminée). */
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));

const list = (data = [makeTopic()], meta = { current_page: 1, last_page: 1 }) => ({ data, meta });
const pageProps = (overrides: Record<string, unknown> = {}) => ({ title: 'Forums', forum: null, q: null, forums: makeForums(), topics: list(), create: createOptions(), events: [], ...overrides });

describe('Page /forums', () => {
    it('affiche les forums, les sujets récents et un sujet anonyme sans pseudo', async () => {
        const anonymous = makeTopic({ id: '01JC3000000000000000000002', title: 'Je n’ose pas en parler', author: anonymousAuthor(), is_anonymous: true, replies_count: 3, views_count: 40 });
        await mountInertia(Index, { url: '/forums', pageProps: pageProps({ topics: list([makeTopic(), anonymous]) }) });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Forums');
        expect(screen.getAllByRole('heading', { level: 2 }).map((el) => el.textContent?.trim())).toContain('Santé & bien-être');
        expect(screen.getByText('4 sujets · 9 messages', { exact: false })).toBeTruthy();
        const row = screen.getByRole('link', { name: /Je n’ose pas en parler/ });
        expect(row.getAttribute('href')).toBe('/forums/01JC3000000000000000000002');
        expect(row.textContent).toContain('Membre anonyme · Santé & bien-être');
        expect(row.textContent).toContain('3 réponses');
        expect(row.textContent).toContain('40 vues');
        expect(screen.getByRole('heading', { level: 2, name: 'Sujets récents' })).toBeTruthy();
    });

    it('affiche la dernière activité (tri des sujets récents, Q78) et borne la recherche par search_max', async () => {
        const answered = makeTopic({ id: '01JC3000000000000000000004', title: 'Sujet animé', created_at: new Date(Date.now() - 5 * 86_400_000).toISOString(), last_reply_at: new Date(Date.now() - 3 * 3_600_000).toISOString() });
        await mountInertia(Index, { url: '/forums', pageProps: pageProps({ search_max: 40, topics: list([answered, makeTopic()]) }) });

        expect(screen.getByRole('link', { name: /Sujet animé/ }).textContent).toContain('Dernière réponse il y a 3 h');
        expect(screen.getByRole('link', { name: /Règles douloureuses/ }).textContent).toContain('il y a 2 h');
        expect(screen.getByRole('link', { name: /Règles douloureuses/ }).textContent).not.toContain('Dernière réponse');
        expect(screen.getByRole('searchbox').getAttribute('maxlength')).toBe('40');
    });

    it('filtre par forum en visite partielle et charge la page suivante', async () => {
        const visit = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        const next = makeTopic({ id: '01JC3000000000000000000003', title: 'Dépistage gratuit' });
        const { calls } = fakeFetch(() => json(list([next], { current_page: 2, last_page: 2 })));
        await mountInertia(Index, { url: '/forums?forum=f3', pageProps: pageProps({ forum: 'f3', topics: list([makeTopic()], { current_page: 1, last_page: 2 }) }) });

        expect(screen.getByRole('heading', { level: 2, name: /Sujets récents – Santé & bien-être/ })).toBeTruthy();
        expect(screen.getByRole('link', { name: /Santé & bien-être/, current: 'page' })).toBeTruthy();

        await fireEvent.click(screen.getByRole('link', { name: /Droits & démarches/ }));
        expect(visit).toHaveBeenCalledWith('/forums', { forum: 'f2' }, expect.objectContaining({ only: ['topics', 'forum', 'q'] }));

        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getByText('Dépistage gratuit')).toBeTruthy());
        expect(calls[0].url).toBe('/api/v1/forums/topics?forum=f3&page=2');
        expect(screen.queryByRole('button', { name: 'Charger plus' })).toBeNull();
    });

    it('ouvre un sujet depuis la modale : bornes vérifiées sans appel, puis envoi anonyme dans le forum filtré', async () => {
        const created = makeTopic({ id: '01JC3000000000000000000009', is_mine: true, author: anonymousAuthor(), is_anonymous: true });
        const { calls } = fakeFetch(() => json({ data: created }, 201));
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        await mountInertia(Index, { url: '/forums?forum=f2', pageProps: pageProps({ forum: 'f2', topics: list([]) }) });
        expect(screen.getByText('Aucun sujet pour le moment. Lance la discussion !', { exact: false })).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: 'Nouveau sujet' }));
        await waitFor(() => expect(document.querySelector('#modalTopic')).not.toBeNull());
        await shown();
        expect((document.querySelector('#f-forum') as HTMLSelectElement).value).toBe('f2');

        await fireEvent.update(document.querySelector('#f-title') as HTMLInputElement, 'Aide');
        await fireEvent.update(document.querySelector('#f-body') as HTMLTextAreaElement, 'Court');
        await fireEvent.submit(document.querySelector('#modalTopic form') as HTMLFormElement);
        const text = document.querySelector('#modalTopic')?.textContent ?? '';
        expect(text).toContain('Le titre doit contenir au moins 5 caractères.');
        expect(text).toContain('Ton message doit contenir au moins 10 caractères.');
        expect(calls).toHaveLength(0);

        await fireEvent.update(document.querySelector('#f-title') as HTMLInputElement, 'Porter plainte seule ?');
        await fireEvent.update(document.querySelector('#f-body') as HTMLTextAreaElement, 'Est-ce possible sans témoin ?');
        await fireEvent.click(document.querySelector('#f-is_anonymous') as HTMLInputElement);
        await fireEvent.submit(document.querySelector('#modalTopic form') as HTMLFormElement);
        await flushPromises();
        expect(calls[0]).toMatchObject({ url: '/api/v1/forums/topics', method: 'POST', body: { forum: 'f2', title: 'Porter plainte seule ?', body: 'Est-ce possible sans témoin ?', is_anonymous: true } });
        await waitFor(() => expect(visit).toHaveBeenCalledWith(created.url));
        await waitFor(() => expect(document.querySelector('#modalTopic')).toBeNull());
    });

    it('n’offre pas « Nouveau sujet » sans le droit de créer', async () => {
        await mountInertia(Index, { url: '/forums', pageProps: pageProps({ create: createOptions({ can: false }) }) });
        expect(screen.queryByRole('button', { name: 'Nouveau sujet' })).toBeNull();
    });
});
