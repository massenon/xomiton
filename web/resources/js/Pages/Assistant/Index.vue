<script setup lang="ts">
// Page `/assistant` (lot assistant, P8-11 ; prototype assistant.php ; Q7, Q41, Q55), page Inertia `Assistant/Index` dans
// le gabarit membre (colonnes du fil : col-md-8 col-lg-6 + colonne droite du prototype « En danger ? » · « Parler à un
// humain » · mention). Conversation avec la base de connaissances par `POST /api/v1/assistant/question` (AskAssistant :
// fournisseur `kb` par défaut, Q7 non tranchée).
// Garde-fous de crise **avant tout appel réseau** (assistant/crisis.ts, mêmes mots-clés que le serveur) : un message de
// crise affiche aussitôt les numéros d'urgence (même source que /urgences), le SOS et « Parler à un humain », même hors
// connexion et même pendant une réponse en cours, sans rien envoyer au fournisseur ; seul un échange que la personne a
// choisi d'enregistrer est envoyé (en file derrière les requêtes en cours) pour être gardé et marqué, un échec est dit.
// Historique dans l'onglet seulement (sessionStorage par compte, assistant/history.ts), rien en mode discret ;
// « Garder cette conversation » (désactivé par défaut) l'enregistre chiffrée côté serveur, « Effacer la conversation »
// l'efface aussi là-bas (écran tout de suite, serveur après les requêtes en cours). « Parler à un humain » : modale
// chargée à la demande (EscalationModal, demande « besoin »).
// Props : AssistantPageController (title, chips, max_length, crisis, offline, escalation, links).
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { useSessionStore } from '@/stores/session';
import { useUiStore } from '@/stores/ui';
import { lazyComponent } from '@/feed/lazy';
import { detectCrisis } from '@/assistant/crisis';
import { forgetHistory, forgetOtherHistories, historyKey, loadHistory, saveHistory, type ChatLink, type ChatMessage } from '@/assistant/history';
import { QUESTION_ENDPOINT, conversationEndpoint, type AnswerData, type AssistantLinks, type CrisisProps, type EscalationProps, type EscalationResult, type OfflineProps } from '@/assistant/types';
import XmButton from '@/components/XmButton.vue';
import XmField from '@/components/XmField.vue';

// Modale chargée au premier clic (ou au survol), hors du morceau partagé avec app.ts (feed/lazy.ts).
const modal = lazyComponent(() => import('@/components/assistant/EscalationModal.vue'));
const EscalationModal = modal.component;

const props = withDefaults(defineProps<{
    title: string;
    chips?: string[];
    max_length?: number;
    crisis: CrisisProps;
    offline: OfflineProps;
    escalation: EscalationProps;
    links: AssistantLinks;
}>(), { chips: () => [], max_length: 500 });

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const session = useSessionStore();
const ui = useUiStore();

const discreet = computed(() => session.user?.profile?.discreet_mode === true);
const storageKey = computed(() => historyKey(session.user?.id));

const messages = ref<ChatMessage[]>([]);
const input = ref('');
const busy = ref(false);
const persist = ref(false);
const conversationId = ref<number | null>(null);
const escalating = ref(false);
const box = ref<HTMLElement | null>(null);
const field = ref<HTMLInputElement | null>(null);
let seq = 0;

const welcome = (): ChatMessage => ({
    id: 0,
    who: 'bot',
    text: t('assistant.page.welcome'),
    links: [{ label: t('assistant.links.sos'), url: props.links.sos, variant: 'danger' }],
});

const visible = computed<ChatMessage[]>(() => (messages.value.length ? messages.value : [welcome()]));
const inputIsCrisis = computed(() => detectCrisis(input.value, props.crisis.keywords) !== null);

function persistLocally() {
    if (discreet.value) return;
    saveHistory(storageKey.value, { messages: messages.value, conversationId: conversationId.value, persist: persist.value });
}

function push(message: Omit<ChatMessage, 'id'>): ChatMessage {
    seq += 1;
    const entry = { id: seq, ...message } as ChatMessage;
    messages.value = [...messages.value, entry];
    persistLocally();
    scrollToEnd();
    return entry;
}

function scrollToEnd() {
    nextTick(() => {
        if (box.value) box.value.scrollTop = box.value.scrollHeight;
    });
}

function restoreFocus(origin: HTMLElement | null) {
    nextTick(() => {
        const target = origin && origin.isConnected ? origin : field.value;
        target?.focus({ preventScroll: true });
    });
}

const crisisMessage = (): Omit<ChatMessage, 'id'> => ({ who: 'bot', kind: 'crisis', text: props.crisis.text, links: props.crisis.links, escalation: true });

