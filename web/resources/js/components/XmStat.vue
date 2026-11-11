<script setup lang="ts">
// Indicateur clé : même HTML que <x-stat> (.card.xm-stat[.danger|.success|.warning|.violet] > .card-body : libellé,
// .kpi-value, tendance avec flèche et mot caché, sous-texte, icône décorative à droite).
// href : la carte entière devient un lien (+ xm-card-hover) ; inertia → <Link>.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    label: string;
    value: string | number;
    variant?: '' | 'danger' | 'success' | 'warning' | 'violet';
    icon?: string | null;
    trend?: string | null;
    direction?: 'up' | 'down' | 'flat';
    sub?: string | null;
    href?: string | null;
    inertia?: boolean;
}>(), { variant: '', icon: null, trend: null, direction: 'flat', sub: null, href: null, inertia: false });

const ARROWS: Record<string, [string, string]> = {
    up: ['arrow-up-right', 'components.stat.trend_up'],
    down: ['arrow-down-right', 'components.stat.trend_down'],
    flat: ['dash', 'components.stat.trend_flat'],
};
const { t } = useI18n();
const arrow = computed(() => ARROWS[props.direction] ?? ARROWS.flat);
const linkTag = computed(() => (props.inertia ? Link : 'a'));
</script>

<template>
    <component :is="linkTag" v-if="href" :href="href" class="text-decoration-none text-body d-block h-100">
        <div :class="['card xm-stat h-100', variant, 'xm-card-hover']">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="small text-secondary">{{ label }}</div>
                    <div class="kpi-value">{{ value }}</div>
                    <div v-if="trend" class="small fw-semibold"><i :class="`bi bi-${arrow[0]}`" aria-hidden="true"></i> {{ trend }}<span class="visually-hidden"> ({{ t(arrow[1]) }})</span></div>
                    <div v-if="sub" class="small text-secondary">{{ sub }}</div>
                    <span class="small text-primary">{{ t('common.see_details') }}</span>
                </div>
                <i v-if="icon" :class="`bi bi-${icon} fs-2 text-secondary opacity-50`" aria-hidden="true"></i>
            </div>
        </div>
    </component>
    <div v-else :class="['card xm-stat h-100', variant]">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <div class="small text-secondary">{{ label }}</div>
                <div class="kpi-value">{{ value }}</div>
                <div v-if="trend" class="small fw-semibold"><i :class="`bi bi-${arrow[0]}`" aria-hidden="true"></i> {{ trend }}<span class="visually-hidden"> ({{ t(arrow[1]) }})</span></div>
                <div v-if="sub" class="small text-secondary">{{ sub }}</div>
            </div>
            <i v-if="icon" :class="`bi bi-${icon} fs-2 text-secondary opacity-50`" aria-hidden="true"></i>
        </div>
    </div>
</template>
