// État partagé des modales « Créer une publication » et « Créer un sondage » (lot fil-ecrans, P7-04) :
//  - brouillon IndexedDB (store `drafts`, clé par compte, expiré après `ttlDays`) : relu à l'ouverture, écrit à chaque
//    saisie (différé de 400 ms), effacé après l'envoi en ligne ; jamais un fichier (photos, vidéo, leurs textes) ni un
//    champ de EXCLUDED_DRAFT_FIELDS (sanitizeDraft du store) ;
//  - mise en file : le brouillon reste, lié à l'entrée (`outbox` = sa clé) jusqu'à l'envoi confirmé ; au rejeu,
//    public/outbox-notices.js l'efface (envoyée) ou y note le refus (`failure` : statut et erreurs, 422 filtre de mots,
//    403 e-mail non confirmé…). Rouvert, il garde la même clé (rejeu sans doublon ; un refus libère la clé côté
//    serveur) : contenu et erreurs retrouvés, ou mention « en attente » tant que l'entrée attend (la nouvelle version
//    la remplace) ;
//  - photos (P7-10) : plusieurs (`limits.max_images`), type et poids de chacune contrôlés, texte alternatif par photo,
//    retrait et réordonnancement (Monter / Descendre, annoncés), aperçu par URL objet libérée au retrait et à la
//    fermeture ; vidéo (publication seulement, exclusive des photos) : type, poids, description 5–500, case de prise de
//    connaissance de l'avertissement de vie privée, progression (XMLHttpRequest) et annulation ;
//  - envoi `POST /api/v1/posts` avec une Idempotency-Key gardée jusqu'au succès (ADR-32 : un double clic ou un rejeu
//    ne crée jamais deux publications) ; 201 → en ligne ; 202 → vidéo reçue, en relecture (jamais insérée dans le
//    fil) ; 422 → erreurs par champ (`images.N` : la photo de même rang), 403 / 429 → message dans la modale ;
//  - hors connexion (navigator.onLine faux, ou réseau coupé pendant l'envoi) : un texte ou un sondage SANS média est mis
//    en file dans le store `outbox` (shared/outbox.js, même clé, JSON), puis envoyé au retour du réseau ; avec des
//    photos ou une vidéo, rien n'est mis en file (30 Mo n'ont rien à faire dans IndexedDB) : message clair, brouillon
//    et fichiers gardés, nouvel essai à la main.
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch, type UnwrapRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { useDraftsStore } from '@/stores/drafts';
import { useOutboxStore } from '@/stores/outbox';
import { POSTS_URL, feedApi } from './api';
import { formErrors, hasMedia, imageError, mediaLimits, outboxBody, requestBody, videoError, withGroup, type ComposerMedia, type FormErrors, type ImageItem, type PollForm, type PostForm, type VideoItem } from './composer';
import type { ComposerOptions, Post } from './types';

export const DRAFT_DELAY = 400;

/** Type d'entrée de la boîte d'envoi (shared/outbox.js : priorité par défaut, après SOS et signalements). */
export const OUTBOX_TYPE = 'post';

const newKey = (): string => {
    const cryptoImpl = globalThis.crypto;
    if (cryptoImpl && typeof cryptoImpl.randomUUID === 'function') return cryptoImpl.randomUUID();
    const bytes = new Uint8Array(16);
    cryptoImpl?.getRandomValues?.(bytes);
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
};

/** Refus au rejeu d'une publication mise en file (résultat `failed` de shared/outbox.js), gardé dans son brouillon. */
export interface OutboxFailure {
    status: number;
    errors?: Record<string, string[] | string>;
}

/** Refus annoncé par la page (store `outbox`, lastFlush) avant que le brouillon ne le porte. */
export interface RecoveredEntry extends OutboxFailure {
    id: string;
}

/** Marques du brouillon d'une publication mise en file (jamais renvoyées au serveur). */
interface DraftLink {
    outbox?: string;
    failure?: OutboxFailure;
}

const firstErrors = (errors: OutboxFailure['errors']): Record<string, string> => Object.fromEntries(Object.entries(errors ?? {})
    .map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] ?? '' : String(messages)]));

