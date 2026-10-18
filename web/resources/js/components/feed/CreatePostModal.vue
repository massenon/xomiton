<script setup lang="ts">
// Modale « Créer une publication » (lot fil-ecrans, P7-04 ; prototype partials/modals.php #modalCreateFeed), chargée à
// la demande par la carte « Créer une publication » (aucun import statique depuis app.ts ni le gabarit). Message,
// type de publication, visibilité (Communauté · Abonné·es · Moi seulement ; « Abonné·es » indisponible en anonyme,
// RG-02), photo facultative (JPG, PNG, WebP, 5 Mo, aperçu, description facultative), « Publier sous pseudonyme »,
// renvoi vers le signalement anonyme et SOS. Brouillon, envoi, hors connexion : feed/useComposer.ts.
// options (props `composer` de la page), focus ('photo' : champ photo mis en avant), recovered (refus au rejeu d'une
// publication mise en file, annoncé par la page) ; émet `published`, `hidden`.
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import { useSessionStore } from '@/stores/session';
import { DRAFT_POST, allowedVisibility, emptyPost, validatePost } from '@/feed/composer';
import { useComposer, type RecoveredEntry } from '@/feed/useComposer';
import { marks, textParts } from '@/feed/lazy';
import type { ComposerOptions, Post } from '@/feed/types';
import XmAlert from '@/components/XmAlert.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = withDefaults(defineProps<{ options: ComposerOptions; focus?: 'photo' | null; recovered?: RecoveredEntry | null }>(), { focus: null, recovered: null });
const emit = defineEmits<{ published: [post: Post]; hidden: [] }>();

const { t } = useI18n();
const session = useSessionStore();
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const { form, preview, errors, failure, busy, restored, waiting, setImage, submit } = useComposer({
    kind: 'post',
    recovered: props.recovered,
    // Brouillon propre à chaque groupe (P8-02) : celui du fil n'apparaît pas dans un groupe, et inversement.
    draft: props.options.group ? `${DRAFT_POST}:group:${props.options.group}` : DRAFT_POST,
    options: props.options,
    initial: () => emptyPost(props.options.tags[0]?.value ?? 'temoignage'),
    validate: (value, image) => validatePost(value, image, props.options.limits, t),
    onPublished: (post) => emit('published', post),
    close: () => modal.value?.close(),
});

const tags = computed(() => Object.fromEntries(props.options.tags.map((tag) => [tag.value, tag.label])));
const visibilities = computed(() => Object.fromEntries(props.options.visibilities
    .filter((option) => !(form.is_anonymous && option.value === 'followers'))
    .map((option) => [option.value, option.label])));
const maxMb = computed(() => Math.floor(props.options.limits.image_max_kb / 1024));
const accept = computed(() => props.options.limits.image_types.join(','));
const me = computed(() => session.user);
const help = computed(() => textParts(t('feed.create.help_links', marks(['report', 'sos'])), ['report', 'sos']));
const helpLinks: Record<string, { href: string; label: string }> = {
    report: { href: route('signaler', undefined, '/signaler'), label: 'feed.create.help_report' },
    sos: { href: route('sos', undefined, '/sos'), label: 'feed.create.help_sos' },
};

// Anonyme + abonné·es refusé par l'API (422) : la visibilité revient à « Communauté ».
watch(() => form.is_anonymous, (anonymous) => { form.visibility = allowedVisibility(form.visibility, anonymous); });

function onFile(event: Event) {
    const input = event.target as HTMLInputElement;
    setImage(input.files?.[0] ?? null);
    if (!input.files?.[0] || errors.value.image) input.value = '';
}

function clearPhoto() {
    setImage(null);
    if (fileInput.value) fileInput.value.value = '';
    form.image_alt = '';
}

async function onSubmit() {
    const ok = await submit();
    if (!ok) await nextTick(() => document.querySelector<HTMLElement>('#modalCreateFeed [aria-invalid="true"], #modalCreateFeed .xm-error-summary')?.focus());
}

onMounted(() => {
    if (props.focus === 'photo') nextTick(() => fileInput.value?.focus());
});
</script>

<template>
    <XmModal ref="modal" id="modalCreateFeed" :title="t('feed.create.title')" type="form" form :open="true" :action="t('feed.create.submit')" :busy="busy" @submit="onSubmit" @hidden="emit('hidden')">
        <XmAlert v-if="waiting" type="info" class="small">{{ t('feed.offline.pending') }}</XmAlert>
        <XmAlert v-else-if="restored" type="info" class="small">{{ t('feed.create.draft_restored') }}</XmAlert>
        <XmErrorSummary :messages="errors" :title="t('feed.create.errors_title')" />
        <XmAlert v-if="failure" type="danger" class="small">{{ failure }}</XmAlert>
        <div class="d-flex mb-3">
            <XmAvatar class="me-2" size="xs" :name="me?.pseudo ?? ''" :src="me?.avatar_url" />
            <div class="w-100">
                <label class="visually-hidden" for="f-body">{{ t('feed.create.text') }}</label>
                <textarea id="f-body" v-model="form.body" class="form-control pe-4 fs-5 lh-1 border-0" name="body" rows="4" :maxlength="options.limits.body_max" :placeholder="t('feed.create.text_placeholder')" :aria-invalid="errors.body ? 'true' : undefined" :aria-describedby="errors.body ? 'f-body-error' : undefined"></textarea>
                <div v-if="errors.body" id="f-body-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.body }}</div>
            </div>
        </div>
        <div class="row g-2 mb-2">
            <div class="col-sm-6">
                <XmField v-model="form.tag" name="tag" type="select" :label="t('feed.create.tag')" :options="tags" :error="errors.tag" />
            </div>
            <div class="col-sm-6">
                <XmField v-model="form.visibility" name="visibility" type="select" :label="t('feed.create.visibility')" :options="visibilities" :hint="form.is_anonymous ? t('feed.create.anonymous_followers') : null" :error="errors.visibility" />
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label small mb-1" for="f-image"><i class="bi bi-image-fill text-success me-1" aria-hidden="true"></i>{{ t('feed.create.photo') }}</label>
            <input id="f-image" ref="fileInput" class="form-control form-control-sm" type="file" name="image" :accept="accept" aria-describedby="f-image-hint" :aria-invalid="errors.image ? 'true' : undefined" @change="onFile">
            <div id="f-image-hint" class="form-text">{{ t('feed.create.photo_help', { max: maxMb }) }}</div>
            <div v-if="errors.image" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.image }}</div>
            <div v-if="preview" class="mt-2">
                <img :src="preview" class="img-fluid rounded xm-poll-preview" :alt="t('feed.create.photo_preview_alt')">
                <div><button type="button" class="btn btn-link btn-sm px-0" @click="clearPhoto"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ t('feed.create.photo_remove') }}</button></div>
                <XmField v-model="form.image_alt" name="image_alt" optional :maxlength="255" :label="t('feed.create.image_alt')" :hint="t('feed.create.image_alt_help')" :error="errors.image_alt" />
            </div>
        </div>
        <XmField v-model="form.is_anonymous" name="is_anonymous" type="switch" :label="t('feed.create.anonymous')" :hint="form.is_anonymous ? t('feed.create.anonymous_help') : null" />
        <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
            <template v-for="(part, index) in help" :key="index"><a v-if="'slot' in part" class="alert-link" :href="helpLinks[part.slot].href">{{ t(helpLinks[part.slot].label) }}</a><template v-else>{{ part.text }}</template></template>
        </div>
    </XmModal>
</template>
