<script setup lang="ts">
// Indicateur d'étapes : même HTML que <x-stepper> (ol.xm-stepper > li.xm-stepper__item[.is-done|.is-current] ; étape
// courante aria-current="step", état annoncé en texte caché). steps ; current (1..n) ; label.
import { useI18n } from 'vue-i18n';

withDefaults(defineProps<{ steps: string[]; current?: number; label?: string | null }>(), { current: 1, label: null });

const { t } = useI18n();
</script>

<template>
    <ol class="xm-stepper list-unstyled d-flex flex-wrap gap-3 mb-4" :aria-label="label ?? t('components.stepper.label')">
        <li v-for="(step, i) in steps" :key="i" :class="['xm-stepper__item', { 'is-done': i + 1 < current, 'is-current': i + 1 === current }]" :aria-current="i + 1 === current ? 'step' : undefined">
            <span class="xm-stepper__dot" aria-hidden="true"><i v-if="i + 1 < current" class="bi bi-check-lg"></i><template v-else>{{ i + 1 }}</template></span>
            <span>{{ step }}<span class="visually-hidden"> — {{ i + 1 < current ? t('components.stepper.done') : (i + 1 === current ? t('components.stepper.current') : t('components.stepper.todo')) }}</span></span>
        </li>
    </ol>
</template>