const offlineMessage = (): Omit<ChatMessage, 'id'> => {
    const links: ChatLink[] = [];
    if (props.offline.call) links.push({ label: props.offline.call.label, url: props.offline.call.url, variant: 'primary-soft' });
    links.push({ label: t('components.assistant.offline_link'), url: props.links.urgences, variant: 'primary-soft' });
    return { who: 'bot', text: props.offline.text, links, error: true };
};

const isOffline = () => !ui.online || (typeof navigator !== 'undefined' && navigator.onLine === false);

// File des requêtes : une question part quand la précédente a répondu, elle porte donc le `conversation_id` créé par
// celle-ci (même l'échange de crise envoyé en arrière-plan : une seule conversation enregistrée) ; « Effacer » passe
// aussi par la file et efface la conversation qu'une réponse tardive aurait créée.
let queue: Promise<unknown> = Promise.resolve();
function enqueue<T>(task: () => Promise<T>): Promise<T> {
    const run = queue.then(task);
    queue = run.catch(() => undefined);
    return run;
}
// Génération affichée : « Effacer » l'incrémente, une réponse arrivée ensuite n'ajoute plus de bulle.
let generation = 0;

function request(question: string): Promise<AnswerData> {
    const keep = persist.value;
    return enqueue(async () => {
        const body: Record<string, unknown> = { question };
        if (keep) {
            body.persist = true;
            if (conversationId.value !== null) body.conversation_id = conversationId.value;
        }
        try {
            const { data } = await api.post<{ data: AnswerData }>(QUESTION_ENDPOINT, body, { silent: true });
            // Gardé même après « Effacer » : l'effacement, en file derrière cette requête, efface aussi cette conversation.
            if (typeof data.data.conversation_id === 'number') conversationId.value = data.data.conversation_id;
            return data.data;
        } catch (cause) {
            // Conversation enregistrée effacée ailleurs : la suite repart d'une nouvelle conversation.
            if (cause instanceof ApiError && cause.status === 404 && body.conversation_id !== undefined) conversationId.value = null;
            throw cause;
        }
    });
}

const recordFailedMessage = (): Omit<ChatMessage, 'id'> => ({ who: 'bot', text: t('assistant.page.record_failed'), error: true });

/** Échange de crise enregistré à la demande de la personne : envoyé en file, la réponse affichée reste locale. */
function recordCrisis(question: string) {
    if (!persist.value) return;
    if (isOffline()) {
        push(recordFailedMessage());
        return;
    }
    const gen = generation;
    request(question)
        .then(() => {
            if (gen === generation) persistLocally();
        })
        .catch(() => {
            if (gen === generation) push(recordFailedMessage());
        });
}

async function exchange(question: string, origin: HTMLElement | null) {
    if (isOffline()) {
        push(offlineMessage());
        restoreFocus(origin);
        return;
    }
    const gen = generation;
    busy.value = true;
    scrollToEnd();
    try {
        const data = await request(question);
        if (gen !== generation) return;
        push({
            who: 'bot',
            kind: data.kind,
            text: data.text,
            links: Array.isArray(data.links) ? data.links : [],
            suggestions: data.kind === 'fallback' && Array.isArray(data.suggestions) ? data.suggestions : [],
            escalation: data.kind === 'crisis' || data.escalation === true,
        });
    } catch (cause) {
        if (gen !== generation) return;
        if (cause instanceof ApiError && cause.status === 0) push(offlineMessage());
        else if (cause instanceof ApiError && cause.status === 422) push({ who: 'bot', text: cause.errors.question?.[0] || cause.message || t('components.assistant.error'), error: true });
        else if (cause instanceof ApiError && cause.status === 429) push({ who: 'bot', text: t('components.assistant.rate_limited', { seconds: cause.retryAfter ?? 60 }), error: true });
        else push({ who: 'bot', text: t('components.assistant.error'), error: true, retry: question });
    } finally {
        busy.value = false;
        restoreFocus(origin);
    }
}

async function ask(text: string, origin: HTMLElement | null = null) {
    const question = String(text ?? '').trim().slice(0, props.max_length);
    const crisis = question !== '' && detectCrisis(question, props.crisis.keywords) !== null;
    // Un message de crise passe même pendant une réponse en cours : sa réponse ne dépend pas du réseau.
    if (question === '' || (busy.value && !crisis)) return;
    push({ who: 'me', text: question });
    input.value = '';

    // Garde-fou de crise : numéros, SOS et escalade tout de suite, sans fournisseur ni réseau.
    if (crisis) {
        push(crisisMessage());
        recordCrisis(question);
        restoreFocus(origin);
        return;
    }

    await exchange(question, origin);
}

