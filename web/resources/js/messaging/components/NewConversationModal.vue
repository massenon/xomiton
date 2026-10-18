<script setup lang="ts">
// « Nouvelle conversation » (P8-01 ; P15-06 en reprend le parcours) : message privé ou groupe ; destinataires cherchés
// par début de pseudonyme (`GET conversations/recipients`, jamais le nom réel) ou parmi ses connexions sans saisie ;
// groupe : nom (≥ `group_title_min`) et personnes choisies (pastilles retirables). La personne qui ne peut pas recevoir
// de message n'apparaît pas ; un refus du serveur s'affiche sous le champ (message neutre).
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import XmAvatar from '@/components/XmAvatar.vue';
import XmModal from '@/components/XmModal.vue';
import type { Api } from '@/app/useApi';
import { CONVERSATIONS_ENDPOINT } from '../store';
import type { Participant } from '../types';

const props = withDefaults(defineProps<{
    open: boolean;
    api: Api;
    searchMin?: number;
    titleMin?: number;
    busy?: boolean;
    error?: string | null;
}>(), { searchMin: 2, titleMin: 3, busy: false, error: null });

const emit = defineEmits<{
    'update:open': [open: boolean];
    create: [payload: { kind: 'private'; user_id: number } | { kind: 'group'; title: string; user_ids: number[] }];
}>();

const { t } = useI18n();
const mode = ref<'private' | 'group'>('private');
const term = ref('');
const results = ref<Participant[]>([]);
const loading = ref(false);
const selected = ref<Participant[]>([]);
const title = ref('');
let timer: ReturnType<typeof setTimeout> | null = null;
let seq = 0;

const tooShort = computed(() => term.value.trim().length > 0 && term.value.trim().length < props.searchMin);
const canCreateGroup = computed(() => title.value.trim().length >= props.titleMin && selected.value.length > 0);
const choices = computed(() => results.value.filter((p) => !selected.value.some((s) => s.id === p.id)));

async function search() {
    const q = term.value.trim();
    if (q.length > 0 && q.length < props.searchMin) {
        results.value = [];
        return;
    }
    const current = ++seq;
    loading.value = true;
    try {
        const { data } = await props.api.get<{ data: Participant[] }>(`${CONVERSATIONS_ENDPOINT}/recipients`, { query: q ? { q } : {}, silent: true });
        if (current === seq) results.value = Array.isArray(data?.data) ? data.data : [];
    } catch {
        if (current === seq) results.value = [];
    } finally {
        if (current === seq) loading.value = false;
    }
}

watch(term, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => { search().catch(() => undefined); }, 250);
});

watch(() => props.open, (open) => {
    if (!open) return;
    term.value = '';
    title.value = '';
    selected.value = [];
    search().catch(() => undefined);
});

function choose(person: Participant) {
    if (mode.value === 'private') {
        emit('create', { kind: 'private', user_id: person.id });
        return;
    }
    selected.value = [...selected.value, person];
}

function unselect(person: Participant) {
    selected.value = selected.value.filter((p) => p.id !== person.id);
}

function submit() {
    if (mode.value !== 'group' || !canCreateGroup.value) return;
    emit('create', { kind: 'group', title: title.value.trim(), user_ids: selected.value.map((p) => p.id) });
}
</script>

<template>
    <XmModal id="modalNewConversation" :open="open" type="form" form :title="t('community.messages.new_dialog.title')"
        :action="mode === 'group' ? t('community.messages.new_dialog.create') : null" :busy="busy || (mode === 'group' && !canCreateGroup)"
        @update:open="emit('update:open', $event)" @submit="submit">
        <div class="nav nav-pills nav-pills-soft small mb-3 gap-1" role="group">
            <button v-for="value in (['private', 'group'] as const)" :key="value" type="button" :class="['nav-link py-1 px-2', { active: mode === value }]"
                :aria-pressed="mode === value ? 'true' : 'false'" @click="mode = value">{{ t(`community.messages.new_dialog.${value}`) }}</button>
        </div>
        <div v-if="mode === 'group'" class="mb-3">
            <label class="form-label" for="newGroupTitle">{{ t('community.messages.new_dialog.group_title') }}</label>
            <input id="newGroupTitle" v-model="title" class="form-control" type="text" maxlength="120" :minlength="titleMin" required
                :placeholder="t('community.messages.new_dialog.group_title_placeholder')">
            <div class="d-flex flex-wrap gap-1 mt-2" aria-live="polite">
                <span class="small text-secondary w-100">{{ t('community.messages.new_dialog.selected', { count: selected.length }, selected.length) }}</span>
                <button v-for="person in selected" :key="person.id" type="button" class="badge rounded-pill bg-primary bg-opacity-10 text-primary border-0"
                    :aria-label="t('community.messages.new_dialog.remove', { name: person.pseudo })" @click="unselect(person)">
                    {{ person.pseudo }} <i class="bi bi-x" aria-hidden="true"></i>
                </button>
            </div>
        </div>
        <label class="form-label" for="newConversationSearch">{{ t('community.messages.new_dialog.search') }}</label>
        <input id="newConversationSearch" v-model="term" class="form-control" type="search" autocomplete="off" maxlength="40"
            :placeholder="t('community.messages.new_dialog.search_placeholder')" aria-describedby="newConversationHint">
        <div id="newConversationHint" class="form-text">
            {{ tooShort ? t('community.messages.new_dialog.search_hint', { min: searchMin }) : (term.trim() === '' ? t('community.messages.new_dialog.suggestions') : '') }}
        </div>
        <p v-if="error" class="small text-danger mt-2 mb-0" role="alert">{{ error }}</p>
        <ul class="list-unstyled mt-2 mb-0" :aria-busy="loading ? 'true' : 'false'">
            <li v-for="person in choices" :key="person.id">
                <button type="button" class="btn btn-light w-100 d-flex align-items-center gap-2 text-start mb-1" :disabled="busy"
                    :aria-label="mode === 'private' ? t('community.messages.new_dialog.start', { name: person.pseudo }) : t('community.messages.new_dialog.add', { name: person.pseudo })"
                    @click="choose(person)">
                    <XmAvatar :name="person.pseudo" :src="person.avatar_url" size="xs" />
                    <span class="text-truncate">{{ person.pseudo }}</span>
                    <i :class="['bi ms-auto', mode === 'private' ? 'bi-chat-dots' : 'bi-plus-lg']" aria-hidden="true"></i>
                </button>
            </li>
        </ul>
        <p v-if="!loading && choices.length === 0 && !tooShort" class="small text-secondary mt-2 mb-0">{{ t('community.messages.new_dialog.no_people') }}</p>
    </XmModal>
</template>
