// Logique des modales « Créer une publication » et « Créer un sondage » (lot fil-ecrans, P7-04 ; prototype
// partials/modals.php, assets/js/xomiton.js) sans composant : validation (mêmes bornes que StorePostRequest, reçues
// en props `composer.limits`), corps de `POST /api/v1/posts` (JSON, ou multipart avec l'image), corps de la boîte
// d'envoi hors connexion (shared/outbox.js : { form: [[nom, valeur | Blob]] } ou objet JSON), brouillon sans fichier et
// erreurs 422 du serveur ramenées aux champs de la modale.
import type { ComposerOptions } from './types';

type Translate = (key: string, named?: Record<string, unknown>) => string;
type Limits = ComposerOptions['limits'];

/** Noms des brouillons (store `drafts`, clés `vue:<compte>:<nom>`). */
export const DRAFT_POST = 'feed.post';
export const DRAFT_POLL = 'feed.poll';

export interface PostForm {
    body: string;
    tag: string;
    visibility: string;
    is_anonymous: boolean;
    image_alt: string;
}

export interface PollForm {
    question: string;
    options: string[];
    body: string;
    duration_days: number;
    visibility: string;
    multiple: boolean;
    results_before_vote: boolean;
    is_anonymous: boolean;
}

export type FormErrors = Record<string, string>;

export const emptyPost = (tag = 'temoignage'): PostForm => ({ body: '', tag, visibility: 'public', is_anonymous: false, image_alt: '' });

export const emptyPoll = (duration = 7): PollForm => ({
    question: '', options: ['', ''], body: '', duration_days: duration, visibility: 'public', multiple: false, results_before_vote: false, is_anonymous: false,
});

/** Nombre de caractères tel que le compte le serveur (mb_strlen) : points de code. */
export const length = (value: string): number => Array.from(value).length;

/** Comparaison des réponses d'un sondage : casse et accents ignorés (StorePostRequest). */
export const normalizeOption = (value: string): string => value.normalize('NFD').replace(/\p{M}/gu, '').trim().toLocaleLowerCase('fr');

/** Anonyme + abonné·es refusé (RG-02, Q72) : la visibilité revient à « public ». */
export const allowedVisibility = (visibility: string, anonymous: boolean): string => (anonymous && visibility === 'followers' ? 'public' : visibility);

/** Image : type et poids (collection `images` de MediaUploader). */
export function imageError(file: File | null, limits: Limits, t: Translate): string | null {
    if (!file) return null;
    if (limits.image_types.length && !limits.image_types.includes(file.type)) return t('feed.create.photo_type');
    if (file.size > limits.image_max_kb * 1024) return t('feed.toasts.image_too_large', { max: Math.floor(limits.image_max_kb / 1024) });
    return null;
}

export function validatePost(form: PostForm, image: File | null, limits: Limits, t: Translate): FormErrors {
    const errors: FormErrors = {};
    const body = form.body.trim();
    if (body === '' && !image) errors.body = t('feed.errors.text_required');
    else if (length(form.body) > limits.body_max) errors.body = t('feed.create.text_max', { max: limits.body_max });
    const photo = imageError(image, limits, t);
    if (photo) errors.image = photo;
    return errors;
}

export function validatePoll(form: PollForm, image: File | null, limits: Limits, t: Translate): FormErrors {
    const errors: FormErrors = {};
    const question = length(form.question.trim());
    if (question < limits.question_min || question > limits.question_max) {
        errors.question = t('feed.poll.question_error', { min: limits.question_min, max: limits.question_max });
    }
    const filled = form.options.map((option) => option.trim()).filter((option) => option !== '');
    const distinct = new Set(filled.map(normalizeOption));
    if (filled.length < limits.options_min) errors.options = t('feed.poll.options_error', { min: limits.options_min });
    else if (filled.length > limits.options_max) errors.options = t('feed.poll.options_count_error', { min: limits.options_min, max: limits.options_max });
    else if (distinct.size !== filled.length) errors.options = t('feed.poll.options_duplicate');
    else if (filled.some((option) => length(option) > limits.option_max)) errors.options = t('feed.create.text_max', { max: limits.option_max });
    if (length(form.body) > limits.body_max) errors.body = t('feed.create.text_max', { max: limits.body_max });
    const photo = imageError(image, limits, t);
    if (photo) errors.image = photo;
    return errors;
}

