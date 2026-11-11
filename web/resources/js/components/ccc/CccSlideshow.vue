<script setup lang="ts">
// « Mode animation » des boîtes à images (P8-12 ; prototype boites-a-images.php) : fenêtre plein écran pour projeter les
// images au groupe. `requestFullscreen` avec repli (la fenêtre reste plein cadre si le navigateur le refuse), clavier ← →
// (image précédente / suivante, retour au début ou à la fin) et Échap (fermer), bouton Fermer, focus piégé dans la
// fenêtre (Tab / Maj+Tab) puis rendu à l'élément qui l'a ouverte, défilement de la page figé, annonce `aria-live` à chaque
// image. Le guide de l'animateur·rice accompagne l'image en mode présentation du pair (`presentation`, P9-05).
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import CccImage from './CccImage.vue';
import { trapTab } from '@/ccc/focus';
import type { Slide } from '@/ccc/types';

const props = withDefaults(defineProps<{
    slides: Slide[];
    start?: number;
    presentation?: boolean;
}>(), { start: 0, presentation: false });

const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const root = ref<HTMLElement | null>(null);
const closeButton = ref<HTMLElement | null>(null);
const index = ref(Math.min(Math.max(props.start, 0), Math.max(props.slides.length - 1, 0)));
const slide = computed(() => props.slides[index.value] ?? null);
const announce = ref('');
let opener: HTMLElement | null = null;
let overflow = '';
let fullscreen = false;

function say(): void {
    const current = slide.value;
    announce.value = current === null ? '' : t('ccc.show.announce', { current: index.value + 1, total: props.slides.length, title: current.title });
}

function step(delta: number): void {
    const total = props.slides.length;
    if (total === 0) return;
    index.value = (index.value + delta + total) % total;
    say();
}

function leaveFullscreen(): void {
    try {
        if (document.fullscreenElement && typeof document.exitFullscreen === 'function') void document.exitFullscreen().catch(() => undefined);
    } catch {
        // Sortie du plein écran refusée : la fenêtre se ferme quand même.
    }
}

function close(): void {
    leaveFullscreen();
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        step(1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        step(-1);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if (root.value) {
        trapTab(event, root.value);
    }
}

/** Plein écran quitté par le navigateur (Échap) : la fenêtre se ferme avec lui, comme au prototype. */
function onFullscreenChange(): void {
    if (document.fullscreenElement === root.value) {
        fullscreen = true;
    } else if (fullscreen) {
        fullscreen = false;
        emit('close');
    }
}

onMounted(() => {
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('fullscreenchange', onFullscreenChange);
    closeButton.value?.focus();
    say();
    try {
        const element = root.value;
        if (element && typeof element.requestFullscreen === 'function') void element.requestFullscreen().catch(() => undefined);
    } catch {
        // Plein écran indisponible (iOS, iframe, autorisation refusée) : repli sur la fenêtre plein cadre.
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('fullscreenchange', onFullscreenChange);
    document.body.style.overflow = overflow;
    fullscreen = false;
    leaveFullscreen();
    if (opener && document.contains(opener)) opener.focus();
});
</script>

<template>
    <Teleport to="body">
        <div ref="root" :class="['xm-ccc-show', { 'xm-ccc-show--presentation': presentation }]" role="dialog" aria-modal="true" :aria-label="t('ccc.show.label')" tabindex="-1" data-ccc-show>
            <div class="xm-ccc-show__bar d-flex justify-content-between align-items-center gap-2 p-3">
                <span class="badge bg-primary" data-ccc-show-count>{{ t('ccc.show.counter', { current: index + 1, total: slides.length }) }}</span>
                <button ref="closeButton" type="button" class="btn btn-light btn-sm" @click="close"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>{{ t('ccc.show.close_hint') }}</button>
            </div>
            <div v-if="slide" class="xm-ccc-show__stage">
                <CccImage :key="slide.image + index" :src="slide.image" :alt="slide.alt" contain />
            </div>
            <div v-if="slide" class="xm-ccc-show__guide p-3 p-md-4">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <div class="flex-grow-1 min-w-0">
                        <p class="h5 mb-1" data-ccc-show-title>{{ slide.title }}</p>
                        <p v-if="slide.caption" class="mb-1" data-ccc-show-caption>{{ slide.caption }}</p>
                        <p v-if="presentation && slide.guide" class="small mb-0 opacity-75" data-ccc-show-guide><span class="visually-hidden">{{ t('ccc.show.guide') }}. </span>{{ slide.guide }}</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-light" :aria-label="t('ccc.show.previous')" @click="step(-1)"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="btn btn-light" :aria-label="t('ccc.show.next')" @click="step(1)"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </div>
                <p class="small opacity-75 mt-2 mb-0 d-none d-md-block">{{ t('ccc.show.keys') }}</p>
            </div>
            <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-ccc-show-live>{{ announce }}</div>
        </div>
    </Teleport>
</template>
