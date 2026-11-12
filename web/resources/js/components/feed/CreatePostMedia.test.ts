// @vitest-environment jsdom
// Photos multiples et vidéo dans les composeurs (P7-10, Q96 ; docs/API.md §8.3 et §8.7) : sélection multiple, plafond
// lu des options de la page, texte alternatif par photo, retrait, réordonnancement au clavier (annoncé), erreurs 422
// par photo (images.N) rattachées au bon champ, exclusivité photos / vidéo, description obligatoire, avertissement de vie
// privée avec case, progression annoncée, annulation, 202 (relecture : jamais dans le fil), envoi sans réseau avec un
// média (message clair, brouillon gardé, rien en file), sondage avec photos et sans vidéo.
import 'fake-indexeddb/auto';
import { openDB } from 'idb';
import axe from 'axe-core';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen, waitFor } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import { composerOptions, fakeFetch, json, makePost, makeVideo } from '@/test/feed';
import { DRAFT_STORE, setDraftOpener, useDraftsStore } from '@/stores/drafts';
import { DRAFT_POST } from '@/feed/composer';
import { useUiStore } from '@/stores/ui';
import CreatePollModal from './CreatePollModal.vue';
import CreatePostModal from './CreatePostModal.vue';

class FakeXhr {
    static instances: FakeXhr[] = [];
    method = '';
    url = '';
    headers: Record<string, string> = {};
    body: unknown = null;
    status = 0;
    responseText = '';
    withCredentials = false;
    aborted = false;
    upload: { onprogress: ((event: { lengthComputable: boolean; loaded: number; total: number }) => void) | null } = { onprogress: null };
    onload: (() => void) | null = null;
    onerror: (() => void) | null = null;
    onabort: (() => void) | null = null;
    ontimeout: (() => void) | null = null;
    constructor() { FakeXhr.instances.push(this); }
    open(method: string, url: string) { this.method = method; this.url = url; }
    setRequestHeader(name: string, value: string) { this.headers[name] = value; }
    getResponseHeader() { return null; }
    send(body: unknown) { this.body = body; }
    abort() { this.aborted = true; this.onabort?.(); }
    progress(loaded: number, total: number) { this.upload.onprogress?.({ lengthComputable: true, loaded, total }); }
    respond(status: number, body: unknown) { this.status = status; this.responseText = JSON.stringify(body); this.onload?.(); }
}

let dbName = '';

beforeEach(() => {
    dbName = `xomiton-drafts-${Math.random().toString(36).slice(2)}`;
    setDraftOpener(() => openDB(dbName, 1, { upgrade(db) { db.createObjectStore(DRAFT_STORE); } }));
    FakeXhr.instances = [];
    let n = 0;
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => `blob:apercu-${++n}`), revokeObjectURL: vi.fn() }));
});

afterEach(async () => {
    // Les transitions Bootstrap en cours (fermeture à la destruction) se terminent avant que le DOM de test soit retiré.
    await new Promise((resolve) => setTimeout(resolve, 350));
    cleanup();
    setDraftOpener(null);
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.className = '';
    document.body.style.overflow = '';
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
});

const options = composerOptions();
const shown = () => waitFor(() => expect(document.querySelector('.modal.show')?.contains(document.activeElement)).toBe(true), { timeout: 3000 });
const submit = (id = 'modalCreateFeed') => fireEvent.submit(document.querySelector(`#${id} form`) as HTMLFormElement);
const photo = (name: string, size = 1000, type = 'image/jpeg') => new File([new Uint8Array(size)], name, { type });
const clip = (name = 'atelier.mp4', size = 1000, type = 'video/mp4') => new File([new Uint8Array(size)], name, { type });
const imagesField = () => document.querySelector('#f-images-field') as HTMLInputElement;
const videoField = () => document.querySelector('#f-video') as HTMLInputElement;
const pick = (files: File[]) => fireEvent.change(imagesField(), { target: { files } });
/** Texte sans espaces insécables (la typographie française les met avant : ; ! ? % et dans « »). */
const plain = (text: string | null | undefined) => (text ?? '').replace(/\u00a0/g, ' ');
const live = () => plain(document.querySelector('[data-xm-images-live]')?.textContent);
const altFields = () => [...document.querySelectorAll<HTMLInputElement>('[id^="f-images_alt-"]:not([id$="-hint"]):not([id$="-error"])')];

