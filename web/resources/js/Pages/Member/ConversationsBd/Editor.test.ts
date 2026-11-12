// @vitest-environment jsdom
// P20-07 : éditeur des conversations en BD — ajout d'une bulle, déplacement au clavier (flèches, pas de 2 %),
// enregistrement automatique (anti-rebond, indicateur « Enregistré »), refus du serveur affiché sous le champ, contrôles
// de la grammaire en direct (jumeau de ConversationGrammar), proposition anonyme par défaut ; fonctions pures de
// l'éditeur et de la grammaire ; « Mes conversations ».
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import Editor from './Editor.vue';
import Index from './Index.vue';
import { arrowDelta, debounce, moveBubble, newBubble, payload, reorder, setCharacter, type EditorBubble, type Scene } from '@/components/bd/editor';
import { check, checkPanel } from '@/components/bd/grammar';

afterEach(() => {
    unmountInertia();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

const scene: Scene = { key: 'm01-case-1', title: 'Sortie du lycée', image: null, description: 'Sortie du lycée à Porto-Novo.', zone: 'haut', characters: [{ key: 'fifame', name: 'Fifamè' }, { key: 'rodrigue', name: 'Rodrigue' }] };

const library = {
    scenes: [scene, { ...scene, key: 'm01-case-2', title: 'Case 2' }, { ...scene, key: 'm01-case-3', title: 'Case 3' }],
    characters: { fifame: 'Fifamè', rodrigue: 'Rodrigue' },
    types: [
        { value: 'parole', label: 'Parole' }, { value: 'pensee', label: 'Pensée' }, { value: 'cri', label: 'Cri' }, { value: 'tremblee', label: 'Voix tremblée' },
        { value: 'chuchote', label: 'Chuchotement' }, { value: 'joie', label: 'Joie' }, { value: 'message', label: 'Message écrit' },
        { value: 'voix_appareil', label: 'Voix d’un appareil' }, { value: 'cartouche', label: 'Cartouche (narration)' },
    ],
    tails: [{ value: 'personnage', label: 'Vers le personnage' }, { value: 'ecran', label: 'Vers l’écran' }, { value: 'bord-gauche', label: 'Hors de l’image, à gauche' }],
    limits: { title_min: 3, title_max: 120, summary_max: 300, text_max: 240, words_max: 20, bubbles_max: 4, panels_min: 3, panels_max: 8 },
};

const speech = (text = 'Je ne viendrai pas ce soir.') => ({ type: 'parole', personnage: 'fifame', texte: text, emphase: [], position: { x: 4, y: 4 }, queue: { vers: 'personnage', x: 30, y: 70 }, associee_a: null, superpose: false });

function conversation(overrides: Record<string, unknown> = {}) {
    return {
        ulid: '01JDBDCONVERSATION000000001',
        title: 'Ma soirée',
        summary: '',
        status: 'brouillon',
        status_label: 'Brouillon',
        editable: true,
        show_pseudonym: false,
        refusal_reason: null,
        updated_at: null,
        public_url: null,
        pdf_url: '/mes-conversations-bd/01JDBDCONVERSATION000000001/pdf',
        panels: [
            { scene: 'm01-case-1', bubbles: [speech()] },
            { scene: 'm01-case-2', bubbles: [speech()] },
            { scene: 'm01-case-3', bubbles: [speech()] },
        ],
        ...overrides,
    };
}

async function mountEditor(overrides: Record<string, unknown> = {}, saveDelay = 50) {
    return mountInertia(Editor, { url: '/mes-conversations-bd/01JDBDCONVERSATION000000001', pageProps: { title: 'Éditeur', conversation: conversation(overrides), library, saveDelay } });
}

describe('Éditeur des conversations en BD', () => {
    it('ajoute une bulle à la case et ouvre ses réglages', async () => {
        fakeFetch(() => json({ data: { saved_at: '2026-11-12T10:00:00+00:00', issues: [] } }));
        await mountEditor();

        expect(document.querySelectorAll('[data-xm-bde-handle]')).toHaveLength(1);
        await fireEvent.click(screen.getByRole('button', { name: /Ajouter une bulle/ }));

        expect(document.querySelectorAll('[data-xm-bde-handle]')).toHaveLength(2);
        expect(screen.getByRole('group', { name: 'Bulle 2' })).toBeTruthy();
        expect((screen.getByLabelText('Qui parle ?') as HTMLSelectElement).value).toBe('fifame');
    });

    it('déplace la bulle au clavier par pas de 2 % et l’annonce', async () => {
        const { calls } = fakeFetch(() => json({ data: { saved_at: '2026-11-12T10:00:00+00:00', issues: [] } }));
        await mountEditor();

        const handle = document.querySelector('[data-xm-bde-handle="0"]') as HTMLButtonElement;
        expect(handle.getAttribute('aria-label')).toContain('Bulle 1, Fifamè');
        await fireEvent.keyDown(handle, { key: 'ArrowRight' });
        await fireEvent.keyDown(handle, { key: 'ArrowDown' });
        expect(document.body.textContent).toMatch(/Bulle 1 déplacée\s:\s6\s%\sdepuis la gauche, 6\s%\sdepuis le haut\./);

        await waitFor(() => expect(calls.some((call) => call.method === 'PUT')).toBe(true));
        const put = calls.find((call) => call.method === 'PUT') as { url: string; body: { panels: { bubbles: { position: { x: number; y: number } }[] }[] } };
        expect(put.url).toBe('/api/v1/bd-conversations/01JDBDCONVERSATION000000001');
        expect(put.body.panels[0].bubbles[0].position).toEqual({ x: 6, y: 6 });
    });

    it('enregistre automatiquement après une pause (une seule requête) et affiche « Enregistré »', async () => {
        const { calls } = fakeFetch(() => json({ data: { saved_at: '2026-11-12T10:00:00+00:00', issues: [] } }));
        await mountEditor({}, 200);

        const field = screen.getByLabelText(/^Titre/) as HTMLInputElement;
        await fireEvent.update(field, 'Ma soirée au');
        await fireEvent.update(field, 'Ma soirée au lycée');
        expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(0);

        await waitFor(() => expect(calls.filter((call) => call.method === 'PUT')).toHaveLength(1), { timeout: 2000 });
        expect((calls[0].body as { title: string }).title).toBe('Ma soirée au lycée');
        await waitFor(() => expect(document.querySelector('[data-xm-bde-saved]')?.textContent).toContain('Enregistré à'));
    });

    it('montre sous le champ le refus du serveur (numéro de téléphone) et l’indique comme non enregistré', async () => {
        fakeFetch(() => json({
            message: 'Retire le numéro de téléphone : une conversation peut devenir publique.',
            code: 'bd_content_rejected',
            errors: { 'panels.0.bubbles.0.texte': ['Retire le numéro de téléphone : une conversation peut devenir publique.'] },
        }, 422));
        await mountEditor();

        await fireEvent.update(screen.getByLabelText('Texte'), 'Appelle-moi au 97 00 11 22');
        await waitFor(() => expect(document.querySelector('[data-xm-bde-saved]')?.textContent).toContain('Pas enregistré'));
        expect(screen.getAllByText(/Retire le numéro de téléphone/).length).toBeGreaterThan(0);
    });

    it('applique les contrôles de la grammaire en direct', async () => {
        fakeFetch(() => json({ data: { saved_at: null, issues: [] } }));
        await mountEditor();
        expect(document.querySelector('[data-xm-bde-ok]')).not.toBeNull();

        await fireEvent.update(screen.getByLabelText('Texte'), Array(21).fill('mot').join(' '));
        await flushPromises();
        expect(screen.getByText('21 mots sur 20')).toBeTruthy();
        expect(document.querySelector('[data-xm-bde-issues]')?.textContent).toContain('21 mots');
        expect(screen.getByText(/1 point à corriger avant de proposer/)).toBeTruthy();
    });

    it('propose la conversation sans pseudonyme par défaut puis passe en lecture seule', async () => {
        const { calls } = fakeFetch((call) => (call.url.endsWith('/submit')
            ? json({ data: { status: 'proposee', status_label: 'Proposée', editable: false } })
            : json({ data: { saved_at: null, issues: [] } })));
        await mountEditor();

        expect((screen.getByLabelText('Afficher mon pseudonyme') as HTMLInputElement).checked).toBe(false);
        await fireEvent.click(screen.getByRole('button', { name: /^Proposer à la publication/ }));
        await flushPromises();

        const submit = calls.find((call) => call.url.endsWith('/submit'));
        expect(submit?.body).toEqual({ show_pseudonym: false });
        await waitFor(() => expect(screen.getByText('Statut : Proposée')).toBeTruthy());
        expect(screen.queryByRole('button', { name: /Ajouter une bulle/ })).toBeNull();
    });

    it('n’offre aucun import d’image', async () => {
        fakeFetch(() => json({ data: { saved_at: null, issues: [] } }));
        await mountEditor();
        expect(document.querySelector('input[type="file"]')).toBeNull();
    });
});

describe('Mes conversations en BD', () => {
    it('liste les créations avec leur statut et le motif d’un refus', async () => {
        fakeFetch(() => json({ data: {} }));
        await mountInertia(Index, {
            url: '/mes-conversations-bd',
            pageProps: {
                title: 'Mes conversations en BD',
                limits: { max: 50, title_min: 3, title_max: 120, summary_max: 300 },
                conversations: [
                    { ulid: 'A', title: 'Brouillon', summary: '', status: 'brouillon', status_label: 'Brouillon', editable: true, refusal_reason: null, panels_count: 2, updated_at: null, public_url: null, editor_url: '/mes-conversations-bd/A', pdf_url: '/mes-conversations-bd/A/pdf' },
                    { ulid: 'B', title: 'Refusée', summary: '', status: 'refusee', status_label: 'Refusée', editable: true, refusal_reason: 'Ajoute une case.', panels_count: 3, updated_at: null, public_url: null, editor_url: '/mes-conversations-bd/B', pdf_url: '/mes-conversations-bd/B/pdf' },
                    { ulid: 'C', title: 'Publiée', summary: '', status: 'publiee', status_label: 'Publiée', editable: false, refusal_reason: null, panels_count: 3, updated_at: null, public_url: '/conversations-bd/publiee', editor_url: '/mes-conversations-bd/C', pdf_url: '/mes-conversations-bd/C/pdf' },
                ],
            },
        });

        expect(screen.getByText('Ajoute une case.')).toBeTruthy();
        expect(screen.getAllByRole('link', { name: /PDF/ })).toHaveLength(3);
        expect(screen.getByRole('link', { name: /Voir la page publique/ }).getAttribute('href')).toBe('/conversations-bd/publiee');
        expect(screen.getAllByRole('button', { name: /Retirer/ })).toHaveLength(1);
    });
});

describe('Fonctions de l’éditeur', () => {
    const bubble = (): EditorBubble => ({ ...newBubble(scene, []), texte: 'Bonjour.' });

    it('déplace par pas de 2 % (10 % avec Maj) dans les bornes', () => {
        expect(arrowDelta('ArrowLeft')).toEqual([-2, 0]);
        expect(arrowDelta('ArrowUp', true)).toEqual([0, -10]);
        expect(arrowDelta('Enter')).toBeNull();
        const b = bubble();
        expect(moveBubble(b, 2, 0).position.x).toBe(b.position.x + 2);
        expect(moveBubble(b, -500, 500).position).toEqual({ x: -4, y: 100 });
    });

    it('fait d’un choix « narration » un cartouche sans queue, et réordonne', () => {
        const narration = setCharacter(bubble(), null, scene);
        expect(narration.type).toBe('cartouche');
        expect(narration.queue).toBeNull();
        expect(setCharacter(narration, 'rodrigue', scene)).toMatchObject({ type: 'parole', personnage: 'rodrigue' });
        const [a, b] = [bubble(), { ...bubble(), texte: 'Deux.' }];
        expect(reorder([a, b], 0, 1).map((x) => x.texte)).toEqual(['Deux.', 'Bonjour.']);
        expect(payload(' Titre ', '', [{ uid: 'p', scene: 'm01-case-1', bubbles: [a] }]).panels[0].bubbles[0]).not.toHaveProperty('uid');
    });

    it('regroupe les enregistrements (anti-rebond) et vide l’attente sur demande', async () => {
        vi.useFakeTimers();
        const action = vi.fn(async () => undefined);
        const saver = debounce(action, 1000);
        saver.schedule();
        saver.schedule();
        vi.advanceTimersByTime(999);
        expect(action).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        await Promise.resolve();
        expect(action).toHaveBeenCalledTimes(1);
        saver.schedule();
        await saver.flush();
        expect(action).toHaveBeenCalledTimes(2);
        expect(saver.pending()).toBe(false);
    });

    it('applique les mêmes règles que le serveur', () => {
        expect(check([]).map((i) => i.code)).toEqual(['panel_count', 'no_bubble']);
        const long = { ...speech(Array(21).fill('mot').join(' ')) };
        expect(checkPanel([long], 1).map((i) => i.code)).toContain('too_long');
        const many = [0, 1, 2, 3, 4].map((n) => ({ ...speech('Oui.'), position: { x: n * 19, y: 2 }, queue: { vers: 'bord-bas', x: null, y: null } }));
        expect(checkPanel(many, 1).map((i) => i.code)).toContain('too_many');
        expect(checkPanel([{ ...speech(), queue: null }], 1).map((i) => i.code)).toContain('tail_missing');
        expect(checkPanel([{ ...speech(), position: { x: 95, y: 4 } }], 1).map((i) => i.code)).toContain('outside');
        expect(checkPanel([speech(), { ...speech(), position: { x: 5, y: 5 } }], 1).map((i) => i.code)).toContain('overlap');
        expect(checkPanel([{ type: 'cartouche', texte: 'À Porto-Novo.', position: { x: 1.5, y: 1.5 }, queue: { vers: 'personnage', x: 50, y: 50 } }], 1).map((i) => i.code)).toContain('caption_tail');
    });
});
