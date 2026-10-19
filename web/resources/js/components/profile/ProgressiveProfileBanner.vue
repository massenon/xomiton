<script setup lang="ts">
// Bandeau du profil progressif (lot profil, P8-08 ; authentification.md l.9, LOGIN_INSCRPTION_MULTISTEP_USER_INSTRUCTION
// §7, UX_GUIDELINES §4) : « Complète ton profil, c'est facultatif », **un groupe de questions à la fois** (pronoms, type
// de membre, statut allié·e / personne ressource Q10, identité de genre, orientation, âge, ville, centres d'intérêt),
// « Pourquoi on demande ça ? », « Je préfère ne pas le dire » partout, « Passer ce groupe » (repoussé à la session
// suivante), « Plus tard » (bandeau masqué pour la session). Identité de genre et orientation : case de consentement
// `sensitive_data` (jamais pré-cochée) tant qu'il n'est pas en cours, et « Afficher sur mon profil » décochée par défaut
// — rien de sensible n'apparaît sur le profil sans ce choix explicite. Ville : interrupteur « Afficher ma zone sur mon
// profil » (valeur actuelle, explicite ; les autres ne voient que le département, Q80). Envoi : PATCH me/profile (docs/API.md §3),
// le serveur renvoie le groupe suivant. Chargé à la demande par la page (lazyComponent), jamais dans app.ts.
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import type { SelfProfile } from '@/profile/api';
import type { ProgressiveGroup, ProgressiveProps, ProgressiveState, ProgressiveValues } from '@/profile/types';

const props = defineProps<{ progressive: ProgressiveProps }>();
const emit = defineEmits<{
    (event: 'updated', profile: SelfProfile): void;
    (event: 'dismiss'): void;
}>();

const SKIPPED_KEY = 'xm_profile_skipped';
const PREFER_NOT = 'prefer_not';
const PRONOUNS = ['elle', 'il', 'iel', 'other', PREFER_NOT] as const;
const ALLY = [['aucun', 'none'], ['allie', 'ally'], ['ressource', 'resource']] as const;
const GENDER_IDENTITIES = ['femme_cis', 'femme_trans', 'homme_cis', 'homme_trans', 'non_binaire', 'intersexe', 'questionnement', 'autre', PREFER_NOT];
const ORIENTATIONS = ['lesbienne', 'bisexuelle', 'pansexuelle', 'queer', 'asexuelle', 'heterosexuelle', 'questionnement', 'autre', PREFER_NOT];
const SENSITIVE: ProgressiveGroup[] = ['gender_identity', 'orientation'];
/** Groupe → clé de lang/fr/profile.php `progressive.groups.*` (l'âge s'appelle `age`, la ville `location`). */
const LANG: Record<ProgressiveGroup, string> = {
    pronouns: 'pronouns', member_type: 'member_type', ally_status: 'ally_status', gender_identity: 'gender_identity',
    orientation: 'orientation', age: 'age', location: 'location', interests: 'interests',
};

const { t } = useI18n();
const toast = useToast();
// Appel direct (même adresse que profileApi().updateProfile) : le bandeau, chargé à la demande, n'importe rien du
// morceau de la page (sinon la page perd sa façade dans le manifeste Vite et sort du contrôle de budget).
const api = useApi();
const updateProfile = async (body: Record<string, unknown>): Promise<SelfProfile> =>
    (await api.patch<{ data: { profile: SelfProfile } }>('/api/v1/me/profile', body, { silent: true })).data.data.profile;

const state = ref<ProgressiveState>({ ...props.progressive.state });
const values = ref<ProgressiveValues>({ ...props.progressive.values });
const skipped = ref<string[]>(readSkipped());
const saving = ref(false);
const error = ref('');
const why = ref(false);
const announce = ref('');
const finished = ref(false);
const legend = ref<HTMLElement | null>(null);

const form = reactive({
    choice: '' as string,
    other: '',
    consent: false,
    share: false,
    zone: null as number | null,
    commune: null as number | null,
    showZone: true,
    interests: [] as string[],
});

function readSkipped(): string[] {
    try {
        const raw = window.sessionStorage.getItem(SKIPPED_KEY);
        const list = raw ? JSON.parse(raw) : [];
        return Array.isArray(list) ? list.filter((item) => typeof item === 'string') : [];
    } catch {
        return [];
    }
}

function writeSkipped(list: string[]) {
    try {
        window.sessionStorage.setItem(SKIPPED_KEY, JSON.stringify(list));
    } catch {
        // Stockage indisponible (navigation privée) : le groupe sera simplement reproposé.
    }
}