async function openPost(props: Record<string, unknown> = {}) {
    const view = renderXm(CreatePostModal, { props: { options, ...props } });
    await shown();
    return view;
}

describe('Photos multiples', () => {
    it('accepte plusieurs photos à la fois, avec aperçu, compteur et un champ de description par photo', async () => {
        await openPost();
        expect(imagesField().multiple).toBe(true);
        await pick([photo('a.jpg'), photo('b.png', 1000, 'image/png'), photo('c.jpg')]);

        expect(document.querySelectorAll('[data-xm-image]')).toHaveLength(3);
        expect(document.querySelectorAll('img.xm-media-thumb')).toHaveLength(3);
        expect(screen.getByLabelText('Description de la photo 2')).toBeTruthy();
        expect(document.querySelector('#f-images-counter')?.textContent).toContain('3 / 4');
        expect(document.querySelector('#f-images-counter')?.textContent).toContain('3 photos sur 4.');
        expect(live()).toBe('3 photos ajoutées. Il y en a 3 sur 4.');
    });

    it('lit le plafond dans les options de la page et garde les premières photos au-delà', async () => {
        await openPost({ options: composerOptions({ limits: { ...options.limits, max_images: 2 } }) });
        await pick([photo('a.jpg'), photo('b.jpg'), photo('c.jpg')]);
        expect(document.querySelectorAll('[data-xm-image]')).toHaveLength(2);
        expect(screen.getAllByText('Seules les 2 premières photos sont gardées.')).not.toHaveLength(0);
        expect(imagesField().disabled).toBe(true);
        expect(document.querySelector('#f-images-hint')?.textContent).toBe('Tu as atteint le nombre maximum de photos (2).');
    });

    it('refuse une photo trop lourde ou d’un autre format en la nommant, sans bloquer les autres', async () => {
        await openPost();
        await pick([photo('grande.jpg', 5 * 1024 * 1024 + 1), photo('dessin.gif', 10, 'image/gif'), photo('bonne.jpg')]);
        expect(document.querySelectorAll('[data-xm-image]')).toHaveLength(1);
        const error = plain(document.querySelector('#f-images-error')?.textContent);
        expect(error).toContain('« grande.jpg »');
        expect(error).toContain('Image trop lourde (5 Mo maximum).');
        expect(error).toContain('« dessin.gif »');
        expect(error).toContain('JPG, PNG ou WebP');
    });

    it('retire une photo, annonce le retrait et libère son aperçu', async () => {
        await openPost();
        await pick([photo('a.jpg'), photo('b.jpg'), photo('c.jpg')]);
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer la photo 2' }));
        expect(document.querySelectorAll('[data-xm-image]')).toHaveLength(2);
        expect(live()).toBe('Photo retirée. Il en reste 2 sur 4.');
        expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:apercu-2');
        await waitFor(() => expect(document.activeElement?.id).toBe('f-images_alt-1'));
    });

    it('réordonne au clavier avec Monter / Descendre : le texte suit la photo, le changement est annoncé', async () => {
        await openPost();
        await pick([photo('a.jpg'), photo('b.jpg'), photo('c.jpg')]);
        await fireEvent.update(screen.getByLabelText('Description de la photo 1'), 'Atelier');
        await fireEvent.update(screen.getByLabelText('Description de la photo 2'), 'Groupe');
        expect((screen.getByRole('button', { name: 'Monter la photo 1' }) as HTMLButtonElement).disabled).toBe(true);
        expect((screen.getByRole('button', { name: 'Descendre la photo 3' }) as HTMLButtonElement).disabled).toBe(true);

        screen.getByRole('button', { name: 'Monter la photo 2' }).focus();
        await fireEvent.click(screen.getByRole('button', { name: 'Monter la photo 2' }));

        expect(altFields().map((field) => field.value)).toEqual(['Groupe', 'Atelier', '']);
        expect(live()).toBe('Photo déplacée : elle est maintenant en position 1 sur 3.');
        // Elle est en tête : « Monter » est désactivé, le focus passe sur « Descendre » de la même photo.
        await waitFor(() => expect(document.activeElement?.id).toBe('f-images-0-down'));
    });

    it('envoie images[] et images_alt[] alignés dans l’ordre choisi, puis annonce la publication', async () => {
        const created = makePost({ media: [] });
        const { calls } = fakeFetch(() => json({ data: created }, 201));
        const view = await openPost();
        await fireEvent.update(screen.getByLabelText('Ton message'), 'Nos photos');
        await pick([photo('a.jpg'), photo('b.jpg')]);
        await fireEvent.update(screen.getByLabelText('Description de la photo 2'), 'Le groupe rassemblé');
        await fireEvent.click(screen.getByRole('button', { name: 'Monter la photo 2' }));
        await submit();

        await waitFor(() => expect(view.emitted('published')).toBeTruthy(), { timeout: 3000 });
        const body = calls[0].body as FormData;
        expect((body.getAll('images[]') as File[]).map((file) => file.name)).toEqual(['b.jpg', 'a.jpg']);
        expect(body.getAll('images_alt[]')).toEqual(['Le groupe rassemblé', '']);
        expect(calls[0].headers['Idempotency-Key']).toMatch(/^[0-9a-f-]{36}$/);
        expect(view.emitted('submitted')).toBeFalsy();
        expect(await useDraftsStore().load(DRAFT_POST)).toBeNull();
    });

    it('rattache les erreurs 422 par photo (images.N, images_alt.N) au bon champ, avec aria-invalid et aria-describedby', async () => {
        fakeFetch(() => json({
            message: 'Données invalides.', code: 'validation_failed',
            errors: { 'images.1': ['Cette image est illisible.'], 'images_alt.0': ['Ce texte contient des mots non autorisés par la charte.'] },
        }, 422));
        await openPost();
        await pick([photo('a.jpg'), photo('b.jpg'), photo('c.jpg')]);
        await submit();

        const first = screen.getByLabelText('Description de la photo 1');
        const second = screen.getByLabelText('Description de la photo 2');
        const third = screen.getByLabelText('Description de la photo 3');
        await waitFor(() => expect(first.getAttribute('aria-invalid')).toBe('true'));
        expect(second.getAttribute('aria-invalid')).toBe('true');
        expect(third.getAttribute('aria-invalid')).toBeNull();
        const describedBy = (field: HTMLElement) => (field.getAttribute('aria-describedby') ?? '').split(' ').map((id) => document.getElementById(id)?.textContent ?? '').join(' | ');
        expect(describedBy(first)).toContain('Ce texte contient des mots non autorisés par la charte.');
        expect(describedBy(second)).toContain('Cette image est illisible.');
        expect(describedBy(third)).not.toContain('illisible');
        // Le résumé d'erreurs mène aux ancres des photos concernées.
        expect(document.querySelector('.xm-error-summary a[href="#f-images-1"]')).toBeTruthy();
        expect(document.getElementById('f-images-1')?.getAttribute('data-xm-image')).toBe('1');
    });

    it('efface les erreurs de rang dès que la liste change (elles ne désignent plus la même photo)', async () => {
        fakeFetch(() => json({ message: 'x', code: 'validation_failed', errors: { 'images.1': ['Cette image est illisible.'] } }, 422));
        await openPost();
        await pick([photo('a.jpg'), photo('b.jpg')]);
        await submit();
        await screen.findAllByText('Cette image est illisible.');
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer la photo 1' }));
        expect(screen.queryAllByText('Cette image est illisible.')).toHaveLength(0);
    });
});

