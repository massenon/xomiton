<script setup lang="ts">
// Niveau d'urgence : même HTML que <x-urgency> (.xm-urgency.xm-urgency--{level}, icône + mot, jamais la couleur seule).
// level : low | medium | high | critical.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{ level?: string }>(), { level: 'low' });

const ICONS: Record<string, string> = { low: 'info-circle', medium: 'exclamation-circle', high: 'exclamation-triangle', critical: 'exclamation-octagon-fill' };
const { t } = useI18n();
const current = computed(() => (ICONS[props.level] ? props.level : 'low'));
</script>

<template>
    <span :class="['xm-urgency', `xm-urgency--${current}`]"><i :class="`bi bi-${ICONS[current]}`" aria-hidden="true"></i><span class="visually-hidden">{{ t('components.urgency.prefix') }}</span> {{ t(`components.urgency.levels.${current}`) }}</span>
</template>