export interface ComposerSetup<F extends PostForm | PollForm> {
    kind: 'post' | 'poll';
    draft: string;
    /** Refus connu de la page pour l'entrée liée au brouillon. */
    recovered?: RecoveredEntry | null;
    options: ComposerOptions;
    initial: () => F;
    validate: (form: F, media: ComposerMedia) => FormErrors;
    /** Publication en ligne (201). */
    onPublished: (post: Post) => void;
    /** Vidéo reçue et mise en relecture (202) : jamais ajoutée au fil. */
    onSubmitted?: (post: Post) => void;
    /** Fermeture voulue (succès ou mise en file). */
    close: () => void;
    /** Tests : réseau supposé présent / absent. */
    online?: () => boolean;
}

export function useComposer<F extends PostForm | PollForm>(setup: ComposerSetup<F>) {
    const { t } = useI18n();
    const toast = useToast();
    const drafts = useDraftsStore();
    const outbox = useOutboxStore();
    const api = feedApi(useApi());
    const online = setup.online ?? (() => typeof navigator === 'undefined' || navigator.onLine !== false);

    const form = reactive(setup.initial()) as UnwrapRef<F> & F;
    const images = ref<ImageItem[]>([]);
    const video = ref<VideoItem | null>(null);
    /** Progression de l'envoi d'une vidéo (0–100), nulle hors envoi ; `progressText` : annonce par paliers de 25 %. */
    const progress = ref<number | null>(null);
    const progressText = ref('');
    /** Message poli annoncé aux lecteurs d'écran (ajout, retrait, déplacement d'une photo). */
    const announcement = ref('');
    const cancelled = ref(false);
    const errors = ref<FormErrors>({});
    const failure = ref('');
    const busy = ref(false);
    const restored = ref(false);
    /** Entrée liée encore dans la boîte d'envoi : la publication attend le réseau. */
    const waiting = ref(false);
    const ready = ref(false);
    let key = newKey();
    /** Clé de l'entrée de la boîte d'envoi liée au brouillon (mise en file non confirmée). */
    let linked: string | null = null;
    let timer: ReturnType<typeof setTimeout> | null = null;

    let counter = 0;
    let controller: AbortController | null = null;
    const media = computed<ComposerMedia>(() => ({ images: images.value, video: video.value }));
    const withMedia = computed(() => hasMedia(media.value));
    const limits = () => mediaLimits(setup.options.limits);

    const revoke = (item: ImageItem) => {
        if (item.preview && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(item.preview);
    };

    /** Erreurs rattachées au rang d'une photo (`images.N`, `images_alt.N`) : périmées dès que la liste change. */
    const dropIndexedErrors = (...names: string[]) => {
        errors.value = Object.fromEntries(Object.entries(errors.value).filter(([key]) => !names.some((name) => key === name || key.startsWith(`${name}.`))));
    };

    function clearMedia() {
        images.value.forEach(revoke);
        images.value = [];
        video.value = null;
        progress.value = null;
        progressText.value = '';
        cancelled.value = false;
    }

    /** Ajoute des photos (sélection multiple) : chacune est contrôlée, le plafond est respecté, le reste est dit. */
    function addImages(files: File[]) {
        if (video.value || files.length === 0) return;
        dropIndexedErrors('images', 'images_alt', 'video');
        const { maxImages } = limits();
        const added: ImageItem[] = [];
        const notes: string[] = [];
        let overflow = false;
        for (const file of files) {
            const problem = imageError(file, setup.options.limits, t);
            if (problem) {
                notes.push(t('feed.media.images_rejected', { name: file.name, problem }));
            } else if (images.value.length + added.length >= maxImages) {
                overflow = true;
            } else {
                const preview = typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function' ? URL.createObjectURL(file) : null;
                added.push({ id: ++counter, file, alt: '', preview });
            }
        }
        if (overflow) notes.push(t('feed.media.images_over_limit', { max: maxImages }));
        if (notes.length > 0) errors.value = { ...errors.value, images: notes.join(' ') };
        if (added.length > 0) {
            images.value = [...images.value, ...added];
            announcement.value = t('feed.media.image_added', { count: added.length, total: images.value.length, max: maxImages }, added.length);
        }
    }

    /** Texte alternatif d'une photo (le serveur l'aligne sur `images[]`) : son erreur de rang périmée disparaît. */
    function setAlt(id: number, alt: string) {
        const index = images.value.findIndex((item) => item.id === id);
        if (index < 0) return;
        images.value[index].alt = alt;
        dropIndexedErrors(`images_alt.${index}`);
    }

    /** Retire une photo ; rend son rang (le champ qui prend sa place reçoit le focus côté écran). */
    function removeImage(id: number): number {
        const index = images.value.findIndex((item) => item.id === id);
        if (index < 0) return -1;
        revoke(images.value[index]);
        images.value = images.value.filter((item) => item.id !== id);
        dropIndexedErrors('images', 'images_alt');
        announcement.value = t('feed.media.image_removed', { count: images.value.length, max: limits().maxImages });
        return index;
    }

    /** Monte (-1) ou descend (+1) une photo ; l'ordre de la liste est l'ordre de publication. Rend le nouveau rang. */
    function moveImage(id: number, delta: -1 | 1): number {
        const index = images.value.findIndex((item) => item.id === id);
        const target = index + delta;
        if (index < 0 || target < 0 || target >= images.value.length) return index;
        const next = [...images.value];
        [next[index], next[target]] = [next[target], next[index]];
        images.value = next;
        dropIndexedErrors('images', 'images_alt');
        announcement.value = t('feed.media.image_moved', { to: target + 1, count: next.length });
        return target;
    }

    /** Choisit (ou retire, `null`) la vidéo : type et poids contrôlés, description et case à remplir ensuite. */
    function setVideo(file: File | null) {
        dropIndexedErrors('video', 'video_description', 'video_ack');
        cancelled.value = false;
        if (!file || images.value.length > 0) {
            video.value = null;
            return;
        }
        const problem = videoError(file, setup.options.limits, t);
        if (problem) {
            video.value = null;
            errors.value = { ...errors.value, video: problem };
            return;
        }
        video.value = { file, description: '', acknowledged: false };
    }

    /** Annule l'envoi en cours : rien n'est publié, le formulaire et la vidéo restent. */
    function cancel() {
        controller?.abort();
    }

    const snapshot = (): F => JSON.parse(JSON.stringify(form)) as F;

    const draftValue = (): F & DraftLink => (linked ? { ...snapshot(), outbox: linked } : snapshot());

    const saveDraft = () => drafts.save(setup.draft, draftValue()).catch(() => undefined);

    /** Brouillon d'une publication mise en file : même clé, refus affiché, ou mention « en attente ». */
    async function relink(id: string, refused: OutboxFailure | null) {
        key = id;
        linked = id;
        if (refused) {
            errors.value = formErrors(firstErrors(refused.errors));
            if (Object.keys(errors.value).length === 0) failure.value = t('feed.offline.failed');
            return;
        }
        try {
            waiting.value = (await outbox.entries(OUTBOX_TYPE)).some((entry) => entry.id === id);
        } catch {
            waiting.value = false;
        }
    }

    // Saisie commencée avant la lecture du brouillon : elle l'emporte (le brouillon n'est pas réappliqué).
    let typed = false;
    let restoring = false;
    const schedule = () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => { timer = null; void saveDraft(); }, DRAFT_DELAY);
    };

    watch(form, () => {
        if (restoring) return;
        if (!ready.value) {
            typed = true;
            return;
        }
        schedule();
    }, { deep: true, flush: 'sync' });

    onMounted(async () => {
        try {
            const saved = await drafts.load<Partial<F> & DraftLink>(setup.draft);
            if (saved && typeof saved === 'object' && !typed) {
                const { outbox: entryId, failure: refused, image_alt: _legacyAlt, ...fields } = saved as Partial<F> & DraftLink & { image_alt?: string };
                restoring = true;
                Object.assign(form, setup.initial(), fields);
                restoring = false;
                restored.value = true;
                if (typeof entryId === 'string' && entryId !== '') {
                    await relink(entryId, refused ?? (setup.recovered?.id === entryId ? setup.recovered : null));
                }
            }
        } catch {
            // IndexedDB indisponible (navigation privée) : formulaire vide, sans brouillon.
        }
        ready.value = true;
        if (typed) schedule();
    });

    onBeforeUnmount(() => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
            void saveDraft();
        }
        images.value.forEach(revoke);
        controller?.abort();
    });

    /** Fin de l'envoi : brouillon effacé (publiée), ou gardé lié à l'entrée mise en file (`keepDraft`). */
    async function finish(keepDraft = false) {
        if (timer) clearTimeout(timer);
        timer = null;
        ready.value = false;
        typed = false;
        if (!keepDraft) await drafts.remove(setup.draft).catch(() => undefined);
        restoring = true;
        Object.assign(form, setup.initial());
        restoring = false;
        clearMedia();
        key = newKey();
        linked = null;
        waiting.value = false;
        setup.close();
    }

    async function queue() {
        if (timer) clearTimeout(timer);
        timer = null;
        // Brouillon lié avant la mise en file : un envoi immédiat (réseau revenu) trouve déjà la marque à effacer.
        linked = key;
        await saveDraft();
        await outbox.add({ type: OUTBOX_TYPE, url: POSTS_URL, method: 'POST', body: withGroup(outboxBody(setup.kind, form as F), setup.options.group), idempotencyKey: key });
        toast.show(t('feed.offline.queued'), 'secondary');
        await finish(true);
    }

    /** Validation, puis envoi (ou mise en file, sans média seulement). Rend vrai si la modale peut se fermer. */
    async function submit(): Promise<boolean> {
        if (busy.value) return false;
        failure.value = '';
        cancelled.value = false;
        errors.value = setup.validate(form as F, media.value);
        if (Object.keys(errors.value).length > 0) return false;
        busy.value = true;
        const needsNetwork = withMedia.value;
        try {
            if (!online()) {
                if (needsNetwork) {
                    failure.value = t('feed.media.needs_network');
                    return false;
                }
                await queue();
                return true;
            }
            if (waiting.value && linked) {
                // Envoyée d'ici : l'entrée en attente (même clé) ne repartira pas une seconde fois.
                await outbox.remove(linked).catch(() => undefined);
                waiting.value = false;
            }
            const withVideo = video.value !== null;
            controller = withVideo ? new AbortController() : null;
            progress.value = withVideo ? 0 : null;
            progressText.value = '';
            const { post, status } = await api.create(withGroup(requestBody(setup.kind, form as F, media.value), setup.options.group), key, {
                signal: controller?.signal,
                maxMb: Math.floor(limits().videoMaxKb / 1024),
                onProgress: withVideo ? (percent) => {
                    progress.value = percent;
                    const step = percent >= 100 ? 100 : Math.floor(percent / 25) * 25;
                    if (step > 0 && t('feed.video.progress', { percent: step }) !== progressText.value) progressText.value = t('feed.video.progress', { percent: step });
                } : undefined,
            });
            if (status === 202) {
                // Vidéo reçue : en relecture, invisible pour les autres ; un 202 sans identifiant est un rejeu en cours (ADR-32).
                if (!post?.id) {
                    failure.value = t('microcopy.error.generic');
                    return false;
                }
                toast.show(t('feed.video.submitted'), 'success');
                setup.onSubmitted?.(post);
                await finish();
                return true;
            }
            toast.show(setup.kind === 'poll' ? t('feed.toasts.poll_published') : t('feed.toasts.published'), 'success');
            setup.onPublished(post);
            await finish();
            return true;
        } catch (cause) {
            if (cause instanceof DOMException && cause.name === 'AbortError') {
                cancelled.value = true;
                return false;
            }
            if (cause instanceof ApiError && cause.status === 0) {
                if (needsNetwork) {
                    failure.value = t('feed.media.needs_network');
                    return false;
                }
                try {
                    await queue();
                    return true;
                } catch {
                    failure.value = t('microcopy.error.unreachable');
                    return false;
                }
            }
            if (cause instanceof ApiError && (cause.status === 422 || cause.status === 413)) {
                errors.value = formErrors(fieldErrors(cause));
                if (Object.keys(errors.value).length === 0) failure.value = cause.message;
            } else {
                failure.value = cause instanceof ApiError ? cause.message : t('microcopy.error.generic');
            }
            return false;
        } finally {
            busy.value = false;
            controller = null;
            progress.value = null;
        }
    }

    return {
        form, images, video, progress, progressText, announcement, cancelled, errors, failure, busy, restored, waiting, withMedia,
        addImages, removeImage, moveImage, setAlt, setVideo, cancel, submit,
    };
}