describe('Vidéo', () => {
    it('rend les photos et la vidéo exclusives et l’explique', async () => {
        await openPost();
        expect(screen.getByText('Une publication contient des photos ou une vidéo, pas les deux.')).toBeTruthy();
        await pick([photo('a.jpg')]);
        expect(videoField().disabled).toBe(true);
        expect(document.querySelector('#f-video-hint')?.textContent).toBe('Tu as ajouté des photos. Retire-les pour choisir une vidéo.');
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer la photo 1' }));
        expect(videoField().disabled).toBe(false);

        await fireEvent.change(videoField(), { target: { files: [clip()] } });
        expect(imagesField().disabled).toBe(true);
        expect(document.querySelector('#f-images-hint')?.textContent).toBe('Tu as choisi une vidéo. Retire-la pour ajouter des photos.');
        await fireEvent.click(screen.getByRole('button', { name: 'Retirer la vidéo' }));
        expect(imagesField().disabled).toBe(false);
    });

    it('n’offre pas de vidéo dans un sondage, mais des photos', async () => {
        renderXm(CreatePollModal, { props: { options } });
        await shown();
        expect(document.querySelector('#f-video')).toBeNull();
        expect(imagesField().multiple).toBe(true);
        expect(screen.getByText('Un sondage peut avoir des photos, mais pas de vidéo.')).toBeTruthy();
    });

    it('contrôle le type et le poids du fichier avant tout envoi', async () => {
        await openPost();
        await fireEvent.change(videoField(), { target: { files: [clip('film.mov', 100, 'video/quicktime')] } });
        expect(document.querySelector('#f-video-error')?.textContent).toContain('Format non pris en charge. Choisis une vidéo MP4 ou WebM.');
        expect(document.querySelector('[data-xm-video-card]')).toBeNull();
        await fireEvent.change(videoField(), { target: { files: [clip('lourde.mp4', 31 * 1024 * 1024)] } });
        expect(document.querySelector('#f-video-error')?.textContent).toContain('Vidéo trop lourde (30 Mo maximum).');
        expect(videoField().getAttribute('aria-invalid')).toBe('true');
    });

    it('affiche l’avertissement de vie privée AVANT l’envoi, avec une case de prise de connaissance obligatoire', async () => {
        const { calls } = fakeFetch(() => json({}, 500));
        await openPost();
        expect(document.querySelector('[data-xm-video-privacy]')).toBeNull();
        await fireEvent.change(videoField(), { target: { files: [clip()] } });

        const notice = document.querySelector('[data-xm-video-privacy]') as HTMLElement;
        expect(notice.textContent).toContain('Ta vidéo n’est pas retouchée');
        expect(notice.textContent).toContain('L’équipe de modération relit ta vidéo');
        expect(notice.getAttribute('role')).toBe('note');
        await fireEvent.update(screen.getByLabelText(/Description de la vidéo/), 'Des femmes assises en cercle.');
        await submit();

        const ack = screen.getByLabelText('J’ai lu cet avertissement et j’ai vérifié ma vidéo.') as HTMLInputElement;
        expect(ack.getAttribute('aria-invalid')).toBe('true');
        expect(screen.getAllByText('Coche la case pour confirmer que tu as lu l’avertissement.')).not.toHaveLength(0);
        expect(calls).toHaveLength(0);
    });

    it('exige une description de 5 à 500 caractères', async () => {
        const { calls } = fakeFetch(() => json({}, 500));
        await openPost();
        await fireEvent.change(videoField(), { target: { files: [clip()] } });
        await fireEvent.click(screen.getByLabelText('J’ai lu cet avertissement et j’ai vérifié ma vidéo.'));
        await submit();
        const description = screen.getByLabelText(/Description de la vidéo/);
        expect(description.getAttribute('aria-invalid')).toBe('true');
        expect(description.getAttribute('aria-required')).toBe('true');
        expect(screen.getAllByText('Décris ta vidéo en quelques mots pour les personnes qui ne peuvent pas la regarder.')).not.toHaveLength(0);
        await fireEvent.update(description, 'abc');
        await submit();
        expect(screen.getAllByText('La description de ta vidéo doit faire entre 5 et 500 caractères.')).not.toHaveLength(0);
        expect(calls).toHaveLength(0);
    });

    async function readyVideo() {
        vi.stubGlobal('XMLHttpRequest', FakeXhr);
        const view = await openPost();
        await fireEvent.change(videoField(), { target: { files: [clip()] } });
        await fireEvent.update(screen.getByLabelText(/Description de la vidéo/), 'Des femmes assises en cercle.');
        await fireEvent.click(screen.getByLabelText('J’ai lu cet avertissement et j’ai vérifié ma vidéo.'));
        await fireEvent.update(screen.getByLabelText('Ton message'), 'Notre atelier en vidéo.');
        return view;
    }

    it('envoie la vidéo avec sa description, annonce la progression par paliers et affiche une barre', async () => {
        await readyVideo();
        await submit();
        await waitFor(() => expect(FakeXhr.instances).toHaveLength(1));
        const xhr = FakeXhr.instances[0];
        expect(xhr).toMatchObject({ method: 'POST', url: '/api/v1/posts', withCredentials: true });
        expect(xhr.headers['Idempotency-Key']).toMatch(/^[0-9a-f-]{36}$/);
        expect(xhr.headers.Accept).toBe('application/json');
        const body = xhr.body as FormData;
        expect((body.get('video') as File).name).toBe('atelier.mp4');
        expect(body.get('video_description')).toBe('Des femmes assises en cercle.');
        expect(body.has('images[]')).toBe(false);

        xhr.progress(10, 100);
        const bar = await screen.findByRole('progressbar');
        expect(bar.getAttribute('aria-valuenow')).toBe('10');
        expect(document.querySelector('[data-xm-video-live]')?.textContent).toBe('');
        xhr.progress(50, 100);
        await waitFor(() => expect(bar.getAttribute('aria-valuenow')).toBe('50'));
        expect(plain(document.querySelector('[data-xm-video-live]')?.textContent)).toBe('Envoi de la vidéo : 50 %');
        expect((bar.firstElementChild as HTMLElement).style.width).toBe('50%');
    });

    it('annule l’envoi : rien n’est publié, la vidéo et les textes restent, le message est annoncé', async () => {
        const view = await readyVideo();
        await submit();
        await waitFor(() => expect(FakeXhr.instances).toHaveLength(1));
        FakeXhr.instances[0].progress(30, 100);
        await fireEvent.click(await screen.findByRole('button', { name: 'Annuler l’envoi' }));

        expect(FakeXhr.instances[0].aborted).toBe(true);
        expect(await screen.findAllByText('Envoi annulé. Ta vidéo n’a pas été envoyée.')).toHaveLength(1);
        expect(screen.queryByRole('progressbar')).toBeNull();
        expect(view.emitted('published')).toBeFalsy();
        expect(view.emitted('submitted')).toBeFalsy();
        expect((screen.getByLabelText(/Description de la vidéo/) as HTMLTextAreaElement).value).toBe('Des femmes assises en cercle.');
        expect(document.querySelector('[data-xm-video-card]')).toBeTruthy();
    });

    it('202 : vidéo reçue en relecture — annoncée, jamais ajoutée au fil (aucun évènement `published`)', async () => {
        const view = await readyVideo();
        await submit();
        await waitFor(() => expect(FakeXhr.instances).toHaveLength(1));
        const pending = makePost({ id: '01JC0000000000000000000099', is_mine: true, review_state: 'pending', review_label: 'En attente de relecture', published_at: null, media: [makeVideo()], can: { update: false, delete: true, report: false, comment: false } });
        FakeXhr.instances[0].respond(202, { data: pending });

        await waitFor(() => expect(view.emitted('submitted')).toBeTruthy(), { timeout: 3000 });
        expect(view.emitted('published')).toBeFalsy();
        expect((view.emitted('submitted') as unknown[][])[0][0]).toMatchObject({ id: '01JC0000000000000000000099', review_state: 'pending' });
        expect(useUiStore().toasts.map((toast) => toast.message)).toContain('Ta vidéo est bien reçue. L’équipe la relira avant de la publier.');
        await waitFor(async () => expect(await useDraftsStore().load(DRAFT_POST)).toBeNull(), { timeout: 3000 });
    });

    it('affiche l’erreur 422 de la description et le refus du quota quotidien (429)', async () => {
        const view = await readyVideo();
        await submit();
        await waitFor(() => expect(FakeXhr.instances).toHaveLength(1));
        FakeXhr.instances[0].respond(422, { message: 'x', code: 'validation_failed', errors: { video_description: ['Cette description contient des mots non autorisés par la charte.'] } });
        expect(await screen.findAllByText('Cette description contient des mots non autorisés par la charte.')).not.toHaveLength(0);
        expect(screen.getByLabelText(/Description de la vidéo/).getAttribute('aria-invalid')).toBe('true');

        await submit();
        await waitFor(() => expect(FakeXhr.instances).toHaveLength(2));
        FakeXhr.instances[1].respond(429, { message: 'Tu as atteint la limite de vidéos envoyées par jour (5). Réessaie un peu plus tard.', code: 'too_many_requests', retry_after: 3400 });
        expect(await screen.findByText('Tu as atteint la limite de vidéos envoyées par jour (5). Réessaie un peu plus tard.')).toBeTruthy();
        expect(view.emitted('submitted')).toBeFalsy();
    });
});

