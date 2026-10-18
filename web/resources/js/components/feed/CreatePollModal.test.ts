// @vitest-environment jsdom
// Modales de création (lot fil-ecrans, P7-04) : brouillon IndexedDB (écrit à la saisie, sans fichier, relu à
// l'ouverture avec « Brouillon restauré », effacé après l'envoi), envoi `POST /api/v1/posts` avec Idempotency-Key,
// erreurs 422 ramenées aux champs, image refusée au-delà de 5 Mo ou avec aperçu, hors connexion : mise en file dans le
// store `outbox` (même clé) avec le brouillon gardé et lié à l'entrée jusqu'à l'envoi confirmé (rejeu refusé : contenu,
// erreurs et clé retrouvés ; encore en attente : mention, puis l'envoi en ligne retire l'entrée), compteur de la
// question annoncé seulement près de la limite, anonyme jamais « abonné·es ».
import 'fake-indexeddb/auto';
import { openDB } from 'idb';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen, waitFor } from '@testing-library/vue';
import { nextTick } from 'vue';
import { renderXm } from '@/test/helpers';
import { composerOptions, fakeFetch, json, makePoll, makePost } from '@/test/feed';
import { DRAFT_STORE, setDraftOpener, useDraftsStore } from '@/stores/drafts';
import { useOutboxStore } from '@/stores/outbox';
import { DRAFT_POLL, DRAFT_POST } from '@/feed/composer';
import { settlePostDraft } from '@/public/outbox-notices.js';
import CreatePollModal from './CreatePollModal.vue';
import CreatePostModal from './CreatePostModal.vue';

let dbName = '';

beforeEach(() => {
    dbName = `xomiton-drafts-${Math.random().toString(36).slice(2)}`;
    setDraftOpener(() => openDB(dbName, 1, { upgrade(db) { db.createObjectStore(DRAFT_STORE); } }));
});

afterEach(() => {
    cleanup();
    setDraftOpener(null);
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.className = '';
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
});

const options = composerOptions();

const fillPoll = async () => {
    await fireEvent.update(screen.getByLabelText(/Question \(titre du sondage\)/), 'Quel thème pour la causerie ?');
    await fireEvent.update(screen.getByLabelText('Réponse 1'), 'Santé');
    await fireEvent.update(screen.getByLabelText('Réponse 2'), 'Droits');
};

/** Bootstrap Modal : ouverture terminée, focus piégé (transitions nulles sous jsdom, mais différées). */
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true));
const closed = (view: { emitted: (name: string) => unknown[] | undefined }) => waitFor(() => expect(view.emitted('hidden')).toBeTruthy());

const submit = (id: string) => fireEvent.submit(document.querySelector(`#${id} form`) as HTMLFormElement);

