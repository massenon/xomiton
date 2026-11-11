<script setup lang="ts">
// Sondage d'une publication (lot fil-ecrans, P7-05) : port de x_render_poll (prototype inc/render.php l.120-183) et
// du vote sans rechargement de poll-vote.php, sur l'API `POST · DELETE posts/{ulid}/poll/vote` (docs/API.md §8.2, §8.4).
// Formulaire tant que la personne n'a pas voté et que le sondage est ouvert (barres et % si `results_visible`), sinon
// résultats : barres %, réponse en tête (`is-lead`), coche sur ses choix, participant·es · votes, « Retirer mon vote ».
// Le serveur seul calcule les résultats (RG-09 : aucun vote individuel, `my_choices` de la seule personne qui lit) ;
// le sondage renvoyé remplace l'ancien (émis par `update:poll`). Résultat annoncé dans une région polite.
// postId (ULID), poll (PollResource), level (niveau du titre de la question, 3 par défaut).
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { feedApi } from '@/feed/api';
import { pollRemaining } from '@/feed/format';
import type { Poll } from '@/feed/types';

const props = withDefaults(defineProps<{ postId: string; poll: Poll; level?: number }>(), { level: 3 });
const emit = defineEmits<{ 'update:poll': [poll: Poll] }>();

const { t } = useI18n();
const toast = useToast();
const api = feedApi(useApi());

const selected = ref<number[]>([]);
const busy = ref(false);
const error = ref('');
const announce = ref('');

const questionId = computed(() => `poll-${props.postId}-question`);
const heading = computed(() => `h${Math.min(6, Math.max(2, props.level))}`);
const showForm = computed(() => !props.poll.has_voted && props.poll.can_vote);
const remaining = computed(() => pollRemaining(props.poll.ends_at, props.poll.closed, t));
const totalVotes = computed(() => props.poll.votes_count ?? 0);
const participants = computed(() => t('feed.poll.participants', { count: props.poll.voters_count }, props.poll.voters_count));
const percent = (value: number | null) => t('feed.poll.percent', { value: Math.round(value ?? 0) });
const isMine = (id: number) => props.poll.my_choices.includes(id);

watch(() => props.poll.my_choices, (choices) => { selected.value = [...choices]; }, { immediate: true });

function choose(id: number, event: Event) {
    const checked = (event.target as HTMLInputElement).checked;
    if (!props.poll.multiple) {
        selected.value = checked ? [id] : [];
        return;
    }
    selected.value = checked ? [...new Set([...selected.value, id])] : selected.value.filter((value) => value !== id);
}

const failure = (cause: unknown): string => {
    if (cause instanceof ApiError) {
        if (cause.code === 'poll_closed') return t('feed.poll.already_closed');
        if (cause.status === 0) return t('microcopy.error.unreachable');
        if (cause.status === 422 || cause.status === 429) return cause.message;
    }
    return t('feed.poll.vote_failed');
};

async function send(choices: number[] | null) {
    if (busy.value) return;
    error.value = '';
    if (choices !== null && choices.length === 0) {
        error.value = t('feed.poll.choose_one');
        return;
    }
    busy.value = true;
    try {
        const poll = await api.vote(props.postId, choices);
        emit('update:poll', poll);
        const message = choices === null ? t('feed.poll.vote_removed') : t('feed.poll.voted');
        announce.value = message;
        toast.show(message, 'success');
    } catch (cause) {
        error.value = failure(cause);
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="xm-poll border rounded-3 p-3 mb-3" :data-poll="postId" :aria-busy="busy ? 'true' : undefined">
        <div class="d-flex align-items-start gap-2 mb-2">
            <span class="icon-md rounded-circle bg-primary bg-opacity-10 text-primary flex-shrink-0"><i class="bi bi-bar-chart-fill" aria-hidden="true"></i></span>
            <div>
                <component :is="heading" :id="questionId" class="h6 mb-0">{{ poll.question }}</component>
                <div class="small text-secondary">{{ poll.multiple ? t('feed.poll.multiple_choice') : t('feed.poll.single_choice') }} {{ t('common.separator') }} {{ remaining }}</div>
            </div>
        </div>

        <form v-if="showForm" class="vstack gap-2" novalidate data-poll-form @submit.prevent="send(selected)">
            <fieldset class="vstack gap-2" :aria-describedby="error ? `${questionId}-error` : undefined">
                <legend class="visually-hidden">{{ poll.question }}</legend>
                <label v-for="option in poll.options" :key="option.id" class="xm-poll-opt d-flex align-items-center gap-2 border rounded-2 px-3 py-2 position-relative overflow-hidden">
                    <span v-if="poll.results_visible" class="xm-poll-bar" :style="{ width: `${Math.round(option.percentage ?? 0)}%` }"></span>
                    <input class="form-check-input m-0 position-relative" :type="poll.multiple ? 'checkbox' : 'radio'" :name="`poll-${postId}`" :value="option.id" :checked="selected.includes(option.id)" @change="choose(option.id, $event)">
                    <span class="flex-grow-1 position-relative">{{ option.label }}</span>
                    <span v-if="poll.results_visible" class="small fw-semibold position-relative">{{ percent(option.percentage) }}</span>
                </label>
            </fieldset>
            <p v-if="error" :id="`${questionId}-error`" class="small text-danger mb-0"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ error }}</p>
            <div class="d-flex align-items-center justify-content-between gap-2">
                <span class="small text-secondary">{{ participants }}</span>
                <button class="btn btn-sm btn-primary" type="submit" :disabled="busy" :aria-busy="busy ? 'true' : undefined"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ t('feed.poll.vote') }}</button>
            </div>
        </form>

        <template v-else>
            <ul class="list-unstyled vstack gap-2 mb-2" :aria-labelledby="questionId">
                <li v-for="option in poll.options" :key="option.id" :class="['xm-poll-opt d-flex align-items-center gap-2 border rounded-2 px-3 py-2 position-relative overflow-hidden', { 'is-lead': option.leading && totalVotes > 0 }]">
                    <span class="xm-poll-bar" :style="{ width: `${Math.round(option.percentage ?? 0)}%` }"></span>
                    <span class="flex-grow-1 position-relative">{{ option.label }}<template v-if="isMine(option.id)"> <i class="bi bi-check-circle-fill text-primary" :title="t('feed.poll.my_choice')" aria-hidden="true"></i><span class="visually-hidden"> {{ t('feed.poll.my_choice_sr') }}</span></template><span v-if="option.leading && totalVotes > 0" class="visually-hidden"> ({{ t('feed.poll.leading') }})</span></span>
                    <span class="small text-secondary position-relative">{{ t('feed.poll.votes', { count: option.votes_count ?? 0 }, option.votes_count ?? 0) }}</span>
                    <span class="small fw-bold position-relative xm-poll-pc">{{ percent(option.percentage) }}</span>
                </li>
            </ul>
            <p v-if="error" class="small text-danger mb-2"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ error }}</p>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small text-secondary">
                <span><i class="bi bi-people me-1" aria-hidden="true"></i>{{ participants }} {{ t('common.separator') }} {{ t('feed.poll.votes', { count: totalVotes }, totalVotes) }}</span>
                <button v-if="poll.has_voted && poll.can_vote" class="btn btn-link btn-sm p-0" type="button" :disabled="busy" @click="send(null)">{{ t('feed.poll.remove_vote') }}</button>
            </div>
        </template>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>
</template>
