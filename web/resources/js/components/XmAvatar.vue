<script setup lang="ts">
// Avatar du thème Social : même HTML que <x-avatar> (.avatar[.avatar-{taille}] > img.avatar-img ; sans image : initiales
// dans .avatar-img.xm-avatar, role="img" + aria-label).
// size : '' (48 px) | xs (35) | sm (40) | lg (64) | xl (82) | xxl (82, 128 ≥ 768 px) | xxxl (128) ; rounded : circle | 2.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    name: string;
    src?: string | null;
    size?: '' | 'xs' | 'sm' | 'lg' | 'xl' | 'xxl' | 'xxxl';
    rounded?: 'circle' | '2';
}>(), { src: null, size: '', rounded: 'circle' });

const PX: Record<string, number> = { xs: 35, sm: 40, '': 48, lg: 64, xl: 82, xxl: 82, xxxl: 128 };
const { t } = useI18n();
const px = computed(() => PX[props.size] ?? 48);
const shape = computed(() => (props.rounded === 'circle' ? 'rounded-circle' : 'rounded-2'));
const alt = computed(() => t('components.avatar.alt', { name: props.name }));
const initials = computed(() => props.name.trim().split(/\s+/u).filter(Boolean).slice(0, 2)
    .map((word) => (Array.from(word)[0] ?? '').toLocaleUpperCase('fr')).join(''));
</script>

<template>
    <div :class="['avatar', { [`avatar-${size}`]: size !== '' }]">
        <img v-if="src" :class="`avatar-img ${shape}`" :src="src" :alt="alt" :title="name" :width="px" :height="px" loading="lazy">
        <span v-else :class="`avatar-img ${shape} xm-avatar`" role="img" :aria-label="alt" :title="name">{{ initials }}</span>
    </div>
</template>
