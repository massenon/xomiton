// @vitest-environment jsdom
// Logique des modales de création (lot fil-ecrans, P7-04) : mêmes règles que StorePostRequest (question 5–200, 2 à 6
// réponses distinctes casse et accents ignorés, texte ≤ 3000, image JPG / PNG / WebP ≤ 5 Mo), anonyme jamais
// « abonné·es », corps JSON ou multipart, corps rejouable de la boîte d'envoi, erreurs 422 ramenées aux champs.
import { describe, expect, it } from 'vitest';
import { i18n } from '@/app/i18n';
import { useFrench } from '@/test/helpers';
import { composerOptions } from '@/test/feed';
import { allowedVisibility, emptyPoll, emptyPost, formErrors, imageError, megabytes, noMedia, normalizeOption, outboxBody, requestBody, validatePoll, validatePost, videoError, type ComposerMedia, type ImageItem } from './composer';

useFrench();
const t = i18n.global.t as unknown as (key: string, named?: Record<string, unknown>) => string;
const limits = composerOptions().limits;
const file = (size: number, type = 'image/jpeg', name = 'photo.jpg') => new File([new Uint8Array(size)], name, { type });
const item = (id: number, alt = '', size = 1000, type = 'image/jpeg'): ImageItem => ({ id, file: file(size, type, `photo-${id}.jpg`), alt, preview: null });
const images = (...items: ImageItem[]): ComposerMedia => ({ images: items, video: null });
const video = (overrides: Partial<NonNullable<ComposerMedia['video']>> = {}, f = file(1000, 'video/mp4', 'atelier.mp4')): ComposerMedia => ({ images: [], video: { file: f, description: 'Des femmes assises en cercle.', acknowledged: true, ...overrides } });

describe('validatePoll', () => {
    it('borne la question de 5 à 200 caractères (points de code, comme mb_strlen)', () => {
        expect(validatePoll({ ...emptyPoll(), question: 'Quoi', options: ['A', 'B'] }, noMedia(), limits, t).question).toBe('Entre 5 et 200 caractères.');
        expect(validatePoll({ ...emptyPoll(), question: 'é'.repeat(200), options: ['A', 'B'] }, noMedia(), limits, t).question).toBeUndefined();
        expect(validatePoll({ ...emptyPoll(), question: 'x'.repeat(201), options: ['A', 'B'] }, noMedia(), limits, t).question).toBeDefined();
    });

    it('exige 2 à 6 réponses distinctes, vides ignorées, casse et accents ignorés', () => {
        const base = { ...emptyPoll(), question: 'Quel thème ?' };
        expect(validatePoll({ ...base, options: ['Santé', '  '] }, noMedia(), limits, t).options).toBe('Renseigne au moins 2 réponses différentes.');
        expect(validatePoll({ ...base, options: ['Santé', 'SANTE'] }, noMedia(), limits, t).options).toBe('Deux réponses du sondage sont identiques.');
        expect(validatePoll({ ...base, options: ['1', '2', '3', '4', '5', '6', '7'] }, noMedia(), limits, t).options).toContain('entre 2 et 6');
        expect(validatePoll({ ...base, options: ['Santé', 'Droits', ''] }, noMedia(), limits, t)).toEqual({});
        expect(normalizeOption(' Bien-Être ')).toBe('bien-etre');
    });
});

describe('validatePost', () => {
    it('demande un message ou une image, et borne le message', () => {
        expect(validatePost(emptyPost(), noMedia(), limits, t).body).toBe('Écris un message avant de publier.');
        expect(validatePost(emptyPost(), images(item(1)), limits, t)).toEqual({});
        expect(validatePost({ ...emptyPost(), body: 'x'.repeat(3001) }, noMedia(), limits, t).body).toBe('3000 caractères maximum.');
    });

    it('refuse une image de plus de 5 Mo ou d’un autre format', () => {
        expect(imageError(file(5 * 1024 * 1024 + 1), limits, t)).toBe('Image trop lourde (5 Mo maximum).');
        expect(imageError(file(10, 'image/gif'), limits, t)).toContain('JPG, PNG ou WebP');
        expect(imageError(file(5 * 1024 * 1024), limits, t)).toBeNull();
    });
});