async function retry(message: ChatMessage) {
    if (busy.value || !message.retry) return;
    const question = message.retry;
    messages.value = messages.value.filter((entry) => entry.id !== message.id);
    await exchange(question, null);
}

function onSubmit() {
    ask(input.value, null);
}

function onChip(event: MouseEvent, chip: string) {
    ask(chip, event.currentTarget as HTMLElement);
}

/** Onglet aligné sur la conversation affichée : rien de gardé quand elle est vide et sans conversation enregistrée. */
function syncLocal() {
    if (messages.value.length === 0 && conversationId.value === null) forgetHistory(storageKey.value);
    else persistLocally();
}

/**
 * « Effacer la conversation » : l'écran et l'onglet tout de suite (quelqu'un peut regarder), puis la conversation
 * enregistrée côté serveur, une fois les requêtes en cours terminées (file) ; si l'effacement échoue, son numéro reste
 * gardé pour un nouvel essai et on le dit.
 */
async function clear() {
    generation += 1;
    messages.value = [];
    syncLocal();
    restoreFocus(null);
    try {
        await enqueue(async () => {
            const id = conversationId.value;
            if (id === null) return;
            try {
                await api.delete(conversationEndpoint(id), { silent: true });
            } catch (cause) {
                // Déjà effacée (404) : rien à garder.
                if (!(cause instanceof ApiError && cause.status === 404)) throw cause;
            }
            if (conversationId.value === id) conversationId.value = null;
        });
    } catch {
        syncLocal();
        toast.error(t('assistant.page.clear_failed'));
        return;
    }
    syncLocal();
    toast.show(t('assistant.page.cleared'), 'info');
}

