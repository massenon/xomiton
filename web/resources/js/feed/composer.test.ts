// @vitest-environment jsdom
// Logique des modales de création (lot fil-ecrans, P7-04) : mêmes règles que StorePostRequest (question 5–200, 2 à 6
// réponses distinctes casse et accents ignorés, texte ≤ 3000, image JPG / PNG / WebP ≤ 5 Mo), anonyme jamais
// « abonné·es », corps JSON ou multipart, corps rejouable de la boîte d'envoi, erreurs 422 ramenées aux champs.
import { describe, expect, it } from 'vitest';
import { i18n } from '@/app/i18n';
import { useFrench } from '@/test/helpers';
import { composerOptions } from '@/test/feed';
import { allowedVisibility, emptyPoll, emptyPost, formErrors, imageError, normalizeOption, outboxBody, requestBody, validatePoll, validatePost } from './composer';

useFrench();
const t = i18n.global.t as unknown as (key: string, named?: Record<string, unknown>) => string;
const limits = composerOptions().limits;
const file = (size: number, type = 'image/jpeg') => new File([new Uint8Array(size)], 'photo.jpg', { type });

describe('validatePoll', () => {
    it('borne la question de 5 à 200 caractères (points de code, comme mb_strlen)', () => {
        expect(validatePoll({ ...emptyPoll(), question: 'Quoi', options: ['A', 'B'] }, null, limits, t).question).toBe('Entre 5 et 200 caractères.');
        expect(validatePoll({ ...emptyPoll(), question: 'é'.repeat(200), options: ['A', 'B'] }, null, limits, t).question).toBeUndefined();
        expect(validatePoll({ ...emptyPoll(), question: 'x'.repeat(201), options: ['A', 'B'] }, null, limits, t).question).toBeDefined();
    });

    it('exige 2 à 6 réponses distinctes, vides ignorées, casse et accents ignorés', () => {
        const base = { ...emptyPoll(), question: 'Quel thème ?' };
        expect(validatePoll({ ...base, options: ['Santé', '  '] }, null, limits, t).options).toBe('Renseigne au moins 2 réponses différentes.');
        expect(validatePoll({ ...base, options: ['Santé', 'SANTE'] }, null, limits, t).options).toBe('Deux réponses du sondage sont identiques.');
        expect(validatePoll({ ...base, options: ['1', '2', '3', '4', '5', '6', '7'] }, null, limits, t).options).toContain('entre 2 et 6');
        expect(validatePoll({ ...base, options: ['Santé', 'Droits', ''] }, null, limits, t)).toEqual({});
        expect(normalizeOption(' Bien-Être ')).toBe('bien-etre');
    });
});

describe('validatePost', () => {
    it('demande un message ou une image, et borne le message', () => {
        expect(validatePost(emptyPost(), null, limits, t).body).toBe('Écris un message avant de publier.');
        expect(validatePost(emptyPost(), file(1000), limits, t)).toEqual({});
        expect(validatePost({ ...emptyPost(), body: 'x'.repeat(3001) }, null, limits, t).body).toBe('3000 caractères maximum.');
    });

    it('refuse une image de plus de 5 Mo ou d’un autre format', () => {
        expect(imageError(file(5 * 1024 * 1024 + 1), limits, t)).toBe('Image trop lourde (5 Mo maximum).');
        expect(imageError(file(10, 'image/gif'), limits, t)).toContain('JPG, PNG ou WebP');
        expect(imageError(file(5 * 1024 * 1024), limits, t)).toBeNull();
    });
});

describe('corps de POST posts', () => {
    it('ramène « abonné·es » à « public » pour une publication anonyme (RG-02)', () => {
        expect(allowedVisibility('followers', true)).toBe('public');
        expect(allowedVisibility('followers', false)).toBe('followers');
        expect(requestBody('post', { ...emptyPost(), body: 'Bonjour', visibility: 'followers', is_anonymous: true }, null)).toMatchObject({ visibility: 'public', is_anonymous: true });
    });

    it('envoie un sondage en JSON sans image, réponses vides retirées', () => {
        const body = requestBody('poll', { ...emptyPoll(7), question: ' Quel thème ? ', options: ['Santé', ' ', 'Droits'], multiple: true }, null);
        expect(body).toEqual({
            kind: 'poll', visibility: 'public', is_anonymous: false,
            poll: { question: 'Quel thème ?', options: ['Santé', 'Droits'], multiple: true, results_before_vote: false, duration_days: 7 },
        });
    });

    it('passe en multipart avec l’image, et garde l’image dans le corps rejouable de la boîte d’envoi', () => {
        const image = file(100);
        const form = requestBody('post', { ...emptyPost('question'), body: 'Avec photo', image_alt: 'Atelier' }, image) as FormData;
        expect(form.get('kind')).toBe('post');
        expect(form.get('tag')).toBe('question');
        expect(form.get('is_anonymous')).toBe('0');
        expect(form.get('image_alt')).toBe('Atelier');
        expect((form.get('image') as File).name).toBe('photo.jpg');

        const queued = outboxBody('poll', { ...emptyPoll(), question: 'Quel thème ?', options: ['A', 'B'] }, image) as { form: Array<[string, unknown]> };
        expect(queued.form).toContainEqual(['poll[options][]', 'A']);
        expect(queued.form).toContainEqual(['poll[duration_days]', '7']);
        expect(queued.form.find(([name]) => name === 'image')?.[1]).toBe(image);
        expect(outboxBody('post', { ...emptyPost(), body: 'Sans photo' }, null)).toMatchObject({ kind: 'post', body: 'Sans photo' });
    });

    it('ramène les erreurs 422 du serveur aux champs de la modale', () => {
        expect(formErrors({ 'poll.question': 'Q', 'poll.options.1': 'O', 'poll.options': 'O2', body: 'B', 'poll.duration_days': 'D' }))
            .toEqual({ question: 'Q', options: 'O', body: 'B', duration_days: 'D' });
    });
});
