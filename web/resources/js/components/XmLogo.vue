<script setup lang="ts">
// Logo Xomiton : même HTML que <x-logo> (BRAND_GUIDE §9 : jamais recomposé en police ; source unique
// public/assets/images/xomiton/brand/*.svg). variant : couleur | sombre | mono-encre | mono-bleu | mono-blanc (blanc) |
// vertical-couleur | vertical-sombre | icone-bleu (icone) | icone-blanc | icone-sombre ; adaptive : couleur en clair,
// sombre en sombre (.light-mode-item / .dark-mode-item) ; width (hauteur déduite du viewBox) ; link / href ; imgClass.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    variant?: string;
    adaptive?: boolean;
    width?: number;
    link?: boolean;
    href?: string;
    imgClass?: string;
}>(), { variant: 'couleur', adaptive: false, width: 160, link: true, href: '/', imgClass: '' });

const ALIASES: Record<string, string> = { blanc: 'mono-blanc', icone: 'icone-bleu' };
const KNOWN = ['couleur', 'sombre', 'mono-encre', 'mono-bleu', 'mono-blanc', 'vertical-couleur', 'vertical-sombre', 'icone-bleu', 'icone-blanc', 'icone-sombre'];
const BASE = '/assets/images/xomiton/brand/';

const { t } = useI18n();
const variant = computed(() => ALIASES[props.variant] ?? props.variant);
if (!props.adaptive && !KNOWN.includes(ALIASES[props.variant] ?? props.variant)) {
    throw new Error(`<XmLogo> variante inconnue [${props.variant}].`);
}
const family = computed(() => (variant.value.startsWith('vertical') ? 'vertical' : variant.value.startsWith('icone') ? 'icone' : 'horizontal'));
const height = computed(() => {
    const ratio = family.value === 'vertical' ? 125.14 / 218.17 : family.value === 'icone' ? 1 : 56.08 / 218.17;
    return Math.round(props.width * ratio);
});
const file = (v: string) => `${BASE}${family.value === 'icone' ? `icone-appli-${v.slice(6)}` : `logo-${v}`}.svg`;
const alt = computed(() => t('components.logo.alt'));
const imgClasses = (extra = '') => (`${extra} ${props.imgClass}`.trim() || undefined);
const images = computed(() => (props.adaptive
    ? [{ src: file('couleur'), class: imgClasses('light-mode-item') }, { src: file('sombre'), class: imgClasses('dark-mode-item') }]
    : [{ src: file(variant.value), class: imgClasses() }]));
</script>

<template>
    <a v-if="link" :href="href" :aria-label="t('components.logo.home')">
        <img v-for="image in images" :key="image.src" :src="image.src" :alt="alt" :title="alt" :width="width" :height="height" :class="image.class">
    </a>
    <span v-else>
        <img v-for="image in images" :key="image.src" :src="image.src" :alt="alt" :title="alt" :width="width" :height="height" :class="image.class">
    </span>
</template>
