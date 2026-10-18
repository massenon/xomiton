// État partagé des modales « Créer une publication » et « Créer un sondage » (lot fil-ecrans, P7-04) :
//  - brouillon IndexedDB (store `drafts`, clé par compte, expiré après `ttlDays`) : relu à l'ouverture, écrit à chaque
//    saisie (différé de 400 ms), effacé après l'envoi en ligne ; jamais l'image (fichier) ni un champ de
//    EXCLUDED_DRAFT_FIELDS (sanitizeDraft du store) ;
//  - mise en file : le brouillon reste, lié à l'entrée (`outbox` = sa clé) jusqu'à l'envoi confirmé ; au rejeu,
//    public/outbox-notices.js l'efface (envoyée) ou y note le refus (`failure` : statut et erreurs, 422 filtre de mots,
//    403 e-mail non confirmé…). Rouvert, il garde la même clé (rejeu sans doublon ; un refus libère la clé côté
//    serveur) : contenu et erreurs retrouvés, ou mention « en attente » tant que l'entrée attend (la nouvelle version
//    la remplace) ;
//  - image : type et poids contrôlés (5 Mo), aperçu par URL objet libérée au changement et à la fermeture ;
//  - envoi `POST /api/v1/posts` avec une Idempotency-Key gardée jusqu'au succès (ADR-32 : un double clic ou un rejeu
//    ne crée jamais deux publications) ; 422 → erreurs par champ, 403 / 429 → message dans la modale ;
//  - hors connexion (navigator.onLine faux, ou réseau coupé pendant l'envoi) : mise en file dans le store `outbox`
//    (shared/outbox.js, même clé, multipart rejouable avec l'image), puis envoi au retour du réseau.
import { onBeforeUnmount, onMounted, reactive, ref, watch, type UnwrapRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { useDraftsStore } from '@/stores/drafts';
import { useOutboxStore } from '@/stores/outbox';
import { POSTS_URL, feedApi } from './api';
import { formErrors, imageError, outboxBody, requestBody, withGroup, type FormErrors, type PollForm, type PostForm } from './composer';
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
    validate: (form: F, image: File | null) => FormErrors;
    /** Publication en ligne (201). */
    onPublished: (post: Post) => void;
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
    const image = ref<File | null>(null);
    const preview = ref<string | null>(null);
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

    const revoke = () => {
        if (preview.value && typeof URL !== 'undefined' && typeof URL.revokeObjectURL === 'function') URL.revokeObjectURL(preview.value);
        preview.value = null;
    };

    function setImage(file: File | null) {
        revoke();
        const { image: _image, ...rest } = errors.value;
        errors.value = rest;
        if (!file) {
            image.value = null;
            return;
        }
        const problem = imageError(file, setup.options.limits, t);
        if (problem) {
            image.value = null;
            errors.value = { ...errors.value, image: problem };
            return;
        }
        image.value = file;
        if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') preview.value = URL.createObjectURL(file);
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
                const { outbox: entryId, failure: refused, ...fields } = saved;
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
        revoke();
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
        setImage(null);
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
        await outbox.add({ type: OUTBOX_TYPE, url: POSTS_URL, method: 'POST', body: withGroup(outboxBody(setup.kind, form as F, image.value), setup.options.group), idempotencyKey: key });
        toast.show(t('feed.offline.queued'), 'secondary');
        await finish(true);
    }

    /** Validation, puis envoi (ou mise en file). Rend vrai si la modale peut se fermer. */
    async function submit(): Promise<boolean> {
        if (busy.value) return false;
        failure.value = '';
        errors.value = setup.validate(form as F, image.value);
        if (Object.keys(errors.value).length > 0) return false;
        busy.value = true;
        try {
            if (!online()) {
                await queue();
                return true;
            }
            if (waiting.value && linked) {
                // Envoyée d'ici : l'entrée en attente (même clé) ne repartira pas une seconde fois.
                await outbox.remove(linked).catch(() => undefined);
                waiting.value = false;
            }
            const post = await api.create(withGroup(requestBody(setup.kind, form as F, image.value), setup.options.group), key);
            toast.show(setup.kind === 'poll' ? t('feed.toasts.poll_published') : t('feed.toasts.published'), 'success');
            setup.onPublished(post);
            await finish();
            return true;
        } catch (cause) {
            if (cause instanceof ApiError && cause.status === 0) {
                try {
                    await queue();
                    return true;
                } catch {
                    failure.value = t('microcopy.error.unreachable');
                    return false;
                }
            }
            if (cause instanceof ApiError && cause.status === 422) {
                errors.value = formErrors(fieldErrors(cause));
                if (Object.keys(errors.value).length === 0) failure.value = cause.message;
            } else {
                failure.value = cause instanceof ApiError ? cause.message : t('microcopy.error.generic');
            }
            return false;
        } finally {
            busy.value = false;
        }
    }

    return { form, image, preview, errors, failure, busy, restored, waiting, setImage, submit };
}
