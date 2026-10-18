<script setup lang="ts">
// Conversation ouverte (P8-01, prototype messaging.php l.72-82) : en-tête (retour sous 768 px, avatar, nom, sous-titre,
// « Signaler », menu : sourdine, bloquer / débloquer, quitter ou masquer), messages groupés par jour dans une zone
// défilante focusable (bulles « moi » / « elle·lui », auteur·rice dans un groupe, « Vu », message supprimé, pièce
// jointe) ; seule une région live dédiée annonce les messages **entrants** (jamais l'historique au chargement) ;
// messages en attente avec horloge grisée jusqu'à confirmation du serveur, « non envoyé » avec Réessayer (masqué si la
// pièce jointe n'a pas survécu au rechargement) / Retirer, et zone d'écriture (émojis du prototype, pièce jointe par un
// vrai bouton — clavier, WCAG 2.1.1 —, 1000 caractères, Entrée envoie, Maj+Entrée = ligne).
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import XmAvatar from '@/components/XmAvatar.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import { dayLabel, groupByDay, timeLabel } from '../thread';
import type { ChatMessage, Conversation } from '../types';

export type PaneAction = 'report' | 'block' | 'unblock' | 'unmute' | 'leave' | `mute:${string}`;

const EMOJIS = ['😊', '💜', '🌈', '🙏', '👍', '😢', '🤗', '💪', '🔒', '✨'];

const props = withDefaults(defineProps<{
    conversation: Conversation | null;
    messages: ChatMessage[];
    loading?: boolean;
    hasOlder?: boolean;
    error?: string | null;
    maxLength?: number;
    accept?: string;
    muteDurations?: string[];
    online?: boolean;
}>(), { loading: false, hasOlder: false, error: null, maxLength: 1000, accept: 'image/jpeg,image/png,image/webp,application/pdf', muteDurations: () => ['8h', '1w', 'always'], online: true });

const emit = defineEmits<{
    back: [];
    send: [body: string, file: File | null];
    older: [];
    retry: [uuid: string];
    discard: [uuid: string];
    remove: [id: number];
    action: [action: PaneAction];
}>();

const { t } = useI18n();
const draft = ref('');
const file = ref<File | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const input = ref<HTMLTextAreaElement | null>(null);
const scroller = ref<HTMLElement | null>(null);
const announcement = ref('');

const groups = computed(() => groupByDay(props.messages));
const isGroup = computed(() => props.conversation?.kind === 'group');
const subtitle = computed(() => {
    const c = props.conversation;
    if (!c) return '';
    if (c.kind === 'group') return t('community.messages.participants', { count: c.participants_count }, c.participants_count);
    return c.muted ? t('community.messages.muted_label') : '';
});
const canSend = computed(() => Boolean(props.conversation?.can_send));
const remaining = computed(() => props.maxLength - draft.value.length);

function submit() {
    const body = draft.value.trim();
    if (!canSend.value || (body === '' && !file.value)) return;
    emit('send', body, file.value);
    draft.value = '';
    clearFile();
    nextTick(() => input.value?.focus());
}

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        submit();
    }
}

function addEmoji(emoji: string) {
    draft.value = `${draft.value}${emoji}`.slice(0, props.maxLength);
    input.value?.focus();
}

function openFilePicker() {
    fileInput.value?.click();
}

function pickFile(event: Event) {
    const target = event.target as HTMLInputElement;
    file.value = target.files?.[0] ?? null;
}

