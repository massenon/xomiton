<script setup lang="ts">
// Page `/espace-pair/dossiers/{code}` (lot espace-pair, P9-08 ; prototype admin/suivi.php « Frise de suivi »), page
// Inertia `PeerSpace/CaseShow` : fiche de référence en cinq sections **en lecture** (1. Identification pseudonymisée ·
// 2. Faits · 3. Services reçus · 4. Orientation · 5. Consentement : CaseSheet, la fiche de l'administration prime, sinon les
// champs de la collecte), frise de suivi (plus récente d'abord, « En retard » en toutes lettres), ajout d'une étape
// (`POST pair/cases/{code}/followups`, `client_uuid` = Idempotency-Key gardée tant que la saisie ne change pas : un
// nouvel essai après une coupure ne crée pas de doublon, ADR-32), « Marquer comme réalisée » et changement de statut du
// dossier (`PATCH`, transitions du serveur ; la fiche est ensuite relue par une visite partielle `record`, `statuses`,
// `sheet` : transitions suivantes et gestes `can` d'un dossier clos à jour). Sans réseau, l'étape n'est PAS enregistrée (message clair, saisie gardée).
// Dossier d'un autre pair ou code inconnu : 404 (scope visibleTo). Jamais gardée par le service worker (no-store).
// Props : PeerCasesPageController@show (title, record, sheet, steps, outcomes, statuses, limits).
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { uuidV4 } from '@/messaging/uuid';
import { dateFr } from '@/peer/format';
import { peerApi } from '@/peer/api';
import type { CaseItem, CaseSheet, Followup, Option } from '@/peer/types';
import XmAlert from '@/components/XmAlert.vue';
import XmBadge from '@/components/XmBadge.vue';
import XmButton from '@/components/XmButton.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmTimeline, { type TimelineItem } from '@/components/XmTimeline.vue';

const props = defineProps<{
    title: string;
    record: CaseItem;
    sheet: CaseSheet;
    steps: string[];
    outcomes: Option[];
    statuses?: Option[];
    limits?: { comment_max: number };
    today?: string;
}>();

const { t } = useI18n();
const toast = useToast();
const api = peerApi(useApi());
const today = props.today ?? new Date().toISOString().slice(0, 10);

const followups = ref<Followup[]>([...(props.record.followups ?? [])]);
const status = ref(props.record.status);
const statusLabel = ref(props.record.status_label);
const statusBadge = ref(props.record.status_badge);
const nextStatus = ref('');
const statusBusy = ref(false);
const form = reactive({ step: props.steps[0] ?? '', date: today, outcome: 'realise', next_on: '', comment: '' });
const errors = ref<Record<string, string>>({});
const failure = ref('');
const busy = ref(false);
const completing = ref<number | null>(null);
let attempt: { key: string; fingerprint: string } | null = null;

watch(() => props.record, (next) => {
    followups.value = [...(next.followups ?? [])];
    status.value = next.status;
    statusLabel.value = next.status_label;
    statusBadge.value = next.status_badge;
});

const limits = computed(() => props.limits ?? { comment_max: 1000 });
const statusOptions = computed(() => ({ '': t('peer.case.status_choose'), ...Object.fromEntries((props.statuses ?? []).filter((item) => item.value !== status.value).map((item) => [item.value, item.label])) }));
const stepOptions = computed(() => Object.fromEntries(props.steps.map((step) => [step, step])));
const outcomeOptions = computed(() => Object.fromEntries(props.outcomes.map((item) => [item.value, item.label])));
const casesUrl = route('espace-pair.dossiers', undefined, '/espace-pair/dossiers');
const locked = computed(() => !props.record.can.add_followup);
const place = computed(() => [props.record.commune?.name, props.record.zone?.name].filter(Boolean).join(', '));

const timeline = computed<TimelineItem[]>(() => [...followups.value].sort((a, b) => ((b.date ?? '') + String(b.id).padStart(10, '0')).localeCompare((a.date ?? '') + String(a.id).padStart(10, '0'))).map((step) => ({
    id: step.id,
    title: step.step,
    date: dateFr(step.date),
    datetime: step.date,
    by: step.author,
    kind: 'internal',
    badge: { label: step.outcome_label, color: step.outcome_badge },
    text: step.comment,
    next: step.next_on ? dateFr(step.next_on) : null,
    late: step.overdue,
})));
const pending = (id: number | string | null | undefined) => followups.value.some((step) => step.id === id && ['planifie', 'reporte'].includes(step.outcome));

