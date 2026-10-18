// @vitest-environment jsdom
// Détail /fil/{ulid} et /fil/enregistres (lot fil-ecrans, P7-06) : carte complète sans champ de commentaire de carte,
// commentaires paginés (suite par curseur, « Membre anonyme »), commentaire ajouté et compteur repris du serveur,
// suppression confirmée par <XmModal type="delete">, signalement avec les motifs `report_reasons` de la page ;
// publications enregistrées retirées de la liste quand on les retire des enregistrées, état vide.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json, makePost } from '@/test/feed';
import type { Comment } from '@/feed/types';
import Saved from './Saved.vue';
import Show from './Show.vue';

afterEach(() => {
    unmountInertia();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
    document.body.className = '';
});

/** Bootstrap Modal : ouverture terminée, focus piégé. */
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));

const comment = (id: number, overrides: Partial<Comment> = {}): Comment => ({
    id,
    parent_id: null,
    body: `Commentaire ${id}`,
    author: { id: 30 + id, pseudo: `Membre_${id}`, avatar_url: null, role: 'membre', anonymous: false },
    is_mine: false,
    is_anonymous: false,
    created_at: new Date(Date.now() - 3_600_000).toISOString(),
    can: { delete: false, report: true },
    ...overrides,
});

describe('Page /fil/{ulid}', () => {
    it('affiche la carte, les commentaires, la suite par curseur et ajoute un commentaire', async () => {
        const anonymous = comment(2, { author: { id: null, pseudo: 'Membre anonyme', avatar_url: null, role: null, anonymous: true }, is_anonymous: true });
        const { calls } = fakeFetch((call) => (call.method === 'GET'
            ? json({ data: [comment(3)], meta: { next_cursor: null, comments_count: 3 } })
            : json({ data: comment(4, { body: 'Merci', is_mine: true, can: { delete: true, report: false } }), meta: { comments_count: 4 } }, 201)));
        await mountInertia(Show, { url: '/fil/01JC0000000000000000000001', pageProps: {
            title: 'Publication',
            post: makePost({ comments_count: 3 }),
            comments: { data: [comment(1), anonymous], meta: { next_cursor: 'k2', comments_count: 3 } },
            events: [],
        } });

        expect(screen.getByRole('heading', { level: 1 }).textContent).toBe('Publication de Awa_S');
        expect(screen.getByRole('heading', { level: 2, name: 'Awa_S' })).toBeTruthy();
        expect(screen.getByRole('heading', { level: 2, name: '3 commentaires' })).toBeTruthy();
        expect(screen.getByText('Membre anonyme')).toBeTruthy();
        expect(document.querySelectorAll('textarea')).toHaveLength(1);

        await fireEvent.click(screen.getByRole('button', { name: 'Voir la suite des commentaires' }));
        await waitFor(() => expect(screen.getByText('Commentaire 3')).toBeTruthy());
        expect(calls[0].url).toBe('/api/v1/posts/01JC0000000000000000000001/comments?cursor=k2');

        const field = screen.getByLabelText('Ton commentaire') as HTMLTextAreaElement;
        await fireEvent.update(field, 'Merci');
        await fireEvent.submit(field.form as HTMLFormElement);
        await waitFor(() => expect(screen.getByRole('heading', { level: 2, name: '4 commentaires' })).toBeTruthy());
        expect(screen.getByRole('button', { name: /Commentaires \(4\)/ })).toBeTruthy();
        expect(document.querySelector('#comment-4')?.textContent).toContain('Merci');
        expect(field.value).toBe('');
    });

    it('refuse un commentaire de moins de 2 caractères sans appeler l’API', async () => {
        const { calls } = fakeFetch(() => json({}));
        await mountInertia(Show, { pageProps: { title: 'Publication', post: makePost(), comments: { data: [], meta: { next_cursor: null } } } });
        expect(screen.getByText('Aucun commentaire. Écris le premier, avec bienveillance.')).toBeTruthy();
        const field = screen.getByLabelText('Ton commentaire') as HTMLTextAreaElement;
        await fireEvent.update(field, 'x');
        await fireEvent.submit(field.form as HTMLFormElement);
        expect(screen.getByText('Écris au moins 2 caractères.')).toBeTruthy();
        expect(calls).toHaveLength(0);
    });
});

