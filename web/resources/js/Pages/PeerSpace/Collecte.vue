<script setup lang="ts">
// Page `/collecte` (lot espace-pair, P9-04 ; prototype collecte.php), page Inertia `PeerSpace/Collecte` : fiche de dossier
// VBG / VDH utilisable **sans réseau**. Les listes fermées (natures, département → commune en cascade, populations,
// sexes, tranches d'âge Q25, types de violence, orientations) et les règles ACTIVES du catalogue viennent des props de la
// page déjà chargée : le formulaire se valide côté client comme côté serveur (peer/collecte.ts : champs obligatoires,
// date non future RG-07, commune du département RG-08, notes vides ou ≥ 20 caractères). L'envoi passe toujours par la
// boîte d'envoi commune (store `outbox`, type `collecte` → POST pair/cases, `client_uuid` = clé d'idempotence, ADR-32) :
// réseau là, elle part tout de suite ; coupé, elle attend (file visible) et part au retour du réseau, une seule fois.
// Le code VBG-nnnn / VDH-nnnn est attribué par le serveur (annoncé à l'envoi réussi). Brouillon : IndexedDB (store
// `drafts`, expire après `draft_days` jours, 1 par défaut : ces champs sont des quasi-identifiants d'une victime), jamais
// le champ « notes » (aucun texte libre sur l'appareil hors boîte d'envoi).
// Fiche refusée par le serveur après la mise en file : elle est gardée en mémoire (peer/rejections.ts), les champs refusés
// sont nommés et « Corriger et renvoyer » la remet dans le formulaire ; un refus tombé pendant une visite ailleurs est
// affiché au retour sur la page. Avertissement permanent : jamais de nom ni de coordonnées. Props : CollectePageController.
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import { useToast } from '@/app/useToast';
import { claim, dismiss, forget, recordFailures, rejections, remember, type Rejection } from '@/peer/rejections';
import { draftIsEmpty, DRAFT_EXCLUDED, DRAFT_NAME, emptyValues, restoreDraft, toPayload, validate } from '@/peer/collecte';
import type { CaseItem, CollecteForm, CollecteOptions, CollecteValues, ValidationRule } from '@/peer/types';
import { useDraftsStore } from '@/stores/drafts';
import { useOutboxStore, type FlushDetail } from '@/stores/outbox';
import { useUiStore } from '@/stores/ui';
import XmAlert from '@/components/XmAlert.vue';
import XmBadge from '@/components/XmBadge.vue';
import XmButton from '@/components/XmButton.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';

const props = withDefaults(defineProps<{
    title: string;
    form: CollecteForm;
    options: CollecteOptions;
    rules: ValidationRule[];
    today: string;
    recent?: CaseItem[];
    draft_days?: number;
}>(), { draft_days: 1 });

const { t } = useI18n();
const toast = useToast();
const ui = useUiStore();
const outbox = useOutboxStore();
const drafts = useDraftsStore();

const values = reactive<CollecteValues>(emptyValues(props.today));
const errors = ref<Record<string, string>>({});
const submitting = ref(false);
const status = ref('');
const warnings = ref<string[]>([]);
const hasDraft = ref(false);
const ready = ref(false);
const summary = ref<HTMLElement | null>(null);
let saveTimer: ReturnType<typeof setTimeout> | null = null;

const context = computed(() => ({ form: props.form, options: props.options, rules: props.rules, today: props.today }));
const queued = computed(() => outbox.byType.collecte ?? 0);
const formEmpty = computed(() => draftIsEmpty(values, props.today) && values.notes.trim() === '');
const communes = computed(() => props.form.zones.find((zone) => String(zone.id) === values.zone_id)?.communes ?? []);