describe('photos et vidéo', () => {
    it('borne le nombre de photos au plafond du serveur (4 par défaut) et lit celui de la page', () => {
        const five = images(item(1), item(2), item(3), item(4), item(5));
        expect(validatePost({ ...emptyPost(), body: 'x' }, five, limits, t).images).toBe('Tu peux joindre au plus 4 photos à une publication.');
        expect(validatePost({ ...emptyPost(), body: 'x' }, five, { ...limits, max_images: 6 }, t)).toEqual({});
    });

    it('rattache les erreurs au rang de la photo : fichier trop lourd, texte alternatif trop long', () => {
        const errors = validatePost(emptyPost(), images(item(1), item(2, '', 5 * 1024 * 1024 + 1), item(3, 'é'.repeat(256))), limits, t);
        expect(errors['images.1']).toBe('Image trop lourde (5 Mo maximum).');
        expect(errors['images_alt.2']).toBe('La description ne peut pas dépasser 255 caractères.');
        expect(errors['images.0']).toBeUndefined();
        expect(errors.body).toBeUndefined();
    });

    it('exige la description (5 à 500) et la prise de connaissance de l’avertissement pour une vidéo', () => {
        expect(validatePost(emptyPost(), video({ description: '' }), limits, t).video_description).toBe('Décris ta vidéo en quelques mots pour les personnes qui ne peuvent pas la regarder.');
        expect(validatePost(emptyPost(), video({ description: 'abc' }), limits, t).video_description).toBe('La description de ta vidéo doit faire entre 5 et 500 caractères.');
        expect(validatePost(emptyPost(), video({ description: 'x'.repeat(501) }), limits, t).video_description).toBeDefined();
        expect(validatePost(emptyPost(), video({ acknowledged: false }), limits, t).video_ack).toBe('Coche la case pour confirmer que tu as lu l’avertissement.');
        expect(validatePost(emptyPost(), video(), limits, t)).toEqual({});
    });

    it('refuse une vidéo d’un autre format ou plus lourde que la limite, et un mélange image + vidéo', () => {
        expect(videoError(file(10, 'video/quicktime', 'a.mov'), limits, t)).toBe('Format non pris en charge. Choisis une vidéo MP4 ou WebM.');
        expect(videoError(file(31 * 1024 * 1024, 'video/mp4', 'a.mp4'), { ...limits, video_max_kb: 30 * 1024 }, t)).toBe('Vidéo trop lourde (30 Mo maximum).');
        expect(videoError(file(10, '', 'a.webm'), limits, t)).toBeNull();
        expect(videoError(file(10, 'video/webm', 'a.webm'), { ...limits, video_types: ['video/mp4'] }, t)).toContain('MP4 ou WebM');
        const mixed: ComposerMedia = { images: [item(1)], video: video().video };
        expect(validatePost(emptyPost(), mixed, limits, t).video).toBe('Une vidéo ne s’ajoute pas à des images\u00a0: choisis l’une ou les autres.');
        expect(validatePoll({ ...emptyPoll(), question: 'Quel thème ?', options: ['A', 'B'] }, video(), limits, t).video).toBe('Une vidéo ne peut pas accompagner un sondage.');
        expect(megabytes(8 * 1024 * 1024 + 300_000)).toBe('8,3');
    });
});

describe('corps de POST posts', () => {
    it('ramène « abonné·es » à « public » pour une publication anonyme (RG-02)', () => {
        expect(allowedVisibility('followers', true)).toBe('public');
        expect(allowedVisibility('followers', false)).toBe('followers');
        expect(requestBody('post', { ...emptyPost(), body: 'Bonjour', visibility: 'followers', is_anonymous: true }, noMedia())).toMatchObject({ visibility: 'public', is_anonymous: true });
    });

    it('envoie un sondage en JSON sans image, réponses vides retirées', () => {
        const body = requestBody('poll', { ...emptyPoll(7), question: ' Quel thème ? ', options: ['Santé', ' ', 'Droits'], multiple: true }, noMedia());
        expect(body).toEqual({
            kind: 'poll', visibility: 'public', is_anonymous: false,
            poll: { question: 'Quel thème ?', options: ['Santé', 'Droits'], multiple: true, results_before_vote: false, duration_days: 7 },
        });
    });

    it('passe en multipart : images[] et images_alt[] alignés (vide sans texte), dans l’ordre de la liste', () => {
        const form = requestBody('post', { ...emptyPost('question'), body: 'Avec photos' }, images(item(1, 'Atelier'), item(2), item(3, ' Le groupe '))) as FormData;
        expect(form.get('kind')).toBe('post');
        expect(form.get('tag')).toBe('question');
        expect(form.get('is_anonymous')).toBe('0');
        expect((form.getAll('images[]') as File[]).map((f) => f.name)).toEqual(['photo-1.jpg', 'photo-2.jpg', 'photo-3.jpg']);
        expect(form.getAll('images_alt[]')).toEqual(['Atelier', '', 'Le groupe']);
        expect(form.has('video')).toBe(false);
    });

    it('envoie une vidéo avec sa description, jamais avec des images, et un sondage sans vidéo', () => {
        const form = requestBody('post', { ...emptyPost(), body: 'Notre atelier' }, video({ description: ' Des femmes assises en cercle. ' })) as FormData;
        expect((form.get('video') as File).name).toBe('atelier.mp4');
        expect(form.get('video_description')).toBe('Des femmes assises en cercle.');
        expect(form.has('images[]')).toBe(false);
        const poll = requestBody('poll', { ...emptyPoll(), question: 'Quel thème ?', options: ['A', 'B'] }, video()) as FormData;
        expect(poll.has('video')).toBe(false);
    });

    it('garde le corps hors connexion en JSON, sans média', () => {
        expect(outboxBody('poll', { ...emptyPoll(), question: 'Quel thème ?', options: ['A', 'B'] })).toMatchObject({ kind: 'poll', poll: { options: ['A', 'B'], duration_days: 7 } });
        expect(outboxBody('post', { ...emptyPost(), body: 'Sans photo' })).toMatchObject({ kind: 'post', body: 'Sans photo' });
    });

    it('ramène les erreurs 422 du serveur aux champs de la modale (images.N et images_alt.N gardent leur rang)', () => {
        expect(formErrors({ 'poll.question': 'Q', 'poll.options.1': 'O', 'poll.options': 'O2', body: 'B', 'poll.duration_days': 'D' }))
            .toEqual({ question: 'Q', options: 'O', body: 'B', duration_days: 'D' });
        expect(formErrors({ 'images.2': 'I', 'images_alt.0': 'A', video_description: 'V' })).toEqual({ 'images.2': 'I', 'images_alt.0': 'A', video_description: 'V' });
    });
});
