<script setup lang="ts">
// Squelette de chargement : même HTML que <x-skeleton> (placeholders Bootstrap, à la forme du contenu ; role="status" +
// aria-busy + texte caché, blocs décoratifs). type : lines | card | avatar | kpi | table ; lines ; rows ; label.
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    type?: 'lines' | 'card' | 'avatar' | 'kpi' | 'table';
    lines?: number;
    rows?: number;
    label?: string | null;
}>(), { type: 'lines', lines: 3, rows: 3, label: null });

const WIDTHS = [12, 10, 8, 11, 7];
const { t } = useI18n();
const text = computed(() => props.label ?? t('components.skeleton.loading'));
</script>

<template>
    <div :class="['xm-skeleton placeholder-glow', `xm-skeleton--${type}`]" role="status" aria-busy="true">
        <span class="visually-hidden">{{ text }}</span>
        <div aria-hidden="true">
            <div v-if="type === 'card'" class="card h-100">
                <span class="placeholder d-block w-100 xm-ratio-16x9 rounded-top"></span>
                <div class="card-body">
                    <span class="placeholder col-4 rounded-pill mb-2"></span>
                    <p class="h5 card-title mb-2"><span class="placeholder col-8"></span></p>
                    <p class="mb-0"><span class="placeholder col-12"></span> <span class="placeholder col-10"></span> <span class="placeholder col-6"></span></p>
                </div>
            </div>
            <div v-else-if="type === 'avatar'" class="d-flex align-items-center gap-2">
                <span class="avatar"><span class="avatar-img rounded-circle placeholder d-block"></span></span>
                <div class="flex-grow-1"><span class="placeholder col-6 d-block mb-1"></span><span class="placeholder col-4 d-block placeholder-sm"></span></div>
            </div>
            <div v-else-if="type === 'kpi'" class="card xm-stat h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="w-75"><span class="placeholder col-8 d-block placeholder-sm mb-2"></span><span class="placeholder col-5 d-block placeholder-lg"></span></div>
                    <span class="placeholder rounded-circle xm-skeleton__icon"></span>
                </div>
            </div>
            <template v-else-if="type === 'table'">
                <div v-for="i in rows" :key="i" class="d-flex gap-3 py-2 border-bottom"><span class="placeholder col-2"></span><span class="placeholder col-3"></span><span class="placeholder col-2"></span><span class="placeholder col-2 ms-auto"></span></div>
            </template>
            <template v-else>
                <span v-for="i in lines" :key="i" :class="`placeholder d-block mb-2 col-${WIDTHS[(i - 1) % 5]}`"></span>
            </template>
        </div>
    </div>
</template>