describe('Créer un sondage', () => {
    it('écrit le brouillon à la saisie, sans image, puis le relit à la réouverture', async () => {
        const first = renderXm(CreatePollModal, { props: { options } });
        await shown();
        await fillPoll();
        const drafts = useDraftsStore();
        await waitFor(async () => expect(await drafts.load(DRAFT_POLL)).toMatchObject({ question: 'Quel thème pour la causerie ?', options: ['Santé', 'Droits'], duration_days: 7 }), { timeout: 2000 });
        const saved = await drafts.load<Record<string, unknown>>(DRAFT_POLL);
        expect(Object.keys(saved ?? {})).not.toContain('image');
        first.unmount();

        renderXm(CreatePollModal, { props: { options } });

        await shown();
        // Nouveau Pinia : même compte (« guest »), même base.
        expect(await screen.findByText('Brouillon restauré. Tu peux le modifier avant de publier.')).toBeTruthy();
        await waitFor(() => expect((screen.getByLabelText('Réponse 2') as HTMLInputElement).value).toBe('Droits'));
    });

    it('publie en JSON avec une Idempotency-Key, annonce la publication et efface le brouillon', async () => {
        const created = makePost({ tag: 'sondage', poll: makePoll() });
        const { calls } = fakeFetch(() => json({ data: created }, 201));
        const view = renderXm(CreatePollModal, { props: { options } });
        await shown();
        await fillPoll();
        await fireEvent.click(screen.getByLabelText('Autoriser plusieurs réponses'));
        await submit('modalCreatePoll');

        await waitFor(() => expect(view.emitted('published')).toBeTruthy());
        expect(calls[0]).toMatchObject({
            url: '/api/v1/posts', method: 'POST',
            body: { kind: 'poll', visibility: 'public', is_anonymous: false, poll: { question: 'Quel thème pour la causerie ?', options: ['Santé', 'Droits'], multiple: true, results_before_vote: false, duration_days: 7 } },
        });
        expect(calls[0].headers['Idempotency-Key']).toMatch(/^[0-9a-f-]{36}$/);
        expect((view.emitted('published') as unknown[][])[0][0]).toEqual(created);
        await waitFor(async () => expect(await useDraftsStore().load(DRAFT_POLL)).toBeNull());
        await closed(view);
    });

    it('bloque l’envoi d’un sondage invalide et ramène les erreurs 422 du serveur aux champs', async () => {
        const { calls } = fakeFetch(() => json({ message: 'Données invalides.', code: 'validation_failed', errors: { 'poll.options.1': ['Ce texte contient des mots non autorisés par la charte.'] } }, 422));
        renderXm(CreatePollModal, { props: { options } });
        await shown();
        await fireEvent.update(screen.getByLabelText(/Question \(titre du sondage\)/), 'Quoi');
        await submit('modalCreatePoll');
        expect(await screen.findAllByText('Entre 5 et 200 caractères.')).not.toHaveLength(0);
        expect(screen.getAllByText('Renseigne au moins 2 réponses différentes.')).not.toHaveLength(0);
        expect(calls).toHaveLength(0);

        await fillPoll();
        await submit('modalCreatePoll');
        expect(await screen.findAllByText('Ce texte contient des mots non autorisés par la charte.')).not.toHaveLength(0);
        expect(document.querySelector('#f-options')?.getAttribute('aria-invalid')).toBe('true');
    });

    it('ajoute jusqu’à 6 réponses et n’en retire jamais sous 2', async () => {
        renderXm(CreatePollModal, { props: { options } });
        await shown();
        const add = () => fireEvent.click(screen.getByRole('button', { name: 'Ajouter une réponse' }));
        for (let i = 0; i < 4; i += 1) await add();
        expect(screen.getAllByPlaceholderText(/Réponse \d/)).toHaveLength(6);
        expect(screen.queryByRole('button', { name: 'Ajouter une réponse' })).toBeNull();
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer la réponse 6' }));
        expect(screen.getAllByPlaceholderText(/Réponse \d/)).toHaveLength(5);
        expect((screen.getByRole('button', { name: 'Retirer la réponse 1' }) as HTMLButtonElement).disabled).toBe(false);
    });

    it('met la publication en file hors connexion, avec la même clé, et garde le brouillon lié à l’entrée', async () => {
        vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
        const { calls } = fakeFetch(() => json({}, 500));
        const add = vi.fn(async (_entry: Record<string, unknown>) => ({ id: 'x' }));
        const view = renderXm(CreatePollModal, { props: { options } });
        await shown();
        const outbox = useOutboxStore();
        await outbox.start({
            account: 'acct', storage: null,
            importer: async () => [{ getOutbox: () => ({ add, start: vi.fn(), flush: vi.fn(), count: vi.fn(async () => 1) }) } as never, { mountOutboxNotices: vi.fn() } as never],
        });
        await fillPoll();
        await submit('modalCreatePoll');

        await waitFor(() => expect(add).toHaveBeenCalledTimes(1));
        const entry = add.mock.calls[0][0];
        expect(entry).toMatchObject({ type: 'post', url: '/api/v1/posts', method: 'POST', account: 'acct' });
        expect(entry.idempotencyKey).toMatch(/^[0-9a-f-]{36}$/);
        expect(entry.body).toMatchObject({ kind: 'poll', poll: { options: ['Santé', 'Droits'] } });
        expect(calls).toHaveLength(0);
        await closed(view);
        expect(await useDraftsStore().load(DRAFT_POLL)).toMatchObject({ question: 'Quel thème pour la causerie ?', options: ['Santé', 'Droits'], outbox: entry.idempotencyKey });
    });

    it('rejeu refusé (422) : le contenu, les erreurs et la clé sont retrouvés à la réouverture', async () => {
        vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
        const add = vi.fn(async (_entry: Record<string, unknown>) => ({ id: 'x' }));
        const box = { add, start: vi.fn(), flush: vi.fn(), count: vi.fn(async () => 0), list: vi.fn(async () => []), remove: vi.fn(async () => undefined) };
        const startOutbox = () => useOutboxStore().start({ account: 'acct', storage: null, importer: async () => [{ getOutbox: () => box } as never, { mountOutboxNotices: vi.fn() } as never] });
        const first = renderXm(CreatePollModal, { props: { options } });
        await shown();
        await startOutbox();
        await fillPoll();
        await submit('modalCreatePoll');
        await waitFor(() => expect(add).toHaveBeenCalledTimes(1));
        const key = add.mock.calls[0][0].idempotencyKey as string;
        await closed(first);
        first.unmount();

        // Rejeu refusé par le filtre de mots : public/outbox-notices.js note le refus dans le brouillon lié.
        await settlePostDraft(key, { status: 422, errors: { 'poll.question': ['Ce texte contient des mots non autorisés par la charte.'] } }, () => openDB(dbName, 1));

        vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(true);
        const { calls } = fakeFetch(() => json({ data: makePost({ tag: 'sondage', poll: makePoll() }) }, 201));
        const view = renderXm(CreatePollModal, { props: { options } });
        await startOutbox();
        await shown();
        await waitFor(() => expect((screen.getByLabelText(/Question \(titre du sondage\)/) as HTMLInputElement).value).toBe('Quel thème pour la causerie ?'));
        expect((screen.getByLabelText('Réponse 2') as HTMLInputElement).value).toBe('Droits');
        expect(screen.getAllByText('Ce texte contient des mots non autorisés par la charte.')).not.toHaveLength(0);

        await fireEvent.update(screen.getByLabelText(/Question \(titre du sondage\)/), 'Quel thème pour le prochain atelier ?');
        await submit('modalCreatePoll');
        await waitFor(() => expect(view.emitted('published')).toBeTruthy());
        expect(calls[0].headers['Idempotency-Key']).toBe(key);
        expect(calls[0].body).toMatchObject({ poll: { question: 'Quel thème pour le prochain atelier ?' } });
        await waitFor(async () => expect(await useDraftsStore().load(DRAFT_POLL)).toBeNull());
        await closed(view);
    });

    it('publication encore en attente : mention à la réouverture, puis l’envoi en ligne retire l’entrée (même clé)', async () => {
        const remove = vi.fn(async () => undefined);
        const box = { add: vi.fn(), start: vi.fn(), flush: vi.fn(), count: vi.fn(async () => 0), list: vi.fn(async () => [{ id: 'cle-en-attente', type: 'post' }]), remove };
        await useDraftsStore().save(DRAFT_POLL, { question: 'Quel thème pour la causerie ?', options: ['Santé', 'Droits'], body: '', duration_days: 7, visibility: 'public', multiple: false, results_before_vote: false, is_anonymous: false, outbox: 'cle-en-attente' });
        const { calls } = fakeFetch(() => json({ data: makePost({ tag: 'sondage', poll: makePoll() }) }, 201));
        const view = renderXm(CreatePollModal, { props: { options } });
        void useOutboxStore().start({ account: 'acct', storage: null, importer: async () => [{ getOutbox: () => box } as never, { mountOutboxNotices: vi.fn() } as never] });
        await shown();
        expect(await screen.findByText('Cette publication attend le retour du réseau. Si tu la modifies, la nouvelle version partira à sa place.')).toBeTruthy();
        expect((screen.getByLabelText('Réponse 1') as HTMLInputElement).value).toBe('Santé');

        await submit('modalCreatePoll');
        await waitFor(() => expect(view.emitted('published')).toBeTruthy());
        expect(remove).toHaveBeenCalledWith('cle-en-attente');
        expect(calls[0].headers['Idempotency-Key']).toBe('cle-en-attente');
        await closed(view);
    });

    it('n’annonce le compteur de la question qu’à l’approche de la limite, une fois la frappe posée', async () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        try {
            renderXm(CreatePollModal, { props: { options } });
            const field = screen.getByLabelText(/Question \(titre du sondage\)/);
            const live = document.querySelector('[data-poll-count-live]') as HTMLElement;
            expect(document.getElementById('f-question-count')?.hasAttribute('aria-live')).toBe(false);
            expect(field.getAttribute('aria-describedby')).toBe('f-question-count');
            await fireEvent.update(field, 'Quel thème ?');
            vi.advanceTimersByTime(1000);
            await nextTick();
            expect(live.textContent).toBe('');
            await fireEvent.update(field, 'x'.repeat(185));
            await nextTick();
            expect(live.textContent).toBe('');
            vi.advanceTimersByTime(1000);
            await nextTick();
            expect(live.textContent).toMatch(/^185 caractères sur 200$/);
        } finally {
            vi.useRealTimers();
        }
    });
});

