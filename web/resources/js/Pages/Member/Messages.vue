<script setup lang="ts">
// Page /messages (P8-01, prototype messaging.php ; Messages\MessagesPageController) : liste des conversations et
// conversation ouverte côte à côte (une seule à la fois sous 768 px, classe `show-pane` du prototype), dans la colonne
// col-lg-9 du gabarit membre (le sidenav du gabarit remplace la pleine largeur du prototype).
//
// Sondage court (D7, DECISIONS.md : pas de WebSocket sur cPanel) : conversation ouverte toutes les `poll_seconds`
// (4 s), liste toutes les `list_poll_seconds`, seulement onglet visible et réseau présent (messaging/poller.ts).
// Hors connexion : messages mis dans la boîte d'envoi commune (store `outbox`), horloge grisée jusqu'à la confirmation
// du serveur, rejeu sans doublon par `client_uuid` (messaging/store.ts). Lu : à l'ouverture et à l'arrivée d'un message
// quand l'onglet est visible ; le compteur « Messages » de l'en-tête suit la réponse du serveur.
// Liste : empreinte du passage précédent (`since`), repli à `list_poll_idle_seconds` (30 s) après 3 réponses inchangées.
// `?c=<ulid>` ouvre une conversation ; `?with=<id>` : le serveur sélectionne la conversation privée déjà ouverte, sinon
// la page **demande confirmation** (« Écrire à … ? ») avant de la créer : un lien seul ne contacte personne.
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import XmModal from '@/components/XmModal.vue';
import { useNotificationsStore } from '@/stores/notifications';
import { useOutboxStore } from '@/stores/outbox';
import { useSessionStore } from '@/stores/session';
import { useUiStore } from '@/stores/ui';
import ChatPane, { type PaneAction } from '@/messaging/components/ChatPane.vue';
import ConversationList from '@/messaging/components/ConversationList.vue';
import NewConversationModal from '@/messaging/components/NewConversationModal.vue';
import ReportModal from '@/messaging/components/ReportModal.vue';
import { createPoller, type Poller } from '@/messaging/poller';
import { useMessagingStore } from '@/messaging/store';
import { filterConversations, unreadConversations } from '@/messaging/thread';
import type { Conversation, MessagingConfig, Participant } from '@/messaging/types';

const props = withDefaults(defineProps<{
    title: string;
    conversations: Conversation[];
    conversationsMeta?: { current_page: number; last_page: number };
    selected?: string | null;
    with?: Participant | null;
    withUnavailable?: boolean;
    config: MessagingConfig;
}>(), { conversationsMeta: () => ({ current_page: 1, last_page: 1 }), selected: null, with: null, withUnavailable: false });

const { t } = useI18n();
const api = useApi();
const toast = useToast();
const store = useMessagingStore();
const outbox = useOutboxStore();
const session = useSessionStore();
const notifications = useNotificationsStore();
const ui = useUiStore();

store.init(props.conversations, props.config, props.conversationsMeta.last_page);
store.me = session.user ? { id: session.user.id, pseudo: session.user.pseudo, avatar_url: session.user.avatar_url } : null;

const paneOpen = ref(false);
const newOpen = ref(false);
const newBusy = ref(false);
const newError = ref<string | null>(null);
const reportOpen = ref(false);
const reportBusy = ref(false);
const reportError = ref<string | null>(null);
type ConfirmAction = PaneAction | 'delete' | 'start';
const confirm = ref<{ action: ConfirmAction; title: string; text: string; label?: string; messageId?: number; userId?: number } | null>(null);
const confirmBusy = ref(false);

const online = computed(() => ui.online !== false);
const active = computed(() => store.active);
const shown = computed(() => filterConversations(store.conversations, store.search));
const messages = computed(() => (store.activeId ? store.visibleMessages(store.activeId) : []));
const thread = computed(() => (store.activeId ? store.threads[store.activeId] : undefined));

let listPoller: Poller | null = null;
let threadPoller: Poller | null = null;
let searchTimer: ReturnType<typeof setTimeout> | null = null;
const offs: Array<() => void> = [];

const errorText = (error: unknown) => (error instanceof ApiError ? error.message : t('microcopy.error.generic'));

function syncHeader(count?: number | null) {
    notifications.messages = typeof count === 'number' ? count : unreadConversations(store.conversations);
}