// Tableaux { value, label } et non objets : un objet JS trie ses clés entières (identifiants) avant la clé vide « Choisir… ».
const select = (items: Array<string | number>, labels: string[], empty: string): Array<{ value: string; label: string }> => [{ value: '', label: empty }, ...items.map((item, i) => ({ value: String(item), label: labels[i] }))];
const choose = computed(() => t('peer.collecte.choose'));
const kindOptions = computed(() => select(props.form.kinds.map((k) => k.value), props.form.kinds.map((k) => k.label), choose.value));
const violenceOptions = computed(() => select(props.form.violence_types.map((v) => v.id), props.form.violence_types.map((v) => v.label), choose.value));
const zoneOptions = computed(() => select(props.form.zones.map((z) => z.id), props.form.zones.map((z) => z.name), choose.value));
const communeOptions = computed(() => select(communes.value.map((c) => c.id), communes.value.map((c) => c.name), t('peer.collecte.none')));
const populationOptions = computed(() => select(props.form.populations.map((p) => p.id), props.form.populations.map((p) => p.label), choose.value));
const sexOptions = computed(() => select(props.form.sexes.map((s) => s.value), props.form.sexes.map((s) => s.label), choose.value));
const ageOptions = computed(() => select(props.options.age_ranges.map((a) => a.value), props.options.age_ranges.map((a) => a.label), choose.value));
const referralOptions = computed(() => select(props.options.referrals, props.options.referrals, t('peer.collecte.no_referral')));

// Commune qui ne suit plus le département choisi : remise à zéro (cascade département → commune, RG-08).
watch(() => values.zone_id, () => {
    if (!communes.value.some((commune) => String(commune.id) === values.commune_id)) values.commune_id = '';
});

function scheduleDraft() {
    if (!ready.value) return;
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(async () => {
        saveTimer = null;
        try {
            if (draftIsEmpty(values, props.today)) {
                await drafts.remove(DRAFT_NAME);
                hasDraft.value = false;
            } else {
                await drafts.save(DRAFT_NAME, { ...values }, Date.now(), DRAFT_EXCLUDED, props.draft_days);
                hasDraft.value = true;
            }
        } catch {
            // IndexedDB indisponible (navigation privée) : le formulaire fonctionne, sans brouillon.
        }
    }, 500);
}
watch(values, scheduleDraft, { deep: true });

async function clearDraft() {
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = null;
    hasDraft.value = false;
    await drafts.remove(DRAFT_NAME).catch(() => undefined);
}

function resetForm() {
    Object.assign(values, emptyValues(props.today));
    errors.value = {};
}

async function focusSummary() {
    await nextTick();
    summary.value?.querySelector<HTMLElement>('.xm-error-summary')?.focus();
}

async function submit() {
    if (submitting.value) return;
    warnings.value = [];
    errors.value = validate(values, context.value, t);
    if (Object.keys(errors.value).length > 0) {
        await focusSummary();
        return;
    }
    submitting.value = true;
    try {
        const entry = await outbox.queue('collecte', toPayload(values));
        if (entry && typeof (entry as { id?: unknown }).id === 'string') remember((entry as { id: string }).id, values);
        status.value = ui.online ? t('peer.collecte.sending') : t('peer.collecte.queued_offline');
        // Formulaire vidé d'abord : la fiche est partie en file, le brouillon s'efface ensuite (IndexedDB, asynchrone).
        resetForm();
        await clearDraft();
    } catch {
        toast.show(t('microcopy.error.generic'), 'danger');
    } finally {
        submitting.value = false;
    }
}

/** Résultat d'un passage de la boîte d'envoi : codes attribués, avertissements non bloquants, refus du serveur. */
function onFlush(detail: FlushDetail | null) {
    if (!detail || !claim(detail)) return;
    const sent = detail.sent.filter((item) => item.type === 'collecte');
    const failed = detail.failed.filter((item) => item.type === 'collecte');
    if (sent.length > 0) {
        forget(sent.map((item) => item.id));
        const codes = sent.map((item) => (item.data as { code?: string } | undefined)?.code).filter((code): code is string => typeof code === 'string');
        status.value = codes.length > 0 ? t('peer.collecte.saved', { codes: codes.join(', ') }, codes.length) : t('peer.collecte.saved_plain');
        toast.show(status.value, 'success');
        const notes = sent.flatMap((item) => ((item as { meta?: { warnings?: Array<{ message?: string }> } }).meta?.warnings ?? []).map((w) => w.message ?? '')).filter(Boolean);
        warnings.value = notes;
        router.reload({ only: ['recent'] });
    }
    if (failed.length > 0) {
        recordFailures(failed as Array<{ id: string; type: string; errors?: Record<string, string[]> }>, t('peer.collecte.refused_generic'));
        status.value = '';
    }
    void outbox.recount();
}
/** « Corriger et renvoyer » : la fiche refusée revient dans le formulaire, champs refusés signalés. */
async function correct(rejection: Rejection) {
    if (!rejection.values || !formEmpty.value) return;
    Object.assign(values, rejection.values);
    errors.value = { ...rejection.errors };
    dismiss(rejection.id);
    await focusSummary();
}
watch(() => outbox.lastFlush, onFlush);

