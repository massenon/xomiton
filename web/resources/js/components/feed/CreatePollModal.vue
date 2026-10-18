<script setup lang="ts">
// Modale « Créer un sondage » (lot fil-ecrans, P7-04 ; prototype partials/modals.php #modalCreatePoll et
// assets/js/xomiton.js [data-poll-create]), chargée à la demande. Question 5 à 200 caractères avec compteur (lu avec
// le champ ; annoncé seulement à 20 caractères de la limite, une fois la frappe posée),
// 2 à 6 réponses (ajout, retrait, numérotées ; vides retirées, doublons refusés casse et accents ignorés), texte
// d'accompagnement, image facultative (5 Mo, aperçu), durée (1 j à 1 mois, ou sans fin), visibilité, interrupteurs
// « plusieurs réponses », « résultats avant le vote », « pseudonyme », mention de confidentialité des votes (RG-09).
// Plein écran sous 576 px (modal-fullscreen-sm-down). Brouillon, envoi, hors connexion : feed/useComposer.ts.
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { DRAFT_POLL, allowedVisibility, emptyPoll, length, validatePoll } from '@/feed/composer';
import { useComposer, type RecoveredEntry } from '@/feed/useComposer';
import type { ComposerOptions, Post } from '@/feed/types';
import XmAlert from '@/components/XmAlert.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = withDefaults(defineProps<{ options: ComposerOptions; recovered?: RecoveredEntry | null }>(), { recovered: null });
const emit = defineEmits<{ published: [post: Post]; hidden: [] }>();

const { t } = useI18n();
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const limits = computed(() => props.options.limits);

const { form, preview, errors, failure, busy, restored, waiting, setImage, submit } = useComposer({
    kind: 'poll',
    recovered: props.recovered,
    draft: DRAFT_POLL,
    options: props.options,
    initial: () => emptyPoll(props.options.default_duration),
    validate: (value, image) => validatePoll(value, image, props.options.limits, t),
    onPublished: (post) => emit('published', post),
    close: () => modal.value?.close(),
});

const questionCount = computed(() => length(form.question));

/** Compteur annoncé près de la limite seulement, après une pause de frappe (jamais à chaque caractère). */
const COUNT_WARN = 20;
const COUNT_DELAY = 800;
const countAnnounce = ref('');
let countTimer: ReturnType<typeof setTimeout> | null = null;
watch(questionCount, (count) => {
    if (countTimer) clearTimeout(countTimer);
    countTimer = null;
    const max = limits.value.question_max;
    if (max - count > COUNT_WARN) {
        countAnnounce.value = '';
        return;
    }
    countTimer = setTimeout(() => { countAnnounce.value = t('feed.poll.question_count_sr', { count, max }); }, COUNT_DELAY);
});
onBeforeUnmount(() => { if (countTimer) clearTimeout(countTimer); });
const durations = computed(() => Object.fromEntries(props.options.durations.map((option) => [String(option.value), option.label])));
const duration = computed({
    get: () => String(form.duration_days),
    set: (value: string | number | boolean | null | undefined | Array<string | number>) => { form.duration_days = Number(value) || 0; },
});
const visibilities = computed(() => Object.fromEntries(props.options.visibilities
    .filter((option) => !(form.is_anonymous && option.value === 'followers'))
    .map((option) => [option.value, option.label])));
const maxMb = computed(() => Math.floor(limits.value.image_max_kb / 1024));
const accept = computed(() => limits.value.image_types.join(','));

watch(() => form.is_anonymous, (anonymous) => { form.visibility = allowedVisibility(form.visibility, anonymous); });

function addOption() {
    if (form.options.length >= limits.value.options_max) return;
    form.options.push('');
    nextTick(() => document.querySelector<HTMLInputElement>(`#f-option-${form.options.length - 1}`)?.focus());
}

function removeOption(index: number) {
    if (form.options.length <= limits.value.options_min) return;
    form.options.splice(index, 1);
    nextTick(() => document.querySelector<HTMLInputElement>(`#f-option-${Math.max(0, index - 1)}`)?.focus());
}

function onFile(event: Event) {
    const input = event.target as HTMLInputElement;
    setImage(input.files?.[0] ?? null);
    if (!input.files?.[0] || errors.value.image) input.value = '';
}

function clearPhoto() {
    setImage(null);
    if (fileInput.value) fileInput.value.value = '';
}

async function onSubmit() {
    const ok = await submit();
    if (!ok) await nextTick(() => document.querySelector<HTMLElement>('#modalCreatePoll [aria-invalid="true"], #modalCreatePoll .xm-error-summary')?.focus());
}
</script>

