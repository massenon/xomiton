<script setup lang="ts">
// État vide : même HTML que <x-empty-state> (UX_GUIDELINES §3 : illustration + une phrase + une action).
// title ; icon (défaut inbox) ; image (décorative, alt="") ; compact. Slots : défaut (explication), action.
import { useSlots } from 'vue';
import XmImg from './XmImg.vue';

withDefaults(defineProps<{
    title: string;
    icon?: string;
    image?: string | null;
    compact?: boolean;
}>(), { icon: 'inbox', image: null, compact: false });

const slots = useSlots();
</script>

<template>
    <div :class="['text-center text-secondary xm-empty', compact ? 'py-4 px-3' : 'py-5 px-3']">
        <XmImg v-if="image" :src="image" alt="" :width="160" class="mb-3" />
        <i v-else :class="`bi bi-${icon} fs-1`" aria-hidden="true"></i>
        <p class="h5 text-body mt-2 mb-2">{{ title }}</p>
        <p v-if="slots.default" class="mb-2 mx-auto xm-prose"><slot /></p>
        <div v-if="slots.action" class="mt-3"><slot name="action" /></div>
    </div>
</template>
