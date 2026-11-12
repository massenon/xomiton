<script setup lang="ts">
// Case d'une conversation en BD (P20-04) — jumeau Vue du composant Blade `x-bd.panel` (même HTML : test de parité
// `bd-panel`), destiné à l'éditeur des membres (lot B, P20-07) et à son aperçu. Illustration SANS texte (ou repli rose
// pâle avec la description de la scène tant que l'image manque), bulles SVG dessinées par bubbles.ts (mêmes calculs que
// BubbleSvg, texte échappé), transcription dans l'ordre de lecture pour les lecteurs d'écran (« Fifamè pense : … »).
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { renderSvg, transcript, type Bubble } from './bubbles';

const props = withDefaults(defineProps<{
    bubbles?: Bubble[];
    names?: Record<string, string>;
    image?: string | null;
    alt?: string;
    n?: number;
    count?: number;
    title?: string;
    priority?: boolean;
    id?: string | null;
}>(), { bubbles: () => [], names: () => ({}), image: null, alt: '', n: 1, count: 1, title: '', priority: false, id: null });

const { t } = useI18n();

const translate = (key: string, params?: Record<string, string>): string => (params ? t(key, params) : t(key));
const svg = computed(() => renderSvg(props.bubbles));
const lines = computed(() => transcript(props.bubbles, props.names, translate));
const alt = computed(() => String(props.alt ?? '').trim());
</script>

<template>
    <figure class="xm-bdc-panel" :id="id || undefined" data-xm-bdc-panel>
        <div class="xm-bdc-frame">
            <div class="xm-bdc-art">
                <img v-if="image" class="xm-bdc-img" :src="image" :alt="alt" :title="t('conversations_bd.panel.title', { n: String(n), count: String(count), title })" width="1200" height="800" :loading="priority ? 'eager' : 'lazy'" decoding="async" :fetchpriority="priority ? 'high' : undefined">
                <div v-else class="xm-bdc-fallback">
                    <p class="xm-bdc-fallback-label">{{ t('conversations_bd.panel.image_pending') }}</p>
                    <p class="xm-bdc-fallback-text">{{ alt }}</p>
                </div>
            </div>
            <div class="xm-bdc-overlay" v-html="svg"></div>
        </div>
        <figcaption class="xm-bdc-caption">
            <span class="xm-bdc-counter">{{ t('conversations_bd.panel.counter', { n: String(n), count: String(count) }) }}</span>
            <span v-if="lines.length === 0" class="xm-bdc-transcript">{{ t('conversations_bd.panel.no_bubble') }}</span>
            <template v-else>
                <span class="visually-hidden">{{ t('conversations_bd.panel.transcript') }}</span>
                <ol class="xm-bdc-transcript">
                    <li v-for="(line, index) in lines" :key="index" :class="{ 'xm-bdc-thought-line': line.thought }">{{ line.text }}</li>
                </ol>
            </template>
        </figcaption>
    </figure>
</template>