/** Champs de `POST posts` (§8.3) sous forme de paires, valeurs déjà converties (booléens 1 / 0). */
export function fields(kind: 'post' | 'poll', form: PostForm | PollForm): Array<[string, string]> {
    const pairs: Array<[string, string]> = [['kind', kind]];
    const anonymous = form.is_anonymous;
    pairs.push(['visibility', allowedVisibility(form.visibility, anonymous)], ['is_anonymous', anonymous ? '1' : '0']);
    if (form.body.trim() !== '') pairs.push(['body', form.body.trim()]);
    if (kind === 'post') {
        const post = form as PostForm;
        pairs.push(['tag', post.tag]);
    } else {
        const poll = form as PollForm;
        pairs.push(['poll[question]', poll.question.trim()]);
        poll.options.map((option) => option.trim()).filter((option) => option !== '').forEach((option) => pairs.push(['poll[options][]', option]));
        pairs.push(['poll[multiple]', poll.multiple ? '1' : '0'], ['poll[results_before_vote]', poll.results_before_vote ? '1' : '0'], ['poll[duration_days]', String(poll.duration_days)]);
    }
    return pairs;
}

/** Corps JSON (sans image) : même contenu que `fields()`. */
export function jsonBody(kind: 'post' | 'poll', form: PostForm | PollForm): Record<string, unknown> {
    const anonymous = form.is_anonymous;
    const body: Record<string, unknown> = { kind, visibility: allowedVisibility(form.visibility, anonymous), is_anonymous: anonymous };
    if (form.body.trim() !== '') body.body = form.body.trim();
    if (kind === 'post') {
        body.tag = (form as PostForm).tag;
    } else {
        const poll = form as PollForm;
        body.poll = {
            question: poll.question.trim(),
            options: poll.options.map((option) => option.trim()).filter((option) => option !== ''),
            multiple: poll.multiple,
            results_before_vote: poll.results_before_vote,
            duration_days: poll.duration_days,
        };
    }
    return body;
}

/** Corps de l'envoi : objet JSON, ou FormData avec l'image (et sa description). */
export function requestBody(kind: 'post' | 'poll', form: PostForm | PollForm, image: File | null): FormData | Record<string, unknown> {
    if (!image) return jsonBody(kind, form);
    const data = new FormData();
    fields(kind, form).forEach(([name, value]) => data.append(name, value));
    data.append('image', image, image.name);
    const alt = kind === 'post' ? (form as PostForm).image_alt.trim() : '';
    if (alt !== '') data.append('image_alt', alt);
    return data;
}

/** Ajoute le groupe de la publication (`group`, P8-02) au corps JSON, au FormData ou aux paires de la boîte d'envoi. */
export function withGroup<B extends FormData | Record<string, unknown> | { form: Array<[string, string | Blob]> }>(body: B, group: string | null | undefined): B {
    if (!group) return body;
    if (typeof FormData !== 'undefined' && body instanceof FormData) body.append('group', group);
    else if ('form' in body && Array.isArray(body.form)) body.form.push(['group', group]);
    else (body as Record<string, unknown>).group = group;
    return body;
}

/** Corps pour la boîte d'envoi (shared/outbox.js) : multipart rejouable `{ form }` ou objet JSON. */
export function outboxBody(kind: 'post' | 'poll', form: PostForm | PollForm, image: File | null): { form: Array<[string, string | Blob]> } | Record<string, unknown> {
    if (!image) return jsonBody(kind, form);
    const pairs: Array<[string, string | Blob]> = fields(kind, form);
    pairs.push(['image', image]);
    const alt = kind === 'post' ? (form as PostForm).image_alt.trim() : '';
    if (alt !== '') pairs.push(['image_alt', alt]);
    return { form: pairs };
}

/** Erreurs 422 (`errors` de l'API, première par champ) ramenées aux champs de la modale. */
export function formErrors(errors: Record<string, string>): FormErrors {
    const mapped: FormErrors = {};
    Object.entries(errors).forEach(([field, message]) => {
        let target = field;
        if (field === 'poll.question') target = 'question';
        else if (field === 'poll.options' || field.startsWith('poll.options.')) target = 'options';
        else if (field === 'poll.duration_days') target = 'duration_days';
        else if (field.startsWith('poll.')) target = field.slice(5);
        if (!(target in mapped)) mapped[target] = message;
    });
    return mapped;
}