const pending = computed(() => state.value.groups.filter((group) => !group.answered && !skipped.value.includes(group.key)));
const current = computed<ProgressiveGroup | null>(() => pending.value[0]?.key ?? null);
const position = computed(() => (current.value ? state.value.groups.findIndex((group) => group.key === current.value) + 1 : state.value.total));
const sensitive = computed(() => current.value !== null && SENSITIVE.includes(current.value));
const needsConsent = computed(() => sensitive.value && !state.value.sensitive_consent && form.choice !== PREFER_NOT && form.choice !== '');
const langKey = computed(() => (current.value ? LANG[current.value] : 'pronouns'));
const communes = computed(() => props.progressive.options.zones.find((zone) => zone.id === form.zone)?.communes ?? []);
const percent = computed(() => Math.round((state.value.answered * 100) / Math.max(1, state.value.total)));

const choices = computed<{ value: string; label: string }[]>(() => {
    switch (current.value) {
    case 'pronouns':
        return PRONOUNS.map((key) => ({ value: key, label: t(`profile.progressive.groups.pronouns.options.${key}`) }));
    case 'member_type':
        return [...props.progressive.options.member_types, { value: PREFER_NOT, label: t('profile.progressive.prefer_not') }];
    case 'ally_status':
        return ALLY.map(([value, key]) => ({ value, label: t(`profile.progressive.groups.ally_status.options.${key}`) }));
    case 'gender_identity':
        return GENDER_IDENTITIES.map((key) => ({ value: key, label: t(`profile.progressive.groups.gender_identity.options.${key}`) }));
    case 'orientation':
        return ORIENTATIONS.map((key) => ({ value: key, label: t(`profile.progressive.groups.orientation.options.${key}`) }));
    case 'age':
        return [...props.progressive.options.age_ranges, { value: PREFER_NOT, label: t('profile.progressive.prefer_not') }];
    default:
        return [];
    }
});

/** Formulaire du groupe ouvert, prérempli de la valeur enregistrée. */
function reset() {
    error.value = '';
    why.value = false;
    form.other = '';
    form.consent = false;
    const v = values.value;
    switch (current.value) {
    case 'pronouns': {
        const known = PRONOUNS.slice(0, 3).find((key) => t(`profile.progressive.groups.pronouns.options.${key}`).toLowerCase() === (v.pronouns ?? '').toLowerCase());
        form.choice = v.pronouns ? (known ?? 'other') : '';
        form.other = v.pronouns && !known ? v.pronouns : '';
        break;
    }
    case 'member_type': form.choice = v.member_type ?? ''; break;
    case 'ally_status': form.choice = v.ally_status ?? ''; break;
    case 'gender_identity': form.choice = v.gender_identity ?? ''; break;
    case 'orientation': form.choice = v.orientation ?? ''; break;
    case 'age': form.choice = v.age_range ?? ''; break;
    default: form.choice = '';
    }
    form.share = current.value !== null && v.shared_fields.includes(current.value);
    form.zone = v.zone_id;
    form.commune = v.commune_id;
    form.showZone = v.show_zone;
    form.interests = [...v.interests];
}

watch(current, () => reset(), { immediate: true });
watch(() => form.zone, (zone, previous) => { if (previous !== undefined && zone !== previous) form.commune = null; });

/** Corps du PATCH me/profile pour le groupe ouvert, ou un message d'erreur. */
function payload(): Record<string, unknown> | string {
    const group = current.value;
    if (group === null) return t('profile.progressive.choose');
    if (group === 'location') {
        if (form.choice === PREFER_NOT) return { answered_groups: ['location'] };
        return form.zone === null ? t('profile.progressive.choose') : { zone_id: form.zone, commune_id: form.commune, show_zone: form.showZone };
    }
    if (group === 'interests') return form.choice === PREFER_NOT ? { answered_groups: ['interests'] } : { interests: form.interests };
    if (form.choice === '') return t('profile.progressive.choose');
    if (form.choice === PREFER_NOT && group === 'member_type') return { answered_groups: ['member_type'] };
    switch (group) {
    case 'pronouns':
        if (form.choice === 'other') return form.other.trim() === '' ? t('profile.progressive.choose') : { pronouns: form.other.trim().slice(0, 30) };
        return { pronouns: form.choice === PREFER_NOT ? PREFER_NOT : t(`profile.progressive.groups.pronouns.options.${form.choice}`) };
    case 'member_type': return { member_type: form.choice };
    case 'ally_status': return { ally_status: form.choice };
    case 'age': return { age_range: form.choice };
    default: {
        if (form.choice === PREFER_NOT) return { [group]: PREFER_NOT };
        if (needsConsent.value && !form.consent) return t('profile.progressive.sensitive_required');
        const shared = values.value.shared_fields.filter((field) => field !== group);
        return {
            ...(needsConsent.value ? { sensitive_consent: true } : {}),
            [group]: form.choice,
            shared_fields: form.share ? [...shared, group] : shared,
        };
    }
    }
}

