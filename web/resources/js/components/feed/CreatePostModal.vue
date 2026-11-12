<script setup lang="ts">
// Modale « Créer une publication » (lot fil-ecrans, P7-04 ; prototype partials/modals.php #modalCreateFeed), chargée à
// la demande par la carte « Créer une publication » (aucun import statique depuis app.ts ni le gabarit). Message,
// type de publication, visibilité (Communauté · Abonné·es · Moi seulement ; « Abonné·es » indisponible en anonyme,
// RG-02), plusieurs photos facultatives (<ComposerImages> : aperçu, texte alternatif par photo, retrait, Monter /
// Descendre) **ou** une vidéo (<ComposerVideo> : description obligatoire, avertissement de vie privée, progression,
// annulation ; relue avant parution, P7-10 / Q96) — jamais les deux —, « Publier sous pseudonyme », renvoi vers le
// signalement anonyme et SOS. Brouillon, envoi, hors connexion : feed/useComposer.ts (texte seul en file d'attente ;
// avec un média, le réseau est exigé et le brouillon reste).
// options (props `composer` de la page), focus ('photo' ou 'video' : champ mis en avant), recovered (refus au rejeu d'une
// publication mise en file, annoncé par la page) ; émet `published` (201), `submitted` (202 : vidéo en relecture, jamais
// ajoutée au fil), `hidden`.
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import { useSessionStore } from '@/stores/session';
import { DRAFT_POST, allowedVisibility, emptyPost, mediaLimits, validatePost } from '@/feed/composer';
import { useComposer, type RecoveredEntry } from '@/feed/useComposer';
import { marks, textParts } from '@/feed/lazy';
import type { ComposerOptions, Post } from '@/feed/types';
import XmAlert from '@/components/XmAlert.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmEmojiPicker from '@/components/XmEmojiPicker.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import ComposerImages from './ComposerImages.vue';
import ComposerVideo from './ComposerVideo.vue';
import XmField from '@/components/XmField.vue';
import XmModal from '@/components/XmModal.vue';

const props = withDefaults(defineProps<{ options: ComposerOptions; focus?: 'photo' | 'video' | null; recovered?: RecoveredEntry | null }>(), { focus: null, recovered: null });
const emit = defineEmits<{ published: [post: Post]; submitted: [post: Post]; hidden: [] }>();

const { t } = useI18n();
const session = useSessionStore();
const modal = ref<InstanceType<typeof XmModal> | null>(null);
const imagesBlock = ref<InstanceType<typeof ComposerImages> | null>(null);
const videoBlock = ref<InstanceType<typeof ComposerVideo> | null>(null);

const {
    form, images, video, progress, progressText, announcement, cancelled, errors, failure, busy, restored, waiting,
    addImages, removeImage, moveImage, setAlt, setVideo, cancel, submit,
} = useComposer({
    kind: 'post',
    recovered: props.recovered,
    // Brouillon propre à chaque groupe (P8-02) : celui du fil n'apparaît pas dans un groupe, et inversement.
    draft: props.options.group ? `${DRAFT_POST}:group:${props.options.group}` : DRAFT_POST,
    options: props.options,
    initial: () => emptyPost(props.options.tags[0]?.value ?? 'temoignage'),
    validate: (value, media) => validatePost(value, media, props.options.limits, t),
    onPublished: (post) => emit('published', post),
    onSubmitted: (post) => emit('submitted', post),
    close: () => modal.value?.close(),
});

const tags = computed(() => Object.fromEntries(props.options.tags.map((tag) => [tag.value, tag.label])));
const visibilities = computed(() => Object.fromEntries(props.options.visibilities
    .filter((option) => !(form.is_anonymous && option.value === 'followers'))
    .map((option) => [option.value, option.label])));
const media = computed(() => mediaLimits(props.options.limits));
const maxMb = computed(() => Math.floor(props.options.limits.image_max_kb / 1024));
const accept = computed(() => props.options.limits.image_types.join(','));
const videoAccept = computed(() => media.value.videoTypes.join(','));
const videoMb = computed(() => Math.floor(media.value.videoMaxKb / 1024));
const me = computed(() => session.user);
const help = computed(() => textParts(t('feed.create.help_links', marks(['report', 'sos'])), ['report', 'sos']));
const helpLinks: Record<string, { href: string; label: string }> = {
    report: { href: route('signaler', undefined, '/signaler'), label: 'feed.create.help_report' },
    sos: { href: route('sos', undefined, '/sos'), label: 'feed.create.help_sos' },
};

// Anonyme + abonné·es refusé par l'API (422) : la visibilité revient à « Communauté ».
watch(() => form.is_anonymous, (anonymous) => { form.visibility = allowedVisibility(form.visibility, anonymous); });

async function onSubmit() {
    const ok = await submit();
    if (!ok) await nextTick(() => document.querySelector<HTMLElement>('#modalCreateFeed [aria-invalid="true"], #modalCreateFeed .xm-error-summary')?.focus());
}

onMounted(() => {
    if (props.focus === 'photo') nextTick(() => imagesBlock.value?.focus());
    else if (props.focus === 'video') nextTick(() => videoBlock.value?.focus());
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
                <XmEmojiPicker target="#f-body" :maxlength="options.limits.body_max" />
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
        <p class="form-text mb-2" id="f-media-exclusive">{{ t('feed.media.exclusive') }}</p>
        <ComposerImages ref="imagesBlock" :images="images" :max="media.maxImages" :accept="accept" :max-mb="maxMb" :errors="errors" :locked="video !== null" :announcement="announcement" @add="addImages" @remove="removeImage" @move="moveImage" @alt="setAlt" />
        <ComposerVideo ref="videoBlock" :video="video" :accept="videoAccept" :max-mb="videoMb" :description-min="media.descriptionMin" :description-max="media.descriptionMax" :errors="errors" :locked="images.length > 0" :progress="progress" :progress-text="progressText" :busy="busy" :cancelled="cancelled" @pick="setVideo" @description="(value) => video && (video.description = value)" @acknowledge="(value) => video && (video.acknowledged = value)" @cancel="cancel" />
        <XmField v-model="form.is_anonymous" name="is_anonymous" type="switch" :label="t('feed.create.anonymous')" :hint="form.is_anonymous ? t('feed.create.anonymous_help') : null" />
        <div class="alert alert-warning small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>
            <template v-for="(part, index) in help" :key="index"><a v-if="'slot' in part" class="alert-link" :href="helpLinks[part.slot].href">{{ t(helpLinks[part.slot].label) }}</a><template v-else>{{ part.text }}</template></template>
        </div>
    </XmModal>
</template>
