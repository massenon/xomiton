<script setup lang="ts">
// Bloc « Vidéo » de la modale « Créer une publication » (P7-10, Q96 ; docs/API.md §8.7) : choix d'un fichier mp4 ou
// webm (poids contrôlé avant l'envoi), **description obligatoire** (5 à 500 caractères, devient l'alternative textuelle),
// **avertissement de vie privée** (`feed.video.privacy_notice` : la vidéo n'est pas retouchée, ses métadonnées restent)
// affiché avant l'envoi avec une case de prise de connaissance obligatoire, relecture annoncée (`review_notice`),
// progression d'envoi (barre + texte annoncé par paliers dans une région polie) et bouton Annuler. Exclusif des
// photos (champ désactivé et expliqué). Aucune lecture automatique. Chargé avec la modale (jamais dans app.ts).
// Props : video, accept, maxMb, descriptionMin / Max, errors, locked, progress, progressText, busy, cancelled.
// Émet pick(fichier | null), description(texte), acknowledge(booléen), cancel.
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { length, megabytes, type VideoItem } from '@/feed/composer';

const props = withDefaults(defineProps<{
    video: VideoItem | null;
    accept: string;
    maxMb: number;
    descriptionMin: number;
    descriptionMax: number;
    errors?: Record<string, string>;
    locked?: boolean;
    progress?: number | null;
    progressText?: string;
    busy?: boolean;
    cancelled?: boolean;
}>(), { errors: () => ({}), locked: false, progress: null, progressText: '', busy: false, cancelled: false });

const emit = defineEmits<{ pick: [file: File | null]; description: [value: string]; acknowledge: [value: boolean]; cancel: [] }>();

const { t } = useI18n();
const input = ref<HTMLInputElement | null>(null);
const count = computed(() => length(props.video?.description ?? ''));

function onChange(event: Event) {
    const field = event.target as HTMLInputElement;
    const file = field.files?.[0] ?? null;
    // Un fichier refusé ne reste pas dans le champ ; un fichier accepté est gardé par le composeur (champ vidé).
    field.value = '';
    emit('pick', file);
}

function clear() {
    emit('pick', null);
    input.value?.focus();
}

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div id="f-video-block" class="mb-2" data-xm-video>
        <label class="form-label small mb-1" for="f-video"><i class="bi bi-camera-video-fill text-primary me-1" aria-hidden="true"></i>{{ t('feed.video.legend') }}</label>
        <input id="f-video" ref="input" class="form-control form-control-sm" type="file" name="video" :accept="accept" :disabled="locked || busy" aria-describedby="f-video-hint" :aria-invalid="errors.video ? 'true' : undefined" @change="onChange">
        <div id="f-video-hint" class="form-text">{{ locked ? t('feed.media.video_locked') : t('feed.video.help', { max: maxMb }) }}</div>
        <div v-if="errors.video" id="f-video-error" class="invalid-feedback d-block" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.video }}</div>

        <div v-if="video" class="card card-body p-2 mt-2" data-xm-video-card>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <p class="small mb-0 min-w-0 text-break"><i class="bi bi-file-earmark-play me-1" aria-hidden="true"></i>{{ t('feed.video.selected', { name: video.file.name, size: megabytes(video.file.size) }) }}</p>
                <button type="button" class="btn btn-light btn-sm text-danger xm-media-btn" :disabled="busy" @click="clear"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ t('feed.video.remove') }}</button>
            </div>

            <div class="mb-2">
                <label class="form-label small mb-1" for="f-video_description">{{ t('feed.video.description') }} <span aria-hidden="true">*</span><span class="visually-hidden">{{ t('microcopy.a11y.required') }}</span></label>
                <textarea id="f-video_description" class="form-control form-control-sm" name="video_description" rows="3" required aria-required="true" :maxlength="descriptionMax" :value="video.description" :disabled="busy" :aria-invalid="errors.video_description ? 'true' : undefined" aria-describedby="f-video_description-hint f-video_description-count" @input="emit('description', ($event.target as HTMLTextAreaElement).value)"></textarea>
                <div id="f-video_description-hint" class="form-text">{{ t('feed.video.description_help', { min: descriptionMin, max: descriptionMax }) }}</div>
                <div id="f-video_description-count" class="form-text text-end">{{ t('feed.media.images_counter', { count, max: descriptionMax }) }}</div>
                <div v-if="errors.video_description" id="f-video_description-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.video_description }}</div>
            </div>

            <div class="alert alert-warning small mb-2" role="note" aria-labelledby="f-video-privacy-title" data-xm-video-privacy>
                <p id="f-video-privacy-title" class="fw-bold mb-1"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>{{ t('feed.video.privacy_title') }}</p>
                <p class="mb-1">{{ t('feed.video.privacy_notice') }}</p>
                <p class="mb-0">{{ t('feed.video.review_notice') }}</p>
            </div>
            <div class="form-check">
                <input id="f-video_ack" class="form-check-input" type="checkbox" name="video_ack" required aria-required="true" :checked="video.acknowledged" :disabled="busy" :aria-invalid="errors.video_ack ? 'true' : undefined" :aria-describedby="errors.video_ack ? 'f-video_ack-error' : undefined" @change="emit('acknowledge', ($event.target as HTMLInputElement).checked)">
                <label class="form-check-label small" for="f-video_ack">{{ t('feed.video.acknowledge') }}</label>
            </div>
            <div v-if="errors.video_ack" id="f-video_ack-error" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.video_ack }}</div>
        </div>

        <div v-if="progress !== null" class="mt-2" data-xm-video-progress>
            <p class="small mb-1" aria-hidden="true">{{ t('feed.video.progress', { percent: progress }) }}</p>
            <div class="progress" role="progressbar" :aria-label="t('feed.video.progress_label')" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress"><div class="progress-bar" :style="{ width: `${progress}%` }"></div></div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2 xm-media-btn" data-xm-video-cancel @click="emit('cancel')"><i class="bi bi-x-circle me-1" aria-hidden="true"></i>{{ t('feed.video.cancel') }}</button>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite" data-xm-video-live>{{ progressText }}</p>
        <p v-if="cancelled" class="alert alert-info small mt-2 mb-0" role="status">{{ t('feed.video.cancelled') }}</p>
    </div>
</template>