const urgencyLabel = (value: string | null) => (value ? t(`peer.sheet.urgency.${value}`) : '');

function validate(): Record<string, string> {
    const found: Record<string, string> = {};
    if (!props.steps.includes(form.step)) found.step = t('peer.collecte.errors.required');
    if (form.date === '') found.date = t('peer.collecte.errors.required');
    else if (form.date > today) found.date = t('peer.collecte.errors.date_future');
    if (!props.outcomes.some((item) => item.value === form.outcome)) found.outcome = t('peer.collecte.errors.required');
    if (form.next_on !== '' && form.date !== '' && form.next_on < form.date) found.next_on = t('peer.case.errors.next_before');
    if (Array.from(form.comment).length > limits.value.comment_max) found.comment = t('peer.case.errors.comment_max', { max: limits.value.comment_max });
    return found;
}

/** Même saisie → même clé (nouvel essai sans doublon) ; saisie modifiée → nouvelle clé. */
function keyFor(fingerprint: string): string {
    if (attempt === null || attempt.fingerprint !== fingerprint) attempt = { key: uuidV4(), fingerprint };
    return attempt.key;
}

async function addFollowup() {
    if (busy.value) return;
    failure.value = '';
    errors.value = validate();
    if (Object.keys(errors.value).length > 0) return;
    const payload = {
        date: form.date,
        step: form.step,
        outcome: form.outcome,
        ...(form.next_on !== '' ? { next_on: form.next_on } : {}),
        ...(form.comment.trim() !== '' ? { comment: form.comment.trim() } : {}),
    };
    busy.value = true;
    try {
        const created = await api.addFollowup(props.record.code, { ...payload, client_uuid: keyFor(JSON.stringify(payload)) });
        if (!followups.value.some((step) => step.id === created.id)) followups.value = [...followups.value, created];
        attempt = null;
        Object.assign(form, { step: props.steps[0] ?? '', date: today, outcome: 'realise', next_on: '', comment: '' });
        errors.value = {};
        toast.show(t('peer.case.followup_saved'), 'success');
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            errors.value = fieldErrors(cause);
            if (Object.keys(errors.value).length === 0) failure.value = cause.message;
        } else if (cause instanceof ApiError && cause.status === 0) {
            failure.value = t('peer.case.offline');
        } else {
            failure.value = cause instanceof ApiError ? cause.message : t('microcopy.error.generic');
        }
    } finally {
        busy.value = false;
    }
}

async function complete(id: number) {
    if (completing.value !== null) return;
    completing.value = id;
    try {
        const updated = await api.completeFollowup(props.record.code, id);
        followups.value = followups.value.map((step) => (step.id === id ? updated : step));
        toast.show(t('peer.case.followup_done'), 'success');
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('peer.case.offline'), 'warning');
        else if (cause instanceof ApiError && cause.status !== 429) toast.show(cause.message, 'danger');
    } finally {
        completing.value = null;
    }
}

