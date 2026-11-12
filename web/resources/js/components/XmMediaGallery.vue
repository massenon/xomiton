<script setup lang="ts">
// Médias d'une publication (P7-10, Q96 ; docs/API.md §8.4 `media[]`) : galerie de photos accessible et lecteur vidéo.
// Photos : 1 image pleine largeur, 2 à 4 en grille responsive (CSS .xm-gallery-n), chacune dans un vrai <button> (cible
// d'au moins 44 px, focus visible) avec son `alt` (repli : « Photo n sur N de la publication de … ») et chargement
// paresseux ; un clic, Entrée ou Espace ouvre la visionneuse (<XmImageViewer>, chargée à la demande). Vidéo : lecteur
// natif <XmVideoPlayer> (chargé à la demande ; sa description reste lisible en attendant ou si le lecteur manque).
// Aucun import statique de la visionneuse ni du lecteur : budgets du JS membre (Feed/Index.vue, Show.vue…).
// Props : media (PostMedia[]), author (pseudonyme, pour le texte de repli).
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/app/useToast';
import { lazyComponent } from '@/feed/lazy';
import type { PostMedia } from '@/feed/types';

const props = defineProps<{ media: PostMedia[]; author: string }>();

const { t } = useI18n();
const toast = useToast();
const viewer = lazyComponent(() => import('./XmImageViewer.vue'));
const player = lazyComponent(() => import('./XmVideoPlayer.vue'));
const Viewer = viewer.component;
const Player = player.component;

const photos = computed(() => props.media
    .filter((item) => item.kind === 'image' && item.url)
    .map((item, index, all) => ({
        url: item.url,
        width: item.width,
        height: item.height,
        alt: item.alt || (all.length === 1 ? t('feed.post.image_alt', { name: props.author }) : t('feed.gallery.image_fallback', { n: index + 1, count: all.length, name: props.author })),
    })));
const video = computed(() => props.media.find((item) => item.kind === 'video' && item.url) ?? null);
const opened = ref<number | null>(null);

watch(video, (value) => { if (value) void player.load(); }, { immediate: true });

async function open(index: number) {
    opened.value = index;
    if (!(await viewer.load())) {
        opened.value = null;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}
</script>

<template>
    <div v-if="photos.length > 0" :class="['xm-gallery', `xm-gallery-${Math.min(photos.length, 4)}`]" role="group" :aria-label="t('feed.gallery.label')" data-xm-gallery @pointerenter="viewer.load()">
        <button v-for="(photo, index) in photos" :key="photo.url" type="button" class="xm-gallery-item" aria-haspopup="dialog" :data-xm-gallery-item="index" @focus="viewer.load()" @click="open(index)">
            <img :src="photo.url" :alt="photo.alt" :width="photo.width ?? undefined" :height="photo.height ?? undefined" loading="lazy" decoding="async">
            <span class="visually-hidden">{{ t('feed.gallery.open', { n: index + 1, count: photos.length }) }}</span>
        </button>
    </div>
    <component :is="Viewer" v-if="opened !== null && Viewer" :images="photos" :start="opened" @close="opened = null" />
    <component :is="Player" v-if="video && Player" :media="video" />
    <figure v-else-if="video" class="xm-video mb-0" data-xm-video-pending>
        <figcaption class="small text-secondary">{{ video.alt }}</figcaption>
    </figure>
</template>