async function focusLegend() {
    await nextTick();
    legend.value?.focus();
}

async function save(event: Event) {
    event.preventDefault();
    if (saving.value) return;
    const body = payload();
    if (typeof body === 'string') {
        error.value = body;
        return;
    }
    saving.value = true;
    error.value = '';
    try {
        const profile = await updateProfile(body);
        values.value = {
            ...values.value,
            pronouns: profile.pronouns, member_type: profile.member_type, ally_status: profile.ally_status,
            gender_identity: profile.gender_identity, orientation: profile.orientation, age_range: profile.age_range,
            interests: profile.interests, shared_fields: profile.shared_fields,
            zone_id: typeof body.zone_id === 'number' ? body.zone_id : values.value.zone_id,
            commune_id: 'commune_id' in body ? (body.commune_id as number | null) : values.value.commune_id,
            show_zone: profile.show_zone ?? values.value.show_zone,
        };
        state.value = profile.progressive;
        emit('updated', profile);
        announce.value = t('profile.progressive.saved');
        toast.show(t('profile.progressive.saved'), 'success');
        // Plus rien à proposer pendant cette session (dernier groupe répondu, les autres passés) : état de fin, jamais
        // une carte vide.
        if (current.value === null) finished.value = true;
        else focusLegend();
    } catch (cause) {
        const first = cause instanceof ApiError ? Object.values(cause.errors)[0]?.[0] : undefined;
        error.value = first ?? t('profile.progressive.error');
    } finally {
        saving.value = false;
    }
}

function skip() {
    if (!current.value) return;
    skipped.value = [...skipped.value, current.value];
    writeSkipped(skipped.value);
    announce.value = t('profile.progressive.skipped');
    if (current.value === null) emit('dismiss');
    else focusLegend();
}
</script>