async function changeStatus() {
    if (statusBusy.value || nextStatus.value === '') return;
    statusBusy.value = true;
    try {
        const updated = await api.changeCaseStatus(props.record.code, nextStatus.value);
        status.value = updated.status;
        statusLabel.value = updated.status_label;
        statusBadge.value = updated.status_badge;
        nextStatus.value = '';
        toast.show(t('peer.case.status_saved'), 'success');
        router.reload({ only: ['record', 'statuses', 'sheet'] });
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('peer.case.offline'), 'warning');
        else if (cause instanceof ApiError && cause.status !== 429) toast.show(cause.message, 'danger');
    } finally {
        statusBusy.value = false;
    }
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div>
            <Link :href="casesUrl" class="small"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ t('peer.case.back') }}</Link>
            <div class="d-flex flex-wrap align-items-center gap-2 mt-1">
                <h1 class="h3 mb-0">{{ record.code }}</h1>
                <XmBadge color="neutral">{{ record.kind_label }}</XmBadge>
                <XmBadge :color="statusBadge" data-peer-status>{{ statusLabel }}</XmBadge>
                <XmBadge v-if="record.anonymized" color="secondary">{{ t('peer.case.anonymized') }}</XmBadge>
            </div>
            <p class="small text-secondary mb-0 mt-1">
                {{ [record.sex_label, record.age_range_label ? t('peer.case.age', { range: record.age_range_label }) : null, record.population?.label, place].filter(Boolean).join(' · ') }}
                <template v-if="record.occurred_on"> · {{ t('peer.case.received', { date: dateFr(record.occurred_on) }) }}</template>
            </p>
        </div>

        <section v-if="record.can.update && (statuses ?? []).length > 0" class="card card-body" aria-labelledby="peerCaseStatus">
            <h2 id="peerCaseStatus" class="h6">{{ t('peer.case.status_title') }}</h2>
            <form class="row g-2 align-items-end" novalidate @submit.prevent="changeStatus">
                <div class="col-sm-6 col-md-4"><XmField v-model="nextStatus" name="next_status" type="select" :label="t('peer.case.status_label')" :options="statusOptions" /></div>
                <div class="col-auto mb-3"><XmButton type="submit" size="sm" :loading="statusBusy" :disabled="nextStatus === ''">{{ t('peer.case.status_submit') }}</XmButton></div>
            </form>
        </section>

        <XmAlert v-if="!sheet.filled" type="info" class="mb-0">{{ t('peer.sheet.not_filled') }}</XmAlert>
        <div v-else class="small text-secondary"><i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>{{ t('peer.sheet.reference', { reference: sheet.reference }) }}</div>

        <div class="row g-3" data-peer-sheet>
            <section class="col-md-6" aria-labelledby="sheet1"><div class="card h-100"><div class="card-body">
                <h2 id="sheet1" class="h6">{{ t('peer.sheet.sections.identification') }}</h2>
                <dl class="row small mb-0">
                    <template v-if="sheet.identification.pseudo"><dt class="col-5">{{ t('peer.sheet.fields.pseudo') }}</dt><dd class="col-7">{{ sheet.identification.pseudo }}</dd></template>
                    <dt class="col-5">{{ t('peer.sheet.fields.sex') }}</dt><dd class="col-7">{{ sheet.identification.sex || '—' }}</dd>
                    <dt class="col-5">{{ t('peer.sheet.fields.age') }}</dt><dd class="col-7">{{ sheet.identification.age_range || '—' }}</dd>
                    <dt class="col-5">{{ t('peer.sheet.fields.population') }}</dt><dd class="col-7">{{ sheet.identification.population || '—' }}</dd>
                    <dt class="col-5">{{ t('peer.sheet.fields.zone') }}</dt><dd class="col-7">{{ [sheet.identification.commune, sheet.identification.zone].filter(Boolean).join(', ') || '—' }}</dd>
                </dl>
            </div></div></section>
            <section class="col-md-6" aria-labelledby="sheet2"><div class="card h-100"><div class="card-body">
                <h2 id="sheet2" class="h6">{{ t('peer.sheet.sections.facts') }}</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">{{ t('peer.sheet.fields.date') }}</dt><dd class="col-7">{{ dateFr(sheet.facts.date) || '—' }}</dd>
                    <template v-if="sheet.facts.place"><dt class="col-5">{{ t('peer.sheet.fields.place') }}</dt><dd class="col-7">{{ sheet.facts.place }}</dd></template>
                    <dt class="col-5">{{ t('peer.sheet.fields.type') }}</dt><dd class="col-7">{{ sheet.facts.type || '—' }}</dd>
                    <dt class="col-5">{{ t('peer.sheet.fields.description') }}</dt><dd class="col-7 text-break">{{ sheet.facts.description || t('peer.sheet.empty') }}</dd>
                </dl>
            </div></div></section>
            <section class="col-md-4" aria-labelledby="sheet3"><div class="card h-100"><div class="card-body">
                <h2 id="sheet3" class="h6">{{ t('peer.sheet.sections.services') }}</h2>
                <ul v-if="sheet.services.items.length > 0" class="small mb-1 ps-3"><li v-for="item in sheet.services.items" :key="item">{{ item }}</li></ul>
                <p v-else class="small text-secondary mb-1">{{ t('peer.sheet.empty') }}</p>
                <p v-if="sheet.services.note" class="small mb-0 text-break">{{ sheet.services.note }}</p>
            </div></div></section>
            <section class="col-md-4" aria-labelledby="sheet4"><div class="card h-100"><div class="card-body">
                <h2 id="sheet4" class="h6">{{ t('peer.sheet.sections.orientation') }}</h2>
                <dl v-if="sheet.orientation.referral || sheet.orientation.contact" class="row small mb-0">
                    <dt class="col-5">{{ t('peer.sheet.fields.referral') }}</dt><dd class="col-7">{{ sheet.orientation.referral || sheet.orientation.contact }}</dd>
                    <template v-if="sheet.orientation.contact && sheet.orientation.referral"><dt class="col-5">{{ t('peer.sheet.fields.contact') }}</dt><dd class="col-7">{{ sheet.orientation.contact }}</dd></template>
                    <template v-if="sheet.orientation.date"><dt class="col-5">{{ t('peer.sheet.fields.referral_date') }}</dt><dd class="col-7">{{ dateFr(sheet.orientation.date) }}</dd></template>
                    <template v-if="sheet.orientation.reason"><dt class="col-5">{{ t('peer.sheet.fields.reason') }}</dt><dd class="col-7">{{ sheet.orientation.reason }}</dd></template>
                    <template v-if="sheet.orientation.urgency"><dt class="col-5">{{ t('peer.sheet.fields.urgency') }}</dt><dd class="col-7">{{ urgencyLabel(sheet.orientation.urgency) }}</dd></template>
                </dl>
                <p v-else class="small text-secondary mb-0">{{ t('peer.sheet.no_referral') }}</p>
            </div></div></section>
            <section class="col-md-4" aria-labelledby="sheet5"><div class="card h-100"><div class="card-body">
                <h2 id="sheet5" class="h6">{{ t('peer.sheet.sections.consent') }}</h2>
                <dl v-if="sheet.consent.given !== null" class="row small mb-0">
                    <dt class="col-5">{{ t('peer.sheet.fields.consent') }}</dt><dd class="col-7">{{ sheet.consent.given ? t('peer.sheet.yes') : t('peer.sheet.no') }}</dd>
                    <template v-if="sheet.consent.mode"><dt class="col-5">{{ t('peer.sheet.fields.mode') }}</dt><dd class="col-7">{{ t(`peer.sheet.modes.${sheet.consent.mode}`) }}</dd></template>
                    <dt class="col-5">{{ t('peer.sheet.fields.sharing') }}</dt><dd class="col-7">{{ sheet.consent.sharing ? t('peer.sheet.yes') : t('peer.sheet.no') }}</dd>
                </dl>
                <p v-else class="small text-secondary mb-0">{{ t('peer.sheet.empty') }}</p>
            </div></div></section>
        </div>

        <div class="row g-4">
            <section class="col-xl-7" aria-labelledby="peerTimeline">
                <div class="card h-100">
                    <div class="card-header"><h2 id="peerTimeline" class="h6 mb-0">{{ t('peer.case.timeline') }}</h2></div>
                    <div class="card-body">
                        <XmTimeline :items="timeline" :label="t('peer.case.timeline')" :empty="t('peer.case.timeline_empty')">
                            <template #actions="{ item }">
                                <XmButton v-if="!locked && pending(item.id)" class="mt-1" size="sm" variant="outline" icon="check2" :loading="completing === item.id" :disabled="completing !== null" @click="complete(Number(item.id))">{{ t('peer.case.mark_done') }}</XmButton>
                            </template>
                        </XmTimeline>
                    </div>
                </div>
            </section>
            <section class="col-xl-5" aria-labelledby="peerAddStep">
                <div class="card h-100">
                    <div class="card-header"><h2 id="peerAddStep" class="h6 mb-0">{{ t('peer.case.add_title') }}</h2></div>
                    <div class="card-body">
                        <XmAlert v-if="locked" type="info" class="mb-0">{{ t('peer.case.locked') }}</XmAlert>
                        <form v-else novalidate data-peer-followup @submit.prevent="addFollowup">
                            <XmErrorSummary :messages="errors" :autofocus="false" />
                            <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
                            <XmField v-model="form.step" name="step" type="select" :label="t('peer.case.fields.step')" :options="stepOptions" :error="errors.step" />
                            <div class="row g-2">
                                <div class="col-6"><XmField v-model="form.date" name="date" type="date" :label="t('peer.case.fields.date')" :max="today" :error="errors.date" /></div>
                                <div class="col-6"><XmField v-model="form.outcome" name="outcome" type="select" :label="t('peer.case.fields.outcome')" :options="outcomeOptions" :error="errors.outcome" /></div>
                            </div>
                            <XmField v-model="form.next_on" name="next_on" type="date" optional :label="t('peer.case.fields.next')" :min="form.date" :error="errors.next_on" />
                            <XmField v-model="form.comment" name="comment" type="textarea" optional :rows="2" :maxlength="limits.comment_max" :label="t('peer.case.fields.comment')" :error="errors.comment" />
                            <XmButton type="submit" class="w-100" icon="plus-lg" :loading="busy">{{ t('peer.case.add_submit') }}</XmButton>
                        </form>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
