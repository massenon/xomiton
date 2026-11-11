// @vitest-environment jsdom
// Page /forums/{ulid} (lot forums, P8-05 ; prototype forum-topic.php) : fil d'Ariane, sujet (message avec retours à la
// ligne, vues), réponses (badge du prototype, « Membre anonyme »), « Charger plus », réponse (≥ 2 vérifié avant l'envoi,
// interrupteur anonyme, compteur repris du serveur), sujet fermé (message à la place du formulaire, refus 422
// `topic_locked`), signalement par la modale du fil (cible `topic` / `reply`), suppression confirmée.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { anonymousAuthor, author, makeReply, makeTopic, TOPIC_ID } from '@/test/forums';
import Show from './Show.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
    document.body.className = '';
});

/** Modale ouverte et focus piégé (transition Bootstrap terminée). */
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));

const replies = (data = [makeReply(1)], meta = { current_page: 1, last_page: 1 }) => ({ data, meta });
const REASONS = [{ value: 'harcelement', label: 'Harcèlement' }, { value: 'autre', label: 'Autre' }];
const pageProps = (overrides: Record<string, unknown> = {}) => ({
    title: 'Règles douloureuses', topic: makeTopic(), replies: replies(), reply: { limits: { min: 2, max: 2000 } }, report_reasons: REASONS, events: [], ...overrides,
});