describe('Créer une publication', () => {
    it('refuse une image de plus de 5 Mo et montre l’aperçu d’une image valide', async () => {
        vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:apercu'), revokeObjectURL: vi.fn() }));
        renderXm(CreatePostModal, { props: { options } });
        await shown();
        const input = screen.getByLabelText(/Photo \(facultatif\)/) as HTMLInputElement;
        const big = new File([new Uint8Array(5 * 1024 * 1024 + 1)], 'grande.jpg', { type: 'image/jpeg' });
        await fireEvent.change(input, { target: { files: [big] } });
        expect(screen.getAllByText('Image trop lourde (5 Mo maximum).')).not.toHaveLength(0);
        expect(document.querySelector('img[src="blob:apercu"]')).toBeNull();

        const ok = new File([new Uint8Array(1000)], 'atelier.png', { type: 'image/png' });
        await fireEvent.change(input, { target: { files: [ok] } });
        expect((document.querySelector('img[src="blob:apercu"]') as HTMLImageElement).alt).toBe('Aperçu de l’image');
        expect(screen.getByLabelText(/Description de la photo/)).toBeTruthy();
    });

    it('retire « Abonné·es » en anonyme et envoie la photo en multipart', async () => {
        vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:apercu'), revokeObjectURL: vi.fn() }));
        const { calls } = fakeFetch(() => json({ data: makePost() }, 201));
        const view = renderXm(CreatePostModal, { props: { options } });
        await shown();
        const visibility = screen.getByLabelText('Visibilité') as HTMLSelectElement;
        await fireEvent.update(visibility, 'followers');
        await fireEvent.click(screen.getByLabelText('Publier sous pseudonyme (anonyme)'));
        await waitFor(() => expect([...visibility.options].map((o) => o.value)).toEqual(['public', 'me']));
        expect(visibility.value).toBe('public');

        await fireEvent.update(screen.getByLabelText('Ton message'), 'Avec une photo');
        const ok = new File([new Uint8Array(1000)], 'atelier.png', { type: 'image/png' });
        await fireEvent.change(screen.getByLabelText(/Photo \(facultatif\)/), { target: { files: [ok] } });
        await submit('modalCreateFeed');
        await waitFor(() => expect(view.emitted('published')).toBeTruthy());
        const body = calls[0].body as FormData;
        expect(body).toBeInstanceOf(FormData);
        expect(body.get('is_anonymous')).toBe('1');
        expect(body.get('visibility')).toBe('public');
        expect((body.get('image') as File).name).toBe('atelier.png');
        expect(await useDraftsStore().load(DRAFT_POST)).toBeNull();
        await closed(view);
    });
});
