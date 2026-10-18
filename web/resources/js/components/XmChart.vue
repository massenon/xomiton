<script setup lang="ts">
// Graphique (COMPOSANTS §x-chart, UI_UX_DESIGN_SYSTEM §4.1) : Chart.js chargé à la demande (import() : hors du paquet
// app.ts), palette catégorielle --xm-chart-1..6 lue sur les jetons (clair / sombre), légende en bas, hauteur fixe
// (défaut 280 px) ; tableau de données accessible sous chaque graphique, effectifs sous `maskBelow` affichés
// « données masquées » (et absents du tracé). Le canevas est décoratif pour les lecteurs d'écran (role="img" +
// aria-label) : le tableau porte l'information.
// type : bar | line | doughnut | pie ; labels ; datasets [{ label, data }] ; label (nom accessible, légende du
// tableau) ; height ; maskBelow (défaut 5, null = aucun masquage).
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

export interface ChartSeries {
    label: string;
    data: Array<number | null>;
}

const props = withDefaults(defineProps<{
    type?: 'bar' | 'line' | 'doughnut' | 'pie';
    labels: string[];
    datasets: ChartSeries[];
    label: string;
    height?: number;
    maskBelow?: number | null;
}>(), { type: 'bar', height: 280, maskBelow: 5 });

const { t } = useI18n();
const canvas = ref<HTMLCanvasElement | null>(null);
const ready = ref(false);
let chart: { destroy: () => void; update: () => void; data: unknown } | null = null;

const masked = (value: number | null): boolean => value !== null && props.maskBelow !== null && value > 0 && value < props.maskBelow;
const cell = (value: number | null): string => (value === null ? '—' : masked(value) ? t('components.chart.masked') : value.toLocaleString('fr-FR'));

const palette = (): string[] => {
    const style = typeof window !== 'undefined' ? getComputedStyle(document.documentElement) : null;
    return [1, 2, 3, 4, 5, 6].map((i) => style?.getPropertyValue(`--xm-chart-${i}`).trim() || '');
};

const chartData = computed(() => {
    const colors = palette();
    const circular = props.type === 'doughnut' || props.type === 'pie';
    return {
        labels: props.labels,
        datasets: props.datasets.map((series, i) => ({
            label: series.label,
            data: series.data.map((value) => (masked(value) ? null : value)),
            backgroundColor: circular ? props.labels.map((_, j) => colors[j % 6]) : colors[i % 6],
            borderColor: circular ? undefined : colors[i % 6],
        })),
    };
});

async function draw() {
    if (!canvas.value) return;
    const { default: Chart } = await import('chart.js/auto');
    chart?.destroy();
    chart = new Chart(canvas.value, {
        type: props.type,
        data: chartData.value as never,
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
    }) as unknown as typeof chart;
    ready.value = true;
}

onMounted(() => { draw().catch(() => undefined); });
watch(() => [props.labels, props.datasets, props.type], () => { draw().catch(() => undefined); }, { deep: true });
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <figure class="xm-chart mb-0">
        <div class="position-relative" :style="{ height: `${height}px` }">
            <canvas ref="canvas" role="img" :aria-label="label"></canvas>
            <span v-if="!ready" class="visually-hidden" role="status">{{ t('components.chart.loading') }}</span>
        </div>
        <table class="visually-hidden">
            <caption>{{ label }} — {{ t('components.chart.table') }}</caption>
            <thead>
                <tr><th scope="col">{{ t('components.chart.category') }}</th><th v-for="series in datasets" :key="series.label" scope="col">{{ series.label }}</th></tr>
            </thead>
            <tbody>
                <tr v-for="(rowLabel, i) in labels" :key="rowLabel"><th scope="row">{{ rowLabel }}</th><td v-for="series in datasets" :key="series.label">{{ cell(series.data[i] ?? null) }}</td></tr>
            </tbody>
        </table>
    </figure>
</template>
