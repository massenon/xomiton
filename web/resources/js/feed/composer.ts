// Logique des modales « Créer une publication » et « Créer un sondage » (lot fil-ecrans, P7-04 ; prototype
// partials/modals.php, assets/js/xomiton.js) sans composant : validation (mêmes bornes que StorePostRequest, reçues
// en props `composer.limits`), corps de `POST /api/v1/posts` (JSON, ou multipart avec `images[]` + `images_alt[]` alignés,
// ou `video` + `video_description`, docs/API.md §8.3 et §8.7), corps de la boîte d'envoi hors connexion (JSON : texte et
// sondage seulement, jamais de média), brouillon sans fichier et erreurs 422 du serveur ramenées aux champs de la
// modale (`images.N` et `images_alt.N` : l'image de même rang).
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
}

/** Image choisie : fichier, texte alternatif et aperçu (URL objet libérée au retrait). Jamais dans le brouillon. */
export interface ImageItem {
    id: number;
    file: File;
    alt: string;
    preview: string | null;
}

/** Vidéo choisie : description (obligatoire) et prise de connaissance de l'avertissement de vie privée. */
export interface VideoItem {
    file: File;
    description: string;
    acknowledged: boolean;
}

export interface ComposerMedia {
    images: ImageItem[];
    video: VideoItem | null;
}

export const noMedia = (): ComposerMedia => ({ images: [], video: null });

export const hasMedia = (media: ComposerMedia): boolean => media.images.length > 0 || media.video !== null;

/** Plafonds de média avec les valeurs par défaut du serveur (docs/API.md §8.7) pour une page qui ne les enverrait pas. */
export function mediaLimits(limits: Limits) {
    return {
        maxImages: Math.max(1, limits.max_images ?? 4),
        videoMaxKb: limits.video_max_kb ?? 30 * 1024,
        videoTypes: limits.video_types?.length ? limits.video_types : ['video/mp4', 'video/webm'],
        descriptionMin: limits.video_description_min ?? 5,
        descriptionMax: limits.video_description_max ?? 500,
        altMax: 255,
    };
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

export const emptyPost = (tag = 'temoignage'): PostForm => ({ body: '', tag, visibility: 'public', is_anonymous: false });

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

/** Vidéo : type (mp4 ou webm, l'extension fait foi si le navigateur ne donne pas de type) et poids (`video_max_kb`). */
export function videoError(file: File | null, limits: Limits, t: Translate): string | null {
    if (!file) return null;
    const { videoTypes, videoMaxKb } = mediaLimits(limits);
    const byExtension = /\.(mp4|webm)$/i.test(file.name) && file.type === '';
    if (!videoTypes.includes(file.type) && !byExtension) return t('feed.video.type_error');
    if (file.size > videoMaxKb * 1024) return t('feed.video.too_large', { max: Math.floor(videoMaxKb / 1024) });
    return null;
}

/** Poids lisible en Mo (une décimale, virgule française), pour « Vidéo choisie : … ». */
export const megabytes = (bytes: number): string => (Math.round((bytes / (1024 * 1024)) * 10) / 10).toString().replace('.', ',');

/** Photos : plafond, poids et type de chacune, texte alternatif ≤ 255 (mêmes règles que StorePostRequest) ; erreurs par rang. */
function imagesErrors(images: ImageItem[], limits: Limits, t: Translate): FormErrors {
    const errors: FormErrors = {};
    const { maxImages, altMax } = mediaLimits(limits);
    if (images.length > maxImages) errors.images = t('feed.media.images_max', { max: maxImages });
    images.forEach((item, index) => {
        const problem = imageError(item.file, limits, t);
        if (problem) errors[`images.${index}`] = problem;
        if (length(item.alt) > altMax) errors[`images_alt.${index}`] = t('feed.media.image_alt_max', { max: altMax });
    });
    return errors;
}

export function validatePost(form: PostForm, media: ComposerMedia, limits: Limits, t: Translate): FormErrors {
    const errors: FormErrors = {};
    const body = form.body.trim();
    if (body === '' && !hasMedia(media)) errors.body = t('feed.errors.text_required');
    else if (length(form.body) > limits.body_max) errors.body = t('feed.create.text_max', { max: limits.body_max });
    Object.assign(errors, imagesErrors(media.images, limits, t));
    if (media.video) {
        const { descriptionMin, descriptionMax } = mediaLimits(limits);
        const description = length(media.video.description.trim());
        const file = videoError(media.video.file, limits, t);
        if (media.images.length > 0) errors.video = t('feed.video.not_with_images');
        else if (file) errors.video = file;
        if (description === 0) errors.video_description = t('feed.video.description_required');
        else if (description < descriptionMin || description > descriptionMax) errors.video_description = t('feed.video.description_length', { min: descriptionMin, max: descriptionMax });
        if (!media.video.acknowledged) errors.video_ack = t('feed.video.acknowledge_required');
    }
    return errors;
}

export function validatePoll(form: PollForm, media: ComposerMedia, limits: Limits, t: Translate): FormErrors {
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
    Object.assign(errors, imagesErrors(media.images, limits, t));
    if (media.video) errors.video = t('feed.video.not_in_poll');
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

/**
 * Corps de l'envoi : objet JSON sans média, sinon FormData : `images[]` et `images_alt[]` **alignés** (une entrée par
 * image, vide si elle n'a pas de texte : l'ordre des fichiers est l'ordre de publication), ou `video` et
 * `video_description` (docs/API.md §8.3).
 */
export function requestBody(kind: 'post' | 'poll', form: PostForm | PollForm, media: ComposerMedia = noMedia()): FormData | Record<string, unknown> {
    if (!hasMedia(media)) return jsonBody(kind, form);
    const data = new FormData();
    fields(kind, form).forEach(([name, value]) => data.append(name, value));
    media.images.forEach((item) => {
        data.append('images[]', item.file, item.file.name);
        data.append('images_alt[]', item.alt.trim());
    });
    if (media.video && kind === 'post') {
        data.append('video', media.video.file, media.video.file.name);
        data.append('video_description', media.video.description.trim());
    }
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

/**
 * Corps pour la boîte d'envoi (shared/outbox.js) : objet JSON. Seuls un texte et un sondage **sans média** partent hors
 * connexion (les fichiers ne sont pas rejoués : 30 Mo de vidéo n'ont rien à faire dans IndexedDB) ; avec des photos ou une
 * vidéo, l'envoi exige le réseau (feed.media.needs_network) et le brouillon reste.
 */
export function outboxBody(kind: 'post' | 'poll', form: PostForm | PollForm): Record<string, unknown> {
    return jsonBody(kind, form);
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