async function openEscalation() {
    escalating.value = true;
    if (!(await modal.load())) {
        escalating.value = false;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

function onSent(result: EscalationResult) {
    const code = result.tracking_code_display || result.tracking_code;
    // Annoncée une seule fois : la bulle du journal (role=log, aria-live), sans toast qui la répéterait.
    push({ who: 'bot', kind: 'escalation', text: t('assistant.escalation.sent', { code }) });
}

watch(persist, () => persistLocally());

onMounted(() => {
    if (discreet.value) {
        // Mode discret : rien dans ce navigateur, même ce qu'une visite précédente y aurait laissé.
        forgetHistory();
        return;
    }
    // Conversation d'un autre compte laissée dans l'onglet (session expirée, déconnexion hors de l'espace Vue) : effacée.
    forgetOtherHistories(storageKey.value);
    const stored = loadHistory(storageKey.value);
    if (stored) {
        messages.value = stored.messages;
        conversationId.value = stored.conversationId;
        persist.value = stored.persist;
        seq = stored.messages.reduce((max, entry) => Math.max(max, entry.id), 0);
        scrollToEnd();
    }
});

const variantClass = (link: ChatLink) => (link.variant === 'danger' ? 'btn-danger' : 'btn-primary-soft');
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-3">
        <div class="card xm-assistant-chat">
            <div class="card-header d-flex align-items-center gap-3">
                <span class="icon-lg rounded-circle xm-assistant-avatar flex-shrink-0" aria-hidden="true"><i class="bi bi-robot"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <h1 class="h5 mb-0">{{ t('assistant.page.title') }}</h1>
                    <small class="text-secondary">{{ t('assistant.page.subtitle') }} · <span class="badge" :class="ui.online ? 'bg-success' : 'bg-secondary'" data-xm-online-state>{{ ui.online ? t('assistant.page.online') : t('assistant.page.offline') }}</span></small>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light xm-btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" :aria-label="t('assistant.page.options')" :title="t('assistant.page.options')"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" type="button" data-xm-clear @click="clear"><i class="bi bi-trash me-2" aria-hidden="true"></i>{{ t('assistant.page.clear') }}</button></li>
                        <li><button class="dropdown-item" type="button" aria-haspopup="dialog" @click="openEscalation" @pointerenter="modal.load()"><i class="bi bi-person-lines-fill me-2" aria-hidden="true"></i>{{ t('assistant.page.human_title') }}</button></li>
                    </ul>
                </div>
            </div>

            <div ref="box" class="card-body vstack gap-3 xm-assistant-log" role="log" aria-live="polite" aria-relevant="additions" :aria-label="t('assistant.page.log_label')" tabindex="0" data-xm-chat>
                <div v-for="message in visible" :key="message.id" class="d-flex" :class="message.who === 'me' ? 'justify-content-end' : 'justify-content-start'" :data-who="message.who" :data-kind="message.kind || undefined">
                    <div class="xm-assistant-msg" :class="message.who">
                        <div class="bubble rounded-3 px-3 py-2"><span class="visually-hidden">{{ `${message.who === 'me' ? t('assistant.page.me') : t('assistant.page.bot')} ` }}</span>{{ message.text }}</div>
                        <div v-if="message.links?.length || message.escalation || message.retry" class="mt-1 d-flex flex-wrap gap-1">
                            <a v-for="link in message.links" :key="link.url + link.label" class="btn btn-sm" :class="variantClass(link)" :href="link.url">{{ link.label }}</a>
                            <XmButton v-if="message.escalation" variant="primary" size="sm" icon="person-heart" aria-haspopup="dialog" @click="openEscalation" @pointerenter="modal.load()">{{ t('assistant.links.human') }}</XmButton>
                            <XmButton v-if="message.retry" variant="light" size="sm" icon="arrow-clockwise" :disabled="busy" @click="retry(message)">{{ t('assistant.page.retry') }}</XmButton>
                        </div>
                        <div v-if="message.suggestions?.length" class="mt-1 d-flex flex-wrap gap-1">
                            <button v-for="suggestion in message.suggestions" :key="suggestion" type="button" class="btn btn-sm btn-primary-soft text-start" :disabled="busy" @click="onChip($event, suggestion)">{{ suggestion }}</button>
                        </div>
                    </div>
                </div>
                <div v-if="busy" class="xm-typing px-3 py-2 rounded-3 bg-body-tertiary align-self-start" data-xm-typing><span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span><span class="visually-hidden">{{ t('assistant.page.typing') }}</span></div>
            </div>

            <div class="px-3 pb-2 d-flex gap-2 overflow-auto xm-assistant-chips" role="group" :aria-label="t('assistant.page.chips_label')">
                <button v-for="chip in chips" :key="chip" type="button" class="btn btn-sm btn-primary-soft text-nowrap" :disabled="busy" @click="onChip($event, chip)">{{ chip }}</button>
            </div>

            <div class="card-footer">
                <form class="d-flex gap-2" autocomplete="off" @submit.prevent="onSubmit">
                    <label class="visually-hidden" for="assistant-input">{{ t('assistant.page.input_label') }}</label>
                    <input id="assistant-input" ref="field" v-model="input" class="form-control" name="question" :placeholder="t('assistant.page.placeholder')" :maxlength="max_length" enterkeyhint="send">
                    <XmButton type="submit" variant="primary" icon="send-fill" :label="t('assistant.page.send')" :disabled="busy && !inputIsCrisis" />
                </form>
                <div class="mt-2">
                    <XmField v-model="persist" name="persist" type="switch" :label="t('assistant.page.persist')" :hint="t('assistant.page.persist_help')" />
                </div>
                <p class="small text-secondary mb-0 mt-1" data-xm-history>
                    <i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ discreet ? t('assistant.page.history_discreet') : (conversationId !== null ? t('assistant.page.history_saved') : t('assistant.page.history_tab')) }}
                </p>
            </div>
        </div>
    </div>

    <aside class="col-lg-3 vstack gap-3" :aria-label="t('menus.a11y.member_aside')">
        <div class="card card-body border-danger">
            <h2 class="h6 fw-bold text-danger mb-1"><i class="bi bi-exclamation-triangle-fill me-1" aria-hidden="true"></i>{{ t('assistant.page.danger_title') }}</h2>
            <p class="small mb-2">{{ t('assistant.page.danger_text') }}</p>
            <a class="btn btn-danger btn-sm w-100" :href="links.sos">{{ t('buttons.help.sos_gps') }}</a>
        </div>
        <div class="card card-body">
            <h2 class="h6 fw-bold mb-1"><i class="bi bi-person-heart me-1 text-primary" aria-hidden="true"></i>{{ t('assistant.page.human_title') }}</h2>
            <p class="small mb-2">{{ t('assistant.page.human_text') }}</p>
            <XmButton class="w-100 mb-2" variant="primary" size="sm" aria-haspopup="dialog" @click="openEscalation" @pointerenter="modal.load()">{{ t('assistant.page.human_cta') }}</XmButton>
            <a class="btn btn-primary-soft btn-sm w-100" :href="links.annuaire">{{ t('assistant.page.directory_cta') }}</a>
        </div>
        <p class="small text-secondary"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ t('assistant.page.disclaimer') }}</p>
    </aside>

    <component
        :is="EscalationModal"
        v-if="escalating && EscalationModal"
        :channels="escalation.channels"
        :message-max="escalation.message_max"
        :conversation-id="conversationId"
        :offline-text="offline.escalation"
        :call="offline.call"
        :directory-url="links.annuaire"
        @sent="onSent"
        @hidden="escalating = false"
    />
</template>
