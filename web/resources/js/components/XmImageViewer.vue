<script setup lang="ts">
// Visionneuse de photos (P7-10, Q96) : fenêtre modale plein écran ouverte depuis la galerie d'une publication
// (<XmMediaGallery>, chargée à la demande). `role="dialog"` + `aria-modal`, focus d'abord sur « Fermer », **focus piégé**
// (Tab et Maj+Tab restent dans la fenêtre), Échap ferme, ← / → (et Début / Fin) changent de photo, boutons
// Précédente / Suivante de 44 px, compteur « 2 / 4 », photo courante annoncée (région polie : « Photo 2 sur 4. … »),
// défilement de la page figé, **focus rendu** à l'élément qui l'avait ouverte. Aucune animation ni lecture automatique.
// Props : images ({ url, alt, width, height }[]), start (rang de la photo ouverte). Émet `close`.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface ViewerImage { url: string; alt: string; width: number | null; height: number | null }

const props = withDefaults(defineProps<{ images: ViewerImage[]; start?: number }>(), { start: 0 });
const emit = defineEmits<{ close: [] }>();

const { t } = useI18n();
const root = ref<HTMLElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
const index = ref(Math.min(Math.max(0, props.start), Math.max(0, props.images.length - 1)));
const count = computed(() => props.images.length);
const current = computed(() => props.images[index.value]);
const status = computed(() => t('feed.gallery.viewer_status', { current: index.value + 1, count: count.value, alt: current.value?.alt ?? '' }));
let opener: HTMLElement | null = null;
let overflow = '';

const go = (delta: number) => {
    if (count.value < 2) return;
    index.value = (index.value + delta + count.value) % count.value;
};

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])';

function onKey(event: KeyboardEvent) {
    if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        emit('close');
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        go(-1);
    } else if (event.key === 'ArrowRight') {
        event.preventDefault();
        go(1);
    } else if (event.key === 'Home' && count.value > 1) {
        event.preventDefault();
        index.value = 0;
    } else if (event.key === 'End' && count.value > 1) {
        event.preventDefault();
        index.value = count.value - 1;
    } else if (event.key === 'Tab') {
        const items = Array.from(root.value?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? []);
        if (items.length === 0) return;
        const first = items[0];
        const last = items[items.length - 1];
        const active = document.activeElement;
        if (event.shiftKey && (active === first || !root.value?.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && (active === last || !root.value?.contains(active))) {
            event.preventDefault();
            first.focus();
        }
    }
}

function onBackdrop(event: MouseEvent) {
    if (event.target === event.currentTarget) emit('close');
}

onMounted(() => {
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    void nextTick(() => closeButton.value?.focus());
});

onBeforeUnmount(() => {
    document.body.style.overflow = overflow;
    if (opener && document.contains(opener)) opener.focus();
});

watch(() => props.images, (images) => { if (index.value >= images.length) index.value = Math.max(0, images.length - 1); });
</script>

<template>
    <Teleport to="body">
        <div ref="root" class="xm-viewer" role="dialog" aria-modal="true" :aria-label="t('feed.gallery.viewer_title')" data-xm-viewer @keydown="onKey">
            <div class="xm-viewer-bar">
                <p class="xm-viewer-count mb-0" aria-hidden="true">{{ t('feed.gallery.viewer_counter', { current: index + 1, count }) }}</p>
                <button ref="closeButton" type="button" class="xm-viewer-btn" :aria-label="t('feed.gallery.close')" @click="emit('close')"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <div class="xm-viewer-stage" @click="onBackdrop">
                <button v-if="count > 1" type="button" class="xm-viewer-btn xm-viewer-prev" :aria-label="t('feed.gallery.previous')" @click="go(-1)"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                <figure v-if="current" class="xm-viewer-figure" @click="onBackdrop">
                    <img :key="current.url" :src="current.url" :alt="current.alt" decoding="async">
                </figure>
                <button v-if="count > 1" type="button" class="xm-viewer-btn xm-viewer-next" :aria-label="t('feed.gallery.next')" @click="go(1)"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
            <p class="visually-hidden" role="status" aria-live="polite" data-xm-viewer-live>{{ status }}</p>
        </div>
    </Teleport>
</template>