function setUrl(id: string | null) {
    if (typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('c', id);
    else url.searchParams.delete('c');
    url.searchParams.delete('with');
    window.history.replaceState(window.history.state, '', `${url.pathname}${url.search}`);
}

async function markRead(id: string) {
    const conversation = store.conversations.find((c) => c.id === id);
    if (!conversation || (conversation.unread_count === 0 && notifications.messages === unreadConversations(store.conversations))) return;
    try {
        syncHeader(await store.markRead(api, id));
    } catch {
        // Réessayé au prochain passage.
    }
}

async function open(id: string) {
    store.activeId = id;
    paneOpen.value = true;
    setUrl(id);
    try {
        await store.loadThread(api, id);
    } catch {
        // Erreur affichée dans le fil (thread.error).
    }
    if (document.visibilityState !== 'hidden') markRead(id).catch(() => undefined);
    threadPoller?.start();
}

function back() {
    paneOpen.value = false;
}

async function pollThread() {
    const id = store.activeId;
    if (!id) return;
    const incoming = await store.pollThread(api, id);
    if (incoming.length > 0 && document.visibilityState !== 'hidden') await markRead(id);
}

async function pollList() {
    // Sous 768 px, la liste est cachée pendant la lecture : la conversation ouverte suffit.
    if (paneOpen.value && typeof window !== 'undefined' && window.matchMedia?.('(max-width: 767.98px)').matches) return;
    await store.refreshList(api);
    if (store.filter === 'all' && store.search.trim() === '' && store.lastPage <= 1) syncHeader();
}

async function send(body: string, file: File | null) {
    const id = store.activeId;
    if (!id) return;
    try {
        const message = await store.send(api, outbox, id, body, file);
        if (message.state === 'pending') toast.info(t('community.messages.toasts.queued'));
    } catch {
        // État « non envoyé » affiché sous la bulle.
    }
}

async function retry(uuid: string) {
    if (!store.activeId) return;
    await store.retry(api, outbox, store.activeId, uuid).catch(() => undefined);
}

async function discard(uuid: string) {
    if (!store.activeId) return;
    await store.discard(outbox, store.activeId, uuid).catch(() => undefined);
}

async function create(payload: Parameters<typeof store.create>[1]) {
    newBusy.value = true;
    newError.value = null;
    try {
        const conversation = await store.create(api, payload);
        newOpen.value = false;
        await open(conversation.id);
    } catch (error) {
        newError.value = errorText(error);
    } finally {
        newBusy.value = false;
    }
}

function onAction(action: PaneAction) {
    const c = active.value;
    if (!c) return;
    if (action === 'report') {
        reportError.value = null;
        reportOpen.value = true;
        return;
    }
    if (action === 'block') {
        confirm.value = { action, title: t('community.messages.confirm.block_title'), text: t('community.messages.confirm.block', { name: c.name }) };
        return;
    }
    if (action === 'leave') {
        confirm.value = c.kind === 'group'
            ? { action, title: t('community.messages.confirm.leave_title'), text: t('community.messages.confirm.leave') }
            : { action, title: t('community.messages.confirm.hide_title'), text: t('community.messages.confirm.hide') };
        return;
    }
    run(action).catch(() => undefined);
}

function askDelete(messageId: number) {
    confirm.value = { action: 'delete', title: t('community.messages.confirm.delete_title'), text: t('community.messages.confirm.delete'), messageId };
}

async function run(action: ConfirmAction, messageId?: number, userId?: number) {
    if (action === 'start' && userId) {
        await create({ kind: 'private', user_id: userId });
        if (newError.value) toast.error(newError.value);
        newError.value = null;
        return;
    }
    const c = active.value;
    if (!c) return;
    try {
        if (action === 'leave') {
            await store.leave(api, c.id);
            paneOpen.value = false;
            setUrl(null);
            toast.success(t(c.kind === 'group' ? 'community.messages.toasts.left' : 'community.messages.toasts.hidden'));
            syncHeader();
        } else if (action === 'delete' && messageId) {
            await store.deleteMessage(api, c.id, messageId);
            toast.success(t('community.messages.toasts.message_deleted'));
        } else if (action === 'block' || action === 'unblock') {
            await store.act(api, c.id, action);
            toast.success(t(`community.messages.toasts.${action === 'block' ? 'blocked' : 'unblocked'}`));
        } else if (action === 'unmute') {
            await store.act(api, c.id, 'unmute');
            toast.success(t('community.messages.toasts.unmuted'));
        } else if (action.startsWith('mute:')) {
            await store.act(api, c.id, 'mute', action.slice(5));
            toast.success(t('community.messages.toasts.muted'));
        }
    } catch (error) {
        toast.error(errorText(error));
    }
}

async function runConfirmed() {
    if (!confirm.value) return;
    confirmBusy.value = true;
    const { action, messageId, userId } = confirm.value;
    try {
        await run(action, messageId, userId);
    } finally {
        confirmBusy.value = false;
        confirm.value = null;
    }
}

async function report(reason: string, details: string | null) {
    const c = active.value;
    if (!c) return;
    reportBusy.value = true;
    reportError.value = null;
    try {
        await store.report(api, c.id, reason, details);
        reportOpen.value = false;
        toast.success(t('community.messages.toasts.reported'));
    } catch (error) {
        reportError.value = errorText(error);
    } finally {
        reportBusy.value = false;
    }
}

watch(() => [store.search, store.filter], () => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { store.refreshList(api).catch(() => undefined); }, 300);
});