onMounted(async () => {
    try {
        const draft = restoreDraft(await drafts.load(DRAFT_NAME), props.today);
        // Lecture IndexedDB plus lente que la saisie : un brouillon relu ne remplace jamais ce qui est déjà tapé.
        if (formEmpty.value) Object.assign(values, draft);
        hasDraft.value = !draftIsEmpty(values, props.today);
    } catch {
        // Pas de brouillon lisible : formulaire vide.
    }
    ready.value = true;
    void outbox.refresh().catch(() => undefined);
    // Passage de la boîte d'envoi tombé pendant une visite ailleurs : résultat (code attribué, refus) affiché ici.
    onFlush(outbox.lastFlush);
});
onBeforeUnmount(() => {
    if (saveTimer) clearTimeout(saveTimer);
});

const casesUrl = route('espace-pair.dossiers', undefined, '/espace-pair/dossiers');
const homeUrl = route('espace-pair', undefined, '/espace-pair');
const caseUrl = (code: string) => route('espace-pair.dossiers.show', { code }, `/espace-pair/dossiers/${code}`);
const place = (item: CaseItem) => item.commune?.name ?? item.zone?.name ?? '';
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 mb-0">{{ t('peer.collecte.heading') }}</h1>
                <p class="text-secondary mb-0">{{ t('peer.collecte.intro') }}</p>
            </div>
            <div class="small d-flex flex-wrap align-items-center gap-2">
                <XmBadge :color="ui.online ? 'success' : 'warning'" data-peer-online>{{ ui.online ? t('peer.status.online') : t('peer.status.offline') }}</XmBadge>
                <span data-peer-queue><i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>{{ t('peer.collecte.waiting', { count: queued }, queued) }}</span>
            </div>
        </div>

        <XmAlert type="warning" class="mb-0"><strong>{{ t('peer.collecte.no_identity_title') }}</strong> {{ t('peer.collecte.no_identity') }}</XmAlert>

        <div aria-live="polite" data-peer-status>
            <XmAlert v-if="status" type="success" class="mb-0">{{ status }}</XmAlert>
        </div>
        <XmAlert v-for="(message, index) in warnings" :key="`w${index}`" type="warning" class="mb-0">{{ message }}</XmAlert>
        <XmAlert v-for="rejection in rejections" :key="rejection.id" type="danger" :title="t('peer.collecte.refused_title')" class="mb-0" data-peer-refused>
            <ul class="mb-2"><li v-for="(message, index) in rejection.messages" :key="index">{{ message }}</li></ul>
            <p v-if="!rejection.values" class="small mb-2">{{ t('peer.collecte.refused_lost') }}</p>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <XmButton v-if="rejection.values" size="sm" variant="outline" icon="pencil" :disabled="!formEmpty" @click="correct(rejection)">{{ t('peer.collecte.refused_correct') }}</XmButton>
                <XmButton size="sm" variant="light" @click="dismiss(rejection.id)">{{ t('peer.collecte.refused_dismiss') }}</XmButton>
                <span v-if="rejection.values && !formEmpty" class="small">{{ t('peer.collecte.refused_blocked') }}</span>
            </div>
        </XmAlert>

        <form class="card card-body" novalidate data-peer-collecte @submit.prevent="submit">
            <div ref="summary"><XmErrorSummary :messages="errors" :autofocus="false" /></div>
            <div class="row g-3">
                <div class="col-md-4"><XmField v-model="values.kind" name="kind" type="select" :label="t('peer.collecte.fields.kind')" :options="kindOptions" :error="errors.kind" required /></div>
                <div class="col-md-4"><XmField v-model="values.occurred_on" name="occurred_on" type="date" :label="t('peer.collecte.fields.date')" :max="today" :error="errors.occurred_on" required /></div>
                <div class="col-md-4"><XmField v-model="values.violence_type_id" name="violence_type_id" type="select" :label="t('peer.collecte.fields.violence')" :options="violenceOptions" :error="errors.violence_type_id" required /></div>
                <div class="col-md-4"><XmField v-model="values.zone_id" name="zone_id" type="select" :label="t('peer.collecte.fields.zone')" :options="zoneOptions" :error="errors.zone_id" required /></div>
                <div class="col-md-4"><XmField v-model="values.commune_id" name="commune_id" type="select" optional :label="t('peer.collecte.fields.commune')" :options="communeOptions" :error="errors.commune_id" /></div>
                <div class="col-md-4"><XmField v-model="values.population_id" name="population_id" type="select" :label="t('peer.collecte.fields.population')" :options="populationOptions" :error="errors.population_id" required /></div>
                <div class="col-md-4"><XmField v-model="values.sex" name="sex" type="select" :label="t('peer.collecte.fields.sex')" :options="sexOptions" :error="errors.sex" required /></div>
                <div class="col-md-4"><XmField v-model="values.age_range" name="age_range" type="select" :label="t('peer.collecte.fields.age')" :options="ageOptions" :error="errors.age_range" required /></div>
                <div class="col-md-4"><XmField v-model="values.referral" name="referral" type="select" optional :label="t('peer.collecte.fields.referral')" :options="referralOptions" :error="errors.referral" /></div>
                <div class="col-12">
                    <XmField v-model="values.notes" name="notes" type="textarea" optional :rows="3" :label="t('peer.collecte.fields.notes')" :hint="t('peer.collecte.notes_hint', { min: options.notes_min_length })" :placeholder="t('peer.collecte.notes_placeholder')" :error="errors.notes" />
                </div>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="small text-secondary">
                    <i class="bi bi-shield-check me-1" aria-hidden="true"></i>{{ t('peer.collecte.footnote') }}
                    <template v-if="hasDraft"> {{ t('peer.collecte.draft_saved') }} <button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click="clearDraft(); resetForm()">{{ t('peer.collecte.draft_clear') }}</button></template>
                </div>
                <XmButton type="submit" icon="save" :loading="submitting">{{ t('peer.collecte.submit') }}</XmButton>
            </div>
        </form>

        <section class="card" aria-labelledby="peerRecent">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 id="peerRecent" class="h5 mb-0">{{ t('peer.collecte.recent') }}</h2>
                <span class="small"><Link :href="casesUrl">{{ t('peer.collecte.all_cases') }}</Link> · <Link :href="homeUrl">{{ t('peer.collecte.home') }}</Link></span>
            </div>
            <div class="xm-table-wrap">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th scope="col">{{ t('peer.cases.columns.code') }}</th><th scope="col">{{ t('peer.cases.columns.kind') }}</th><th scope="col">{{ t('peer.cases.columns.date') }}</th><th scope="col">{{ t('peer.cases.columns.place') }}</th><th scope="col">{{ t('peer.cases.columns.violence') }}</th><th scope="col">{{ t('peer.cases.columns.status') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="item in recent ?? []" :key="item.id">
                            <td class="fw-semibold"><Link :href="caseUrl(item.code)">{{ item.code }}</Link></td>
                            <td><XmBadge color="neutral">{{ item.kind }}</XmBadge></td>
                            <td>{{ item.occurred_on }}</td>
                            <td>{{ place(item) }}</td>
                            <td>{{ item.violence_type?.label }}</td>
                            <td><XmBadge :color="item.status_badge">{{ item.status_label }}</XmBadge></td>
                        </tr>
                        <tr v-if="(recent ?? []).length === 0"><td colspan="6" class="text-center text-secondary py-4">{{ t('peer.cases.empty_short') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