describe('Page /fil/{ulid} : suppression et signalement', () => {
    const reasons = [{ value: 'harcelement', label: 'Harcèlement ou insultes' }, { value: 'autre', label: 'Autre' }];

    it('supprime son commentaire après confirmation, sans window.confirm', async () => {
        const confirm = vi.spyOn(window, 'confirm');
        const { calls } = fakeFetch(() => new Response(null, { status: 204 }));
        await mountInertia(Show, { pageProps: {
            title: 'Publication',
            post: makePost({ comments_count: 2 }),
            comments: { data: [comment(1), comment(5, { is_mine: true, can: { delete: true, report: false } })], meta: { next_cursor: null } },
        } });
        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer ce commentaire' }));
        await shown();
        expect(screen.getByText('Supprimer ce commentaire ?')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer définitivement' }));
        await waitFor(() => expect(document.querySelector('#comment-5')).toBeNull());
        expect(calls[0]).toMatchObject({ method: 'DELETE', url: '/api/v1/comments/5' });
        expect(screen.getByRole('heading', { level: 2, name: '1 commentaire' })).toBeTruthy();
        await waitFor(() => expect(document.getElementById('modalDeleteComment')).toBeNull());
        expect(confirm).not.toHaveBeenCalled();
    });

    it('signale un commentaire avec les motifs fournis par la page', async () => {
        const { calls } = fakeFetch(() => json({ data: { reported: true } }, 201));
        await mountInertia(Show, { pageProps: {
            title: 'Publication',
            post: makePost(),
            comments: { data: [comment(1)], meta: { next_cursor: null } },
            report_reasons: reasons,
        } });
        await fireEvent.click(screen.getByRole('button', { name: 'Signaler ce commentaire' }));
        await shown();
        expect(document.getElementById('modalReportContentTitle')?.textContent).toBe('Signaler ce commentaire');
        await fireEvent.click(screen.getByLabelText('Harcèlement ou insultes'));
        await fireEvent.submit(document.querySelector('#modalReportContent form') as HTMLFormElement);
        await waitFor(() => expect(calls[0]).toMatchObject({ method: 'POST', url: '/api/v1/comments/1/report', body: { reason: 'harcelement' } }));
        await waitFor(() => expect(document.getElementById('modalReportContent')).toBeNull());
    });

    it('supprime la publication depuis le détail puis revient au fil', async () => {
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined);
        const { calls } = fakeFetch(() => new Response(null, { status: 204 }));
        await mountInertia(Show, { pageProps: {
            title: 'Publication',
            post: makePost({ is_mine: true, can: { update: true, delete: true, report: false, comment: true } }),
            comments: { data: [], meta: { next_cursor: null } },
            report_reasons: reasons,
        } });
        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer' }));
        await shown();
        await fireEvent.click(screen.getByRole('button', { name: 'Supprimer définitivement' }));
        await waitFor(() => expect(visit).toHaveBeenCalledWith('/fil'));
        expect(calls[0]).toMatchObject({ method: 'DELETE', url: '/api/v1/posts/01JC0000000000000000000001' });
    });
});

describe('Page /fil/enregistres', () => {
    it('retire une publication de la liste quand on la retire des enregistrées', async () => {
        fakeFetch(() => json({ data: { saved: false, changed: true } }));
        await mountInertia(Saved, { url: '/fil/enregistres', pageProps: {
            title: 'Publications enregistrées',
            saved: { data: [makePost({ saved: true })], meta: { next_cursor: null } },
        } });
        expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('Publications enregistrées');
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer des enregistrés' }));
        await waitFor(() => expect(document.querySelector('article[data-xm-post]')).toBeNull());
        expect(screen.getByText(/Aucune publication enregistrée/)).toBeTruthy();
    });
});
