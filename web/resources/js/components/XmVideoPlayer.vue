<script setup lang="ts">
// Lecteur vidéo d'une publication (P7-10, Q96 ; docs/API.md §8.7) : <video> natif avec contrôles du navigateur,
// `preload="metadata"` (rien n'est téléchargé avant le clic), `playsinline` (iOS), **jamais de lecture automatique** ni
// de son coupé forcé. Source : lien signé court propre à la personne qui lit (`media.url`, plages `Range` côté serveur).
// La description (`media.alt`) sert d'`aria-label` du lecteur ET de légende visible ; si le fichier n'est pas lisible
// (lien expiré, format non pris en charge), un texte remplace le lecteur et la description reste lisible. Chargé à la
// demande par <XmMediaGallery> (jamais dans app.ts). Props : media (PostMedia de type `video`).
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { PostMedia } from '@/feed/types';

const props = defineProps<{ media: PostMedia }>();

const { t } = useI18n();
const failed = ref(false);
const description = computed(() => (props.media.alt ?? '').trim());
</script>

<template>
    <figure class="xm-video mb-0" data-xm-video-player>
        <video v-if="!failed" class="xm-video-el" controls preload="metadata" playsinline :src="media.url" :aria-label="description || t('feed.video.player_label')" :width="media.width ?? undefined" :height="media.height ?? undefined" @error="failed = true">{{ t('feed.video.player_fallback') }}</video>
        <p v-else class="alert alert-warning small mb-2" role="status" data-xm-video-failed><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i>{{ t('feed.video.unplayable') }}</p>
        <figcaption v-if="description" class="small text-secondary mt-1" :aria-hidden="failed ? undefined : 'true'">{{ description }}</figcaption>
    </figure>
</template>
