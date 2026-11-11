<script setup lang="ts">
// Image d'un outil CCC (P8-12) : alt obligatoire (texte alternatif de la planche saisi dans /admin/ccc ; alt="" pour une
// image décorative), fond de chargement tant que l'image n'est pas arrivée, et message à la place de l'image si elle
// ne charge pas (le serveur sert déjà l'image de remplacement quand le fichier manque : ici, seul le réseau peut échouer).
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    src: string;
    alt: string;
    ratio?: '16x9' | '4x3' | null;
    contain?: boolean;
}>(), { ratio: null, contain: false });

const { t } = useI18n();
const state = ref<'loading' | 'ready' | 'error'>('loading');

watch(() => props.src, () => { state.value = 'loading'; });
</script>

<template>
    <div :class="['xm-ccc-image position-relative', ratio ? `xm-ratio-${ratio}` : null, { 'is-loading placeholder-glow': state === 'loading', 'is-contain': contain }]" :data-state="state">
        <img v-show="state !== 'error'" :src="src" :alt="alt" :class="contain ? 'xm-ccc-image__contain' : 'xm-img-cover'" loading="lazy" decoding="async" @load="state = 'ready'" @error="state = 'error'">
        <div v-if="state === 'error'" class="xm-ccc-image__failed d-flex flex-column align-items-center justify-content-center text-center text-secondary p-3" role="img" :aria-label="alt">
            <i class="bi bi-image fs-1" aria-hidden="true"></i>
            <span class="small">{{ t('ccc.image.failed') }}</span>
        </div>
    </div>
</template>