describe('Page /forums/{ulid}', () => {
    it('affiche le fil d’Ariane, le sujet, les réponses avec badge et réponse anonyme', async () => {
        const peer = makeReply(1, { author: author({ pseudo: 'Kossi_pair', role: 'pair', badge: 'Pair animateur·rice' }) });
        const anonymous = makeReply(2, { author: anonymousAuthor(), is_anonymous: true, body: 'Je te crois.' });
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps({ topic: makeTopic({ replies_count: 2 }), replies: replies([peer, anonymous]) }) });

        const crumbs = screen.getByRole('navigation', { name: 'Fil d’Ariane des forums' });
        expect(crumbs.querySelector('a[href="/forums"]')?.textContent).toBe('Forums');
        expect(crumbs.querySelector('a[href="/forums?forum=f3"]')?.textContent).toBe('Santé & bien-être');
        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Règles douloureuses');
        expect(screen.getByText('12 vues')).toBeTruthy();
        expect(document.querySelector('.xm-forum-text')?.textContent).toBe('Que faire quand la douleur\nempêche d’aller en cours ?');
        expect(screen.getByRole('heading', { level: 2, name: '2 réponses' }).id).toBe('reponses');
        expect(screen.getByText('Pair animateur·rice')).toBeTruthy();
        expect(screen.getByText('Membre anonyme')).toBeTruthy();
    });

    it('vérifie la réponse avant l’envoi, puis l’ajoute en anonyme avec le compteur du serveur', async () => {
        const created = makeReply(5, { body: 'Courage à toi.', author: anonymousAuthor(), is_anonymous: true, is_mine: true });
        const { calls } = fakeFetch(() => json({ data: created, meta: { replies_count: 2 } }, 201));
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps() });

        const field = screen.getByLabelText('Ta réponse') as HTMLTextAreaElement;
        await fireEvent.update(field, ' a ');
        await fireEvent.submit(field.closest('form') as HTMLFormElement);
        expect(screen.getByText('Ta réponse doit contenir au moins 2 caractères.')).toBeTruthy();
        expect(field.getAttribute('aria-invalid')).toBe('true');
        expect(calls).toHaveLength(0);

        await fireEvent.update(field, 'Courage à toi.');
        await fireEvent.click(screen.getByRole('switch', { name: 'Répondre de façon anonyme' }));
        await fireEvent.submit(field.closest('form') as HTMLFormElement);
        await flushPromises();

        expect(calls[0]).toMatchObject({ url: `/api/v1/forums/topics/${TOPIC_ID}/replies`, method: 'POST', body: { body: 'Courage à toi.', is_anonymous: true } });
        await waitFor(() => expect(screen.getByText('Courage à toi.')).toBeTruthy());
        expect(screen.getByRole('heading', { level: 2, name: '2 réponses' })).toBeTruthy();
        expect(field.value).toBe('');
    });

    it('remplace le formulaire par un message sur un sujet fermé, et le ferme sur un refus topic_locked', async () => {
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps({ topic: makeTopic({ is_locked: true, can: { reply: false, delete: false, report: true } }) }) });
        expect(screen.queryByLabelText('Ta réponse')).toBeNull();
        expect(screen.getAllByText('Ce sujet est fermé : plus de réponse possible.', { exact: false }).length).toBeGreaterThan(0);
        unmountInertia();

        fakeFetch(() => json({ message: 'Ce sujet est fermé : plus de réponse possible.', code: 'topic_locked' }, 422));
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps() });
        const field = screen.getByLabelText('Ta réponse') as HTMLTextAreaElement;
        await fireEvent.update(field, 'Bonjour à toutes.');
        await fireEvent.submit(field.closest('form') as HTMLFormElement);
        await waitFor(() => expect(screen.queryByLabelText('Ta réponse')).toBeNull());
        const notice = document.querySelector('.alert-info') as HTMLElement;
        expect(notice.textContent).toContain('Ce sujet est fermé');
        // Refus annoncé (région live) et focus sur l'avis plutôt que sur body.
        expect(notice.getAttribute('role')).toBe('status');
        await waitFor(() => expect(document.activeElement).toBe(notice));
        expect(document.querySelector('p[role="status"][aria-live="polite"]')?.textContent).toBe('Ce sujet est fermé : plus de réponse possible.');
    });

    it('compte la longueur de la réponse en caractères comme le serveur (un emoji compte pour un)', async () => {
        const { calls } = fakeFetch(() => json({ data: makeReply(9), meta: { replies_count: 2 } }, 201));
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps() });
        const field = screen.getByLabelText('Ta réponse') as HTMLTextAreaElement;
        await fireEvent.update(field, '🙂');
        await fireEvent.submit(field.closest('form') as HTMLFormElement);
        expect(screen.getByText('Ta réponse doit contenir au moins 2 caractères.', { exact: false })).toBeTruthy();
        expect(calls).toHaveLength(0);
    });

    it('charge les réponses suivantes par page', async () => {
        const { calls } = fakeFetch(() => json(replies([makeReply(2, { body: 'Réponse suivante' })], { current_page: 2, last_page: 2 })));
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps({ replies: replies([makeReply(1)], { current_page: 1, last_page: 2 }) }) });

        await fireEvent.click(screen.getByRole('button', { name: 'Charger plus' }));
        await waitFor(() => expect(screen.getByText('Réponse suivante')).toBeTruthy());
        expect(calls[0].url).toBe(`/api/v1/forums/topics/${TOPIC_ID}/replies?page=2`);
    });

    it('signale une réponse avec les motifs de la page (cible reply)', async () => {
        const { calls } = fakeFetch(() => json({ data: { reported: true, reason: 'harcelement' } }, 201));
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps() });

        await fireEvent.click(screen.getByRole('button', { name: 'Signaler cette réponse' }));
        await waitFor(() => expect(document.querySelector('#modalReportContent')).not.toBeNull());
        await shown();
        expect(document.querySelector('#modalReportContent .modal-title')?.textContent).toBe('Signaler cette réponse');
        await fireEvent.click(screen.getByLabelText('Harcèlement'));
        await fireEvent.submit(document.querySelector('#modalReportContent form') as HTMLFormElement);
        await flushPromises();
        expect(calls[0]).toMatchObject({ url: '/api/v1/forums/replies/1/report', method: 'POST', body: { reason: 'harcelement', details: null } });
        await waitFor(() => expect(document.querySelector('#modalReportContent')).toBeNull());
    });

    it('supprime son sujet après confirmation et revient au forum', async () => {
        const { calls } = fakeFetch(() => new Response(null, { status: 204 }));
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        await mountInertia(Show, { url: `/forums/${TOPIC_ID}`, pageProps: pageProps({ topic: makeTopic({ is_mine: true, can: { reply: true, delete: true, report: false } }) }) });
        expect(screen.queryByRole('button', { name: 'Signaler ce sujet' })).toBeNull();

        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer le sujet' }));
        await shown();
        await fireEvent.click(document.querySelector('#modalDeleteForumContent .modal-footer .btn-danger') as HTMLButtonElement);
        await flushPromises();
        expect(calls[0]).toMatchObject({ url: `/api/v1/forums/topics/${TOPIC_ID}`, method: 'DELETE' });
        await waitFor(() => expect(visit).toHaveBeenCalledWith('/forums?forum=f3'));
        await waitFor(() => expect(document.querySelector('#modalDeleteForumContent')).toBeNull());
    });
});