describe('Accessibilité (axe-core sous jsdom, hors contraste)', () => {
    const violations = async () => (await axe.run(document.querySelector('.modal') as Element, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);

    it('photos : aucune violation, avec des erreurs par photo affichées', async () => {
        fakeFetch(() => json({ message: 'x', code: 'validation_failed', errors: { 'images.1': ['Cette image est illisible.'], 'images_alt.0': ['Trop long.'] } }, 422));
        await openPost();
        await pick([photo('a.jpg'), photo('b.jpg')]);
        expect(await violations()).toEqual([]);
        await submit();
        await screen.findAllByText('Cette image est illisible.');
        expect(await violations()).toEqual([]);
    });

    it('vidéo : aucune violation, avec l’avertissement, la case et la progression', async () => {
        vi.stubGlobal('XMLHttpRequest', FakeXhr);
        await openPost();
        await fireEvent.change(videoField(), { target: { files: [clip()] } });
        expect(await violations()).toEqual([]);
        await fireEvent.update(screen.getByLabelText(/Description de la vidéo/), 'Des femmes assises en cercle.');
        await fireEvent.click(screen.getByLabelText('J’ai lu cet avertissement et j’ai vérifié ma vidéo.'));
        await fireEvent.update(screen.getByLabelText('Ton message'), 'Notre atelier.');
        await submit();
        await waitFor(() => expect(FakeXhr.instances).toHaveLength(1));
        FakeXhr.instances[0].progress(40, 100);
        await screen.findByRole('progressbar');
        expect(await violations()).toEqual([]);
    });
});

describe('Sans réseau', () => {
    it('refuse d’envoyer un média sans réseau : message clair, brouillon et photos gardés, rien en file', async () => {
        vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
        const { calls } = fakeFetch(() => json({}, 500));
        const view = await openPost();
        await fireEvent.update(screen.getByLabelText('Ton message'), 'Ma publication avec photo');
        await pick([photo('a.jpg')]);
        await submit();

        expect(await screen.findByText('Les photos et les vidéos ont besoin du réseau pour partir. Ton brouillon est gardé : réessaie dès que la connexion revient.')).toBeTruthy();
        expect(calls).toHaveLength(0);
        expect(view.emitted('published')).toBeFalsy();
        expect(document.querySelectorAll('[data-xm-image]')).toHaveLength(1);
        await waitFor(async () => expect(await useDraftsStore().load(DRAFT_POST)).toMatchObject({ body: 'Ma publication avec photo' }), { timeout: 2000 });
        const saved = await useDraftsStore().load<Record<string, unknown>>(DRAFT_POST);
        expect(JSON.stringify(saved)).not.toContain('a.jpg');
        expect(saved).not.toHaveProperty('outbox');
    });

    it('réseau coupé pendant l’envoi d’une photo : même message, jamais de mise en file', async () => {
        const fetchMock = vi.fn(async () => { throw new TypeError('network'); });
        vi.stubGlobal('fetch', fetchMock);
        await openPost();
        await fireEvent.update(screen.getByLabelText('Ton message'), 'Avec photo');
        await pick([photo('a.jpg')]);
        await submit();
        expect(await screen.findByText(/Les photos et les vidéos ont besoin du réseau/)).toBeTruthy();
        expect(fetchMock).toHaveBeenCalledTimes(1);
    });
});

describe('Sondage avec photos', () => {
    it('envoie un sondage avec plusieurs photos (multipart, textes alignés)', async () => {
        const { calls } = fakeFetch(() => json({ data: makePost({ tag: 'sondage' }) }, 201));
        const view = renderXm(CreatePollModal, { props: { options } });
        await shown();
        await fireEvent.update(screen.getByLabelText(/Question \(titre du sondage\)/), 'Quel thème pour la causerie ?');
        await fireEvent.update(screen.getByLabelText('Réponse 1'), 'Santé');
        await fireEvent.update(screen.getByLabelText('Réponse 2'), 'Droits');
        await pick([photo('a.jpg'), photo('b.jpg')]);
        await fireEvent.update(screen.getByLabelText('Description de la photo 1'), 'Atelier');
        await submit('modalCreatePoll');

        await waitFor(() => expect(view.emitted('published')).toBeTruthy(), { timeout: 3000 });
        const body = calls[0].body as FormData;
        expect(body.get('kind')).toBe('poll');
        expect(body.getAll('poll[options][]')).toEqual(['Santé', 'Droits']);
        expect(body.getAll('images[]')).toHaveLength(2);
        expect(body.getAll('images_alt[]')).toEqual(['Atelier', '']);
    });
});
