<script setup lang="ts">
// Liste des conversations (P8-01, prototype messaging.php l.60-70) : titre « Messages » et mention « Chiffrés » (Q32 b :
// chiffrement sur nos serveurs, jamais « de bout en bout »), recherche (nom, pseudonymes ; jamais le contenu), filtre
// Toutes · Non lues, conversations (avatar, nom, heure, aperçu — masqué en mode discret —, non lus, sourdine), bouton
// « Nouvelle conversation ». Navigation au clavier : chaque conversation est un bouton, `aria-current` sur l'ouverte.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import XmAvatar from '@/components/XmAvatar.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import { timeLabel } from '../thread';
import type { Conversation } from '../types';

const props = withDefaults(defineProps<{
    conversations: Conversation[];
    activeId?: string | null;
    search?: string;
    filter?: 'all' | 'unread';
    error?: string | null;
}>(), { activeId: null, search: '', filter: 'all', error: null });

const emit = defineEmits<{
    select: [id: string];
    create: [];
    'update:search': [value: string];
    'update:filter': [value: 'all' | 'unread'];
}>();

const { t } = useI18n();

const items = computed(() => props.conversations);

function preview(c: Conversation): string {
    const last = c.last_message;
    if (!last) return t('community.messages.empty_conversation');
    if (last.preview_hidden) return t('community.messages.preview_hidden');
    const text = last.deleted ? t('community.messages.deleted') : (last.preview || (last.attachment ? t('community.messages.attachment') : ''));
    if (last.mine) return t('community.messages.preview_from', { name: t('community.messages.you'), text });
    if (last.author) return t('community.messages.preview_from', { name: last.author, text });
    return text;
}
</script>

<template>
    <div class="xm-chat-list border-end d-flex flex-column col-12 col-md-4 col-lg-4 flex-shrink-0">
        <div class="p-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                <h1 class="h5 mb-0">{{ t('community.messages.title') }}</h1>
                <span class="small text-success ms-auto" :title="t('community.messages.encrypted_title')">
                    <i class="bi bi-lock-fill me-1" aria-hidden="true"></i>{{ t('community.messages.encrypted_badge') }}
                </span>
                <button type="button" class="btn btn-primary-soft btn-sm xm-btn-icon" :aria-label="t('community.messages.new')" :title="t('community.messages.new')" @click="emit('create')">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                </button>
            </div>
            <label class="visually-hidden" for="convSearch">{{ t('community.messages.search_label') }}</label>
            <input id="convSearch" class="form-control form-control-sm" type="search" :value="search" :placeholder="t('community.messages.search')"
                autocomplete="off" maxlength="60" @input="emit('update:search', ($event.target as HTMLInputElement).value)">
            <div class="nav nav-pills nav-pills-soft small mt-2 gap-1" role="group" :aria-label="t('community.messages.filters.label')">
                <button v-for="value in (['all', 'unread'] as const)" :key="value" type="button"
                    :class="['nav-link py-1 px-2', { active: filter === value }]" :aria-pressed="filter === value ? 'true' : 'false'"
                    @click="emit('update:filter', value)">
                    {{ t(`community.messages.filters.${value}`) }}
                </button>
            </div>
        </div>
        <div class="overflow-auto flex-grow-1" data-xm-conversations>
            <p v-if="error" class="small text-danger p-3 mb-0" role="status">{{ error }}</p>
            <XmEmptyState v-if="items.length === 0" compact icon="chat-dots" :title="t('community.messages.empty_list')">
                <template #action>
                    <button type="button" class="btn btn-primary btn-sm" @click="emit('create')">{{ t('community.messages.new') }}</button>
                </template>
            </XmEmptyState>
            <ul v-else class="list-unstyled mb-0">
                <li v-for="c in items" :key="c.id">
                    <button type="button" :class="['xm-conv list-group-item list-group-item-action d-flex gap-2 align-items-center p-3 w-100 bg-transparent', { active: c.id === activeId }]"
                        :aria-current="c.id === activeId ? 'true' : undefined" :data-id="c.id" @click="emit('select', c.id)">
                        <XmAvatar v-if="c.kind === 'private'" :name="c.name" :src="c.avatar_url" size="sm" />
                        <span v-else class="xm-chat-group-avatar rounded-circle bg-primary bg-opacity-10 text-primary d-inline-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true">
                            <i class="bi bi-people-fill"></i>
                        </span>
                        <span class="overflow-hidden flex-grow-1">
                            <span class="d-flex justify-content-between align-items-baseline gap-1">
                                <strong :class="['text-truncate small', { 'text-body': c.unread_count > 0 }]">{{ c.name }}</strong>
                                <span class="small text-secondary ms-1 flex-shrink-0">{{ timeLabel(c.last_message?.at ?? c.last_message_at, new Date(), 'fr', true) }}</span>
                            </span>
                            <span class="d-flex align-items-center gap-1">
                                <span :class="['small text-truncate flex-grow-1 xm-chat-preview', c.unread_count > 0 ? 'fw-semibold text-body' : 'text-secondary']">{{ preview(c) }}</span>
                                <i v-if="c.muted" class="bi bi-bell-slash small text-secondary" :title="t('community.messages.muted_label')" aria-hidden="true"></i>
                                <span v-if="c.muted" class="visually-hidden">{{ t('community.messages.muted_label') }}</span>
                                <span v-if="c.unread_count > 0" class="badge rounded-pill bg-danger">
                                    {{ c.unread_count }}<span class="visually-hidden"> {{ t('community.messages.unread', { count: c.unread_count }, c.unread_count) }}</span>
                                </span>
                            </span>
                        </span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="p-2 small text-secondary border-top">
            <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>{{ t('community.messages.intro') }}
        </div>
    </div>
</template>