<template>
    <XmModal ref="modal" id="modalCreatePoll" :title="t('feed.poll.title')" type="fullscreen" form :open="true" :action="t('feed.poll.submit')" :busy="busy" @submit="onSubmit" @hidden="emit('hidden')">
        <XmAlert v-if="waiting" type="info" class="small">{{ t('feed.offline.pending') }}</XmAlert>
        <XmAlert v-else-if="restored" type="info" class="small">{{ t('feed.create.draft_restored') }}</XmAlert>
        <XmErrorSummary :messages="errors" :title="t('feed.create.errors_title')" />
        <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>

        <div class="mb-3">
            <label class="form-label" for="f-question">{{ t('feed.poll.question') }} <span aria-hidden="true">*</span><span class="visually-hidden">{{ t('microcopy.a11y.required') }}</span></label>
            <input id="f-question" v-model="form.question" class="form-control" name="question" required :minlength="limits.question_min" :maxlength="limits.question_max" :placeholder="t('feed.poll.question_placeholder')" aria-describedby="f-question-count" :aria-invalid="errors.question ? 'true' : undefined">
            <div id="f-question-count" class="form-text text-end">
                <span aria-hidden="true">{{ t('feed.poll.question_counter', { count: questionCount, max: limits.question_max }) }}</span>
                <span class="visually-hidden">{{ t('feed.poll.question_count_sr', { count: questionCount, max: limits.question_max }) }}</span>
            </div>
            <p class="visually-hidden" role="status" data-poll-count-live>{{ countAnnounce }}</p>
            <div v-if="errors.question" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.question }}</div>
        </div>

        <fieldset id="f-options" class="mb-3" tabindex="-1" :aria-invalid="errors.options ? 'true' : undefined" :aria-describedby="errors.options ? 'f-options-error' : undefined">
            <legend class="form-label fs-6 mb-1">{{ t('feed.poll.options') }} <span aria-hidden="true">*</span> <span class="small text-secondary">{{ t('feed.poll.options_hint', { min: limits.options_min, max: limits.options_max }) }}</span></legend>
            <div class="vstack gap-2" data-poll-options>
                <div v-for="(_, index) in form.options" :key="index" class="input-group xm-poll-option-row">
                    <span class="input-group-text">{{ index + 1 }}</span>
                    <input :id="`f-option-${index}`" v-model="form.options[index]" class="form-control" :name="`options[${index}]`" :maxlength="limits.option_max" :required="index < limits.options_min" :placeholder="t('feed.poll.option_placeholder', { n: index + 1 })" :aria-label="t('feed.poll.option_label', { n: index + 1 })">
                    <button class="btn btn-light text-danger" type="button" :aria-label="t('feed.poll.option_remove', { n: index + 1 })" :disabled="form.options.length <= limits.options_min" data-poll-remove @click="removeOption(index)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
            </div>
            <button v-if="form.options.length < limits.options_max" type="button" class="btn btn-sm btn-primary-soft mt-2" data-poll-add @click="addOption"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ t('feed.poll.option_add') }}</button>
            <div v-if="errors.options" id="f-options-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.options }}</div>
        </fieldset>

        <XmField v-model="form.body" name="body" type="textarea" :rows="2" :maxlength="limits.body_max" optional :label="t('feed.poll.text')" :placeholder="t('feed.poll.text_placeholder')" :error="errors.body" />

        <div class="mb-3">
            <label class="form-label" for="f-image"><i class="bi bi-image-fill text-success me-1" aria-hidden="true"></i>{{ t('feed.poll.image') }} <span class="small text-secondary">{{ t('feed.poll.image_hint', { max: maxMb }) }}</span></label>
            <input id="f-image" ref="fileInput" class="form-control form-control-sm" type="file" name="image" :accept="accept" :aria-invalid="errors.image ? 'true' : undefined" @change="onFile">
            <div v-if="errors.image" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.image }}</div>
            <div v-if="preview" class="mt-2">
                <img :src="preview" class="img-fluid rounded xm-poll-preview" :alt="t('feed.poll.image_alt')">
                <div><button type="button" class="btn btn-link btn-sm px-0" @click="clearPhoto"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ t('feed.create.photo_remove') }}</button></div>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-sm-6">
                <XmField v-model="duration" name="duration_days" type="select" :label="t('feed.poll.duration')" :options="durations" :error="errors.duration_days" />
            </div>
            <div class="col-sm-6">
                <XmField v-model="form.visibility" name="visibility" type="select" :label="t('feed.poll.visibility')" :options="visibilities" :hint="form.is_anonymous ? t('feed.create.anonymous_followers') : null" :error="errors.visibility" />
            </div>
        </div>
        <XmField v-model="form.multiple" name="multiple" type="switch" :label="t('feed.poll.multiple')" />
        <XmField v-model="form.results_before_vote" name="results_before_vote" type="switch" :label="t('feed.poll.results_before_vote')" />
        <XmField v-model="form.is_anonymous" name="is_anonymous" type="switch" :label="t('feed.poll.anonymous')" />
        <p class="small text-secondary mt-2 mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ t('feed.poll.privacy') }}</p>
    </XmModal>
</template>