watch(() => store.activeId, (id) => {
    if (!id) threadPoller?.stop();
});

onMounted(async () => {
    const seconds = (value: number | undefined, fallback: number) => Math.max(2, Number(value) || fallback) * 1000;
    threadPoller = createPoller({ interval: seconds(props.config?.poll_seconds, 4), run: pollThread });
    const listBase = seconds(props.config?.list_poll_seconds, 15);
    const listIdle = Math.max(listBase, seconds(props.config?.list_poll_idle_seconds, 30));
    listPoller = createPoller({ interval: () => (store.listUnchanged >= 3 ? listIdle : listBase), run: pollList });
    listPoller.start();
    syncHeader();

    const onFlushed = (event: Event) => store.applyFlush((event as CustomEvent).detail || {});
    window.addEventListener('xm:outbox:flushed', onFlushed);
    offs.push(() => window.removeEventListener('xm:outbox:flushed', onFlushed));
    store.restorePending(outbox, session.outboxAccount).catch(() => undefined);

    if (props.withUnavailable) {
        toast.error(t('community.messages.with_unavailable'));
        setUrl(null);
    } else if (props.with) {
        confirm.value = {
            action: 'start', userId: props.with.id, title: t('community.messages.with_confirm_title'),
            text: t('community.messages.with_confirm', { name: props.with.pseudo }), label: t('community.messages.new_dialog.start', { name: props.with.pseudo }),
        };
    } else if (props.selected) {
        if (!store.conversations.some((c) => c.id === props.selected)) {
            try {
                const { data } = await api.get<{ data: Conversation }>(`/api/v1/conversations/${encodeURIComponent(props.selected)}`, { silent: true });
                store.upsert(data.data);
            } catch {
                toast.error(t('community.messages.errors.not_found'));
                setUrl(null);
                return;
            }
        }
        await open(props.selected);
    } else if (typeof window !== 'undefined' && window.matchMedia?.('(min-width: 768px)').matches && store.conversations[0]) {
        // Grand écran : la première conversation s'ouvre comme dans le prototype.
        await open(store.conversations[0].id);
    }
});

onBeforeUnmount(() => {
    listPoller?.stop();
    threadPoller?.stop();
    if (searchTimer) clearTimeout(searchTimer);
    offs.splice(0).forEach((off) => off());
});

</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9" data-xm-messages-page>
        <div :class="['card xm-chat d-flex flex-row overflow-hidden', { 'show-pane': paneOpen }]">
            <ConversationList
                v-model:search="store.search"
                v-model:filter="store.filter"
                :conversations="shown"
                :active-id="store.activeId"
                :error="store.listError"
                @select="open"
                @create="newError = null; newOpen = true"
            />
            <ChatPane
                :conversation="active"
                :messages="messages"
                :loading="thread?.loading ?? false"
                :has-older="Boolean(thread?.nextCursor)"
                :error="thread?.error ?? null"
                :max-length="config.body_max_length"
                :accept="config.attachment_accept"
                :mute-durations="config.mute_durations"
                :online="online"
                @back="back"
                @send="send"
                @older="store.activeId && store.loadOlder(api, store.activeId).catch(() => undefined)"
                @retry="retry"
                @discard="discard"
                @remove="askDelete"
                @action="onAction"
            />
        </div>
    </div>
    <NewConversationModal v-model:open="newOpen" :api="api" :search-min="config.recipient_search_min" :title-min="config.group_title_min"
        :busy="newBusy" :error="newError" @create="create" />
    <ReportModal v-model:open="reportOpen" :reasons="config.report_reasons" :busy="reportBusy" :error="reportError" @submit="report" />
    <XmModal id="modalChatConfirm" :open="confirm !== null" :type="confirm?.action === 'delete' ? 'delete' : 'confirm'" :title="confirm?.title ?? ''"
        :action="confirm?.action === 'delete' ? t('community.messages.actions.delete_message') : (confirm?.label ?? confirm?.title ?? '')" :busy="confirmBusy"
        @update:open="(value) => { if (!value) { if (confirm?.action === 'start') setUrl(store.activeId); confirm = null; } }" @action="runConfirmed">
        <p class="mb-0">{{ confirm?.text }}</p>
    </XmModal>
</template>