<template>
    <section class="card card-body xm-profile-banner" aria-labelledby="progressiveTitle">
        <template v-if="finished">
            <h2 id="progressiveTitle" class="h5 mb-1"><i class="bi bi-check2-circle text-success me-1" aria-hidden="true"></i>{{ t('profile.progressive.done_title') }}</h2>
            <p class="small text-secondary mb-2">{{ t('profile.progressive.done_text') }}</p>
            <div><button class="btn btn-sm btn-light" type="button" @click="emit('dismiss')">{{ t('common.close') }}</button></div>
        </template>
        <template v-else-if="current">
            <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                    <h2 id="progressiveTitle" class="h5 mb-1">{{ t('profile.progressive.banner_title') }}</h2>
                    <p class="small text-secondary mb-2">{{ t('profile.progressive.banner_text') }}</p>
                </div>
                <button class="btn btn-sm btn-link text-secondary text-nowrap" type="button" @click="emit('dismiss')">{{ t('profile.progressive.banner_dismiss') }}</button>
            </div>
            <div class="progress xm-profile-progress mb-3" role="progressbar" :aria-label="t('profile.progressive.banner_progress', { percent })" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" :style="{ width: `${percent}%` }"></div>
            </div>

            <form novalidate @submit="save">
                <fieldset :aria-describedby="error ? 'progressiveError' : undefined">
                    <legend ref="legend" class="h6 mb-1" tabindex="-1">
                        <span class="d-block small text-secondary fw-normal">{{ t('profile.progressive.step', { current: position, total: state.total }) }}</span>
                        {{ t(`profile.progressive.groups.${langKey}.title`) }}
                    </legend>
                    <button class="btn btn-link btn-sm px-0 mb-2" type="button" :aria-expanded="why ? 'true' : 'false'" aria-controls="progressiveWhy" @click="why = !why">
                        <i class="bi bi-question-circle me-1" aria-hidden="true"></i>{{ t('profile.progressive.why') }}
                    </button>
                    <p v-show="why" id="progressiveWhy" class="small text-secondary xm-profile-why">{{ t(`profile.progressive.groups.${langKey}.why`) }}</p>

                    <div v-if="choices.length" class="vstack gap-1">
                        <div v-for="option in choices" :key="option.value" class="form-check">
                            <input :id="`progressive-${current}-${option.value}`" v-model="form.choice" class="form-check-input" type="radio" :name="`progressive-${current}`" :value="option.value">
                            <label class="form-check-label" :for="`progressive-${current}-${option.value}`">{{ option.label }}</label>
                        </div>
                    </div>
                    <div v-if="current === 'pronouns' && form.choice === 'other'" class="mt-2">
                        <label class="visually-hidden" for="progressive-pronouns-other">{{ t('profile.progressive.groups.pronouns.other_placeholder') }}</label>
                        <input id="progressive-pronouns-other" v-model="form.other" class="form-control form-control-sm" type="text" maxlength="30" :placeholder="t('profile.progressive.groups.pronouns.other_placeholder')">
                    </div>
                    <p v-if="current === 'member_type' && form.choice === 'pair'" class="small text-secondary mt-2 mb-0">{{ t('auth.register.profile.member_type_hint') }}</p>

                    <template v-if="current === 'location'">
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <label class="form-label small" for="progressive-zone">{{ t('profile.progressive.groups.location.zone') }}</label>
                                <select id="progressive-zone" v-model="form.zone" class="form-select form-select-sm" :disabled="form.choice === 'prefer_not'">
                                    <option :value="null">{{ t('profile.progressive.none') }}</option>
                                    <option v-for="zone in progressive.options.zones" :key="zone.id" :value="zone.id">{{ zone.name }}</option>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label small" for="progressive-commune">{{ t('profile.progressive.groups.location.commune') }}</label>
                                <select id="progressive-commune" v-model="form.commune" class="form-select form-select-sm" :disabled="form.zone === null || form.choice === 'prefer_not'">
                                    <option :value="null">{{ t('profile.progressive.none') }}</option>
                                    <option v-for="commune in communes" :key="commune.id" :value="commune.id">{{ commune.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input id="progressive-show-zone" v-model="form.showZone" class="form-check-input" type="checkbox" role="switch" aria-describedby="progressive-show-zone-help" :disabled="form.choice === 'prefer_not'">
                            <label class="form-check-label small" for="progressive-show-zone">{{ t('profile.progressive.groups.location.show_zone') }}</label>
                            <div id="progressive-show-zone-help" class="form-text">{{ t('profile.progressive.groups.location.show_zone_help') }}</div>
                        </div>
                        <div class="form-check mt-2">
                            <input id="progressive-location-prefer" class="form-check-input" type="checkbox" :checked="form.choice === 'prefer_not'" @change="form.choice = ($event.target as HTMLInputElement).checked ? 'prefer_not' : ''">
                            <label class="form-check-label" for="progressive-location-prefer">{{ t('profile.progressive.prefer_not') }}</label>
                        </div>
                    </template>

                    <template v-if="current === 'interests'">
                        <p class="small text-secondary mb-2">{{ t('profile.progressive.groups.interests.hint') }}</p>
                        <div class="d-flex flex-wrap gap-3">
                            <div v-for="interest in progressive.options.interests" :key="interest.slug" class="form-check">
                                <input :id="`progressive-interest-${interest.slug}`" v-model="form.interests" class="form-check-input" type="checkbox" :value="interest.slug" :disabled="form.choice === 'prefer_not'">
                                <label class="form-check-label" :for="`progressive-interest-${interest.slug}`">{{ interest.label }}</label>
                            </div>
                        </div>
                        <div class="form-check mt-2">
                            <input id="progressive-interests-prefer" class="form-check-input" type="checkbox" :checked="form.choice === 'prefer_not'" @change="form.choice = ($event.target as HTMLInputElement).checked ? 'prefer_not' : ''">
                            <label class="form-check-label" for="progressive-interests-prefer">{{ t('profile.progressive.prefer_not') }}</label>
                        </div>
                    </template>

                    <template v-if="sensitive && form.choice !== 'prefer_not' && form.choice !== ''">
                        <div v-if="needsConsent" class="form-check mt-3">
                            <input id="progressive-consent" v-model="form.consent" class="form-check-input" type="checkbox">
                            <label class="form-check-label small" for="progressive-consent">{{ t('profile.progressive.sensitive_consent') }}</label>
                        </div>
                        <div class="form-check form-switch mt-2">
                            <input id="progressive-share" v-model="form.share" class="form-check-input" type="checkbox" role="switch" aria-describedby="progressive-share-help">
                            <label class="form-check-label small" for="progressive-share">{{ t('profile.progressive.share') }}</label>
                            <div id="progressive-share-help" class="form-text">{{ t('profile.progressive.share_help') }}</div>
                        </div>
                    </template>

                    <p v-if="error" id="progressiveError" class="text-danger small mt-2 mb-0" role="alert">{{ error }}</p>
                </fieldset>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button class="btn btn-primary btn-sm" type="submit" :disabled="saving" :aria-busy="saving ? 'true' : undefined">{{ t('profile.progressive.save_group') }}</button>
                    <button class="btn btn-light btn-sm" type="button" :disabled="saving" @click="skip">{{ t('profile.progressive.skip_group') }}</button>
                </div>
            </form>
        </template>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </section>
</template>