function clearFile() {
    file.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

const atBottom = () => {
    const el = scroller.value;
    return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80;
};

/** Reste en bas à l'arrivée d'un message si on y était déjà (sinon on ne déplace pas la lecture en cours). */
watch(() => props.messages.length, async (now, before) => {
    const stick = before === 0 || atBottom();
    // Région live : un message reçu pendant la lecture (pas le chargement du fil, pas ses propres envois).
    const last = props.messages[now - 1];
    if (before > 0 && now > before && last && !last.mine && last.id !== null) {
        announcement.value = t('community.messages.incoming', { name: last.author?.pseudo ?? props.conversation?.name ?? '' });
    }
    await nextTick();
    if (stick && scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
});
watch(() => props.conversation?.id, async () => {
    announcement.value = '';
    draft.value = '';
    clearFile();
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
});

const key = (m: ChatMessage) => (m.id !== null ? `m${m.id}` : `u${m.client_uuid}`);
const bubbleClass = (m: ChatMessage) => ['xm-bubble', m.mine ? 'me' : 'them', { 'is-pending': m.state === 'pending', 'is-failed': m.state === 'failed' }];
</script>

<template>
    <div class="xm-chat-pane d-flex flex-column flex-grow-1 overflow-hidden">
        <template v-if="conversation">
            <div class="d-flex align-items-center gap-2 p-3 border-bottom">
                <button class="btn btn-light btn-sm d-md-none xm-btn-icon" type="button" :aria-label="t('community.messages.back')" @click="emit('back')">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                </button>
                <XmAvatar v-if="!isGroup" :name="conversation.name" :src="conversation.avatar_url" size="sm" />
                <span v-else class="xm-chat-group-avatar rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">
                    <i class="bi bi-people-fill"></i>
                </span>
                <div class="flex-grow-1 overflow-hidden">
                    <h2 class="h6 fw-bold text-truncate mb-0" data-xm-chat-name>{{ conversation.name }}</h2>
                    <div v-if="subtitle" class="small text-secondary text-truncate">{{ subtitle }}</div>
                </div>
                <button class="btn btn-sm btn-light text-danger" type="button" @click="emit('action', 'report')">
                    <i class="bi bi-flag me-1" aria-hidden="true"></i><span class="d-none d-sm-inline">{{ t('community.messages.actions.report') }}</span>
                    <span class="visually-hidden d-sm-none">{{ t('community.messages.actions.report') }}</span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light xm-btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" :aria-label="t('community.messages.menu')">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end small">
                        <template v-if="conversation.muted">
                            <li><button class="dropdown-item" type="button" @click="emit('action', 'unmute')"><i class="bi bi-bell me-2" aria-hidden="true"></i>{{ t('community.messages.actions.unmute') }}</button></li>
                        </template>
                        <template v-else>
                            <li v-for="duration in muteDurations" :key="duration">
                                <button class="dropdown-item" type="button" @click="emit('action', `mute:${duration}`)"><i class="bi bi-bell-slash me-2" aria-hidden="true"></i>{{ t(`community.messages.actions.mute_${duration}`) }}</button>
                            </li>
                        </template>
                        <li v-if="!isGroup && conversation.blocked_by_me"><button class="dropdown-item" type="button" @click="emit('action', 'unblock')"><i class="bi bi-person-check me-2" aria-hidden="true"></i>{{ t('community.messages.actions.unblock') }}</button></li>
                        <li v-else-if="!isGroup"><button class="dropdown-item text-danger" type="button" @click="emit('action', 'block')"><i class="bi bi-person-x me-2" aria-hidden="true"></i>{{ t('community.messages.actions.block') }}</button></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><button class="dropdown-item" type="button" @click="emit('action', 'leave')"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>{{ isGroup ? t('community.messages.actions.leave') : t('community.messages.actions.hide') }}</button></li>
                    </ul>
                </div>
            </div>

            <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-xm-incoming>{{ announcement }}</div>
            <div ref="scroller" class="xm-chat-msgs flex-grow-1 p-3 vstack gap-2" role="region" tabindex="0" :aria-label="t('community.messages.thread_label')"
                :aria-busy="loading ? 'true' : 'false'" data-xm-messages>
                <div v-if="hasOlder" class="text-center">
                    <button type="button" class="btn btn-link btn-sm" :disabled="loading" @click="emit('older')">{{ t('community.messages.load_older') }}</button>
                </div>
                <p v-if="loading && messages.length === 0" class="text-center text-secondary my-auto">{{ t('community.messages.loading') }}</p>
                <p v-else-if="error && messages.length === 0" class="text-center text-danger my-auto" role="status">{{ t('community.messages.errors.load_failed') }}</p>
                <p v-else-if="messages.length === 0" class="text-center text-secondary my-auto">{{ t('community.messages.empty_conversation') }}</p>
                <template v-for="group in groups" :key="group.key">
                    <div class="text-center text-secondary xm-chat-day my-1">{{ dayLabel(group.iso, { today: t('community.messages.today'), yesterday: t('community.messages.yesterday') }) }}</div>
                    <div v-for="m in group.messages" :key="key(m)" :class="['d-flex', { 'justify-content-end': m.mine }]" :data-xm-message="m.id ?? m.client_uuid">
                        <div :class="bubbleClass(m)">
                            <small v-if="isGroup && !m.mine && m.author" class="d-block fw-bold">{{ m.author.pseudo }}</small>
                            <span v-if="m.deleted" class="fst-italic">{{ t('community.messages.deleted') }}</span>
                            <template v-else>
                                <a v-if="m.attachment?.kind === 'image'" :href="m.attachment.url" target="_blank" rel="noopener" class="d-block mb-1">
                                    <img class="xm-bubble-image" :src="m.attachment.url" :alt="t('community.messages.attachment_image_alt')" loading="lazy">
                                </a>
                                <a v-else-if="m.attachment?.kind === 'document'" :href="m.attachment.url" target="_blank" rel="noopener" class="d-block mb-1">
                                    <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>{{ t('community.messages.attachment_open') }}
                                </a>
                                <span v-else-if="m.file_name" class="d-block mb-1"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>{{ m.file_name }}</span>
                                <span v-if="m.body" class="xm-bubble-text">{{ m.body }}</span>
                            </template>
                            <small class="d-flex justify-content-end align-items-center gap-1 mt-1">
                                <template v-if="m.state === 'pending'">
                                    <i class="bi bi-clock xm-bubble-clock" aria-hidden="true"></i><span>{{ t('community.messages.pending') }}</span>
                                </template>
                                <template v-else-if="m.state === 'failed'">
                                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i><span>{{ m.error || t('community.messages.errors.send_failed') }}</span>
                                </template>
                                <template v-else>
                                    <span>{{ timeLabel(m.created_at) }}</span>
                                    <span v-if="m.mine && m.status === 'seen'"><i class="bi bi-check2-all" aria-hidden="true"></i> {{ t('community.messages.seen') }}</span>
                                    <span v-else-if="m.mine && m.status === 'sent'" :title="t('community.messages.sent')"><i class="bi bi-check2" aria-hidden="true"></i><span class="visually-hidden">{{ t('community.messages.sent') }}</span></span>
                                </template>
                            </small>
                            <div v-if="m.state === 'failed'" class="d-flex flex-wrap gap-2 justify-content-end mt-1">
                                <button v-if="m.retryable !== false" type="button" class="btn btn-link btn-sm p-0" @click="emit('retry', m.client_uuid as string)">{{ t('community.messages.retry') }}</button>
                                <span v-else class="small">{{ t('community.messages.retry_unavailable') }}</span>
                                <button type="button" class="btn btn-link btn-sm p-0" @click="emit('discard', m.client_uuid as string)">{{ t('community.messages.remove_pending') }}</button>
                            </div>
                            <div v-else-if="m.mine && m.id !== null && !m.deleted" class="text-end">
                                <button type="button" class="btn btn-link btn-sm p-0 text-reset xm-bubble-action" @click="emit('remove', m.id as number)">{{ t('community.messages.actions.delete_message') }}</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div v-if="!online" class="px-3 pt-2 small text-secondary" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i>{{ t('community.messages.offline_notice') }}</div>
            <div v-if="!canSend" class="p-3 border-top small text-secondary" role="status">
                {{ conversation.blocked_by_me ? t('community.messages.blocked_notice') : t('community.messages.blocked_by_notice') }}
                <button v-if="conversation.blocked_by_me" type="button" class="btn btn-link btn-sm p-0 ms-1" @click="emit('action', 'unblock')">{{ t('community.messages.actions.unblock') }}</button>
            </div>
            <form v-else class="p-3 border-top xm-chat-compose" autocomplete="off" @submit.prevent="submit">
                <div v-if="file" class="small mb-2 d-flex align-items-center gap-2">
                    <i class="bi bi-paperclip" aria-hidden="true"></i><span class="text-truncate">{{ file.name }}</span>
                    <button type="button" class="btn btn-link btn-sm p-0" @click="clearFile">{{ t('community.messages.attachment_remove') }}</button>
                </div>
                <div class="d-flex align-items-end gap-2">
                    <div class="dropup">
                        <button class="btn btn-light xm-btn-icon" type="button" data-bs-toggle="dropdown" :aria-label="t('community.messages.emoji')" aria-expanded="false"><i class="bi bi-emoji-smile" aria-hidden="true"></i></button>
                        <div class="dropdown-menu p-2">
                            <div class="d-flex flex-wrap gap-1">
                                <button v-for="emoji in EMOJIS" :key="emoji" type="button" class="btn btn-light btn-sm fs-5" :aria-label="emoji" @click="addEmoji(emoji)">{{ emoji }}</button>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-light xm-btn-icon" type="button" :title="t('community.messages.attach')" data-xm-attach @click="openFilePicker">
                        <i class="bi bi-paperclip" aria-hidden="true"></i><span class="visually-hidden">{{ t('community.messages.attach') }} ({{ t('community.messages.attachment_hint') }})</span>
                    </button>
                    <input id="chatFile" ref="fileInput" type="file" class="d-none" tabindex="-1" aria-hidden="true" :accept="accept" @change="pickFile">
                    <label class="visually-hidden" for="chatInput">{{ t('community.messages.compose_label') }}</label>
                    <textarea id="chatInput" ref="input" v-model="draft" class="form-control" rows="1" :maxlength="maxLength" :placeholder="t('community.messages.compose')"
                        :aria-describedby="remaining < 50 ? 'chatInputCount' : undefined" @keydown="onKeydown"></textarea>
                    <button class="btn btn-primary xm-btn-icon" type="submit" :aria-label="t('community.messages.send')" :disabled="draft.trim() === '' && !file">
                        <i class="bi bi-send-fill" aria-hidden="true"></i>
                    </button>
                </div>
                <div v-if="remaining < 50" id="chatInputCount" class="small text-end text-danger mt-1" aria-live="polite">{{ remaining }}</div>
            </form>
        </template>
        <XmEmptyState v-else class="my-auto" icon="chat-dots" :title="t('community.messages.select_conversation')">
            {{ t('community.messages.encrypted_title') }}
        </XmEmptyState>
    </div>
</template>
