<script setup lang="ts">
// Manuel du pair animateur·rice (P8-12 ; prototype manuel-pair-animateur.php) : liste des modules (titre, durée, nombre de
// points, progression), détail d'un module avec ses chapitres et des cases « point vu », progression par module et
// globale (barres `role="progressbar"`, annonce `aria-live` à chaque case), impression du récapitulatif (feuille
// `.xm-ccc-print`, CSS d'impression de resources/css/app/pages/ccc.css). La progression reste sur l'appareil
// (ccc/progress.ts : localStorage protégé, jamais envoyée au serveur). Pas de quiz : aucune source de questions.
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import XmButton from '@/components/XmButton.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import { clearSeen, loadSeen, percentOf, saveSeen, seenCount, toggle, totals, type SeenState } from '@/ccc/progress';
import type { ManualModule } from '@/ccc/types';

const props = withDefaults(defineProps<{
    modules: ManualModule[];
    account?: number | string | null;
}>(), { account: null });

const { t } = useI18n();

const loaded = loadSeen(props.account, props.modules);
const seen = ref<SeenState>(loaded.seen);
const storageOk = ref(loaded.available);
const openKey = ref<string | null>(null);
const announce = ref('');
const detailTitle = ref<HTMLElement | null>(null);
const openers = ref<Record<string, HTMLElement | null>>({});
const flash = ref('');

const current = computed(() => props.modules.find((module) => module.key === openKey.value) ?? null);
const overall = computed(() => totals(seen.value, props.modules));
const today = computed(() => new Date().toLocaleDateString('fr-FR'));

// Un module masqué entre deux visites : le détail ouvert se referme, la progression est nettoyée à la prochaine écriture.
watch(() => props.modules, (modules) => {
    if (openKey.value !== null && !modules.some((module) => module.key === openKey.value)) openKey.value = null;
});

function moduleSeen(module: ManualModule): number {
    return seenCount(seen.value, module);
}

function isSeen(module: ManualModule, rank: number): boolean {
    return seen.value[module.key]?.includes(rank) ?? false;
}

function persist(): void {
    storageOk.value = saveSeen(props.account, seen.value);
}

function onCheck(module: ManualModule, rank: number, event: Event): void {
    const on = (event.target as HTMLInputElement).checked;
    seen.value = toggle(seen.value, module.key, rank, on);
    persist();
    flash.value = '';
    announce.value = `${t('ccc.manual.progress_announce', { done: moduleSeen(module), total: module.chapters.length })} ${t('ccc.manual.global_announce', { percent: overall.value.percent })}`;
}

async function open(module: ManualModule): Promise<void> {
    openKey.value = module.key;
    announce.value = '';
    await nextTick();
    detailTitle.value?.focus();
    detailTitle.value?.scrollIntoView?.({ block: 'start' });
}

async function back(): Promise<void> {
    const key = openKey.value;
    openKey.value = null;
    await nextTick();
    if (key) openers.value[key]?.focus();
}

function reset(): void {
    seen.value = {};
    clearSeen(props.account);
    announce.value = '';
    flash.value = t('ccc.manual.reset_done');
}

function print(): void {
    window.print();
}
</script>

<template>
    <section class="vstack gap-4 xm-ccc-manual" :aria-labelledby="'ccc-manual-title'">
        <div class="xm-ccc-screen vstack gap-3">
            <div>
                <h2 id="ccc-manual-title" class="h4 mb-1">{{ t('ccc.manual.heading') }}</h2>
                <p class="text-secondary mb-0">{{ t('ccc.manual.intro') }}</p>
            </div>

            <XmEmptyState v-if="modules.length === 0" icon="journal-bookmark" :title="t('ccc.empty.manuel')">{{ t('ccc.empty.hint') }}</XmEmptyState>

            <template v-else>
                <div class="card card-body vstack gap-2" data-ccc-global>
                    <div class="d-flex align-items-center gap-3">
                        <span class="fw-semibold">{{ t('ccc.manual.progress_global') }}</span>
                        <div class="progress flex-grow-1 xm-ccc-bar" role="progressbar" :aria-label="t('ccc.manual.progress_global')" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="overall.percent" :aria-valuetext="t('ccc.manual.progress_count', { done: overall.done, total: overall.total })">
                            <div class="progress-bar bg-success" :style="{ width: `${overall.percent}%` }"></div>
                        </div>
                        <strong data-ccc-global-percent>{{ t('ccc.manual.progress_percent', { percent: overall.percent }) }}</strong>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <XmButton variant="primary" icon="printer" @click="print">{{ t('ccc.manual.print') }}</XmButton>
                        <XmButton variant="light" @click="reset">{{ t('ccc.manual.reset') }}</XmButton>
                    </div>
                    <p v-if="!storageOk" class="small text-warning-emphasis mb-0" role="status">{{ t('ccc.manual.storage_unavailable') }}</p>
                    <p v-if="flash" class="small text-secondary mb-0" role="status">{{ flash }}</p>
                </div>

                <ul v-if="current === null" class="list-unstyled row g-3 mb-0" data-ccc-modules>
                    <li v-for="module in modules" :key="module.key" class="col-12 col-md-6">
                        <article class="card h-100" :aria-labelledby="`ccc-mod-${module.key}`">
                            <div class="card-body vstack gap-2">
                                <h3 :id="`ccc-mod-${module.key}`" class="h6 mb-0">{{ module.title }}</h3>
                                <p v-if="module.description" class="small text-secondary mb-0">{{ module.description }}</p>
                                <div class="d-flex flex-wrap gap-2 small">
                                    <span v-if="module.duration_minutes" class="badge bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-clock me-1" aria-hidden="true"></i><span class="visually-hidden">{{ t('ccc.manual.duration_label', { minutes: module.duration_minutes }) }}</span><span aria-hidden="true">{{ t('ccc.manual.duration', { minutes: module.duration_minutes }) }}</span></span>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ t('ccc.manual.chapters_count', { count: module.chapters.length }, module.chapters.length) }}</span>
                                    <span v-if="module.chapters.length > 0 && moduleSeen(module) === module.chapters.length" class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ t('ccc.manual.module_done') }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1 xm-ccc-bar" role="progressbar" :aria-label="t('ccc.manual.progress_module', { title: module.title })" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percentOf(moduleSeen(module), module.chapters.length)" :aria-valuetext="t('ccc.manual.progress_count', { done: moduleSeen(module), total: module.chapters.length })">
                                        <div class="progress-bar bg-success" :style="{ width: `${percentOf(moduleSeen(module), module.chapters.length)}%` }"></div>
                                    </div>
                                    <span class="small text-secondary text-nowrap" aria-hidden="true">{{ moduleSeen(module) }}/{{ module.chapters.length }}</span>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent">
                                <button :ref="(el) => { openers[module.key] = el as HTMLElement | null; }" type="button" class="btn btn-primary-soft btn-sm" :aria-label="`${t('ccc.manual.open')}\u00a0: ${module.title}`" @click="open(module)">{{ t('ccc.manual.open') }}</button>
                            </div>
                        </article>
                    </li>
                </ul>

                <div v-else class="card" :aria-label="t('ccc.manual.detail_label', { title: current.title })" role="region" data-ccc-detail>
                    <div class="card-body vstack gap-3">
                        <div><XmButton variant="ghost" size="sm" icon="arrow-left" class="p-0" @click="back">{{ t('ccc.manual.back') }}</XmButton></div>
                        <div>
                            <h3 ref="detailTitle" class="h5 mb-1" tabindex="-1">{{ current.title }}</h3>
                            <p v-if="current.description" class="text-secondary mb-1">{{ current.description }}</p>
                            <span v-if="current.duration_minutes" class="badge bg-secondary-subtle text-secondary-emphasis"><i class="bi bi-clock me-1" aria-hidden="true"></i>{{ t('ccc.manual.duration_label', { minutes: current.duration_minutes }) }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1 xm-ccc-bar" role="progressbar" :aria-label="t('ccc.manual.progress_module', { title: current.title })" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percentOf(moduleSeen(current), current.chapters.length)" :aria-valuetext="t('ccc.manual.progress_count', { done: moduleSeen(current), total: current.chapters.length })">
                                <div class="progress-bar bg-success" :style="{ width: `${percentOf(moduleSeen(current), current.chapters.length)}%` }"></div>
                            </div>
                            <span class="small text-secondary text-nowrap" aria-hidden="true">{{ moduleSeen(current) }}/{{ current.chapters.length }}</span>
                        </div>
                        <fieldset>
                            <legend class="h6">{{ t('ccc.manual.chapters_heading') }}</legend>
                            <div class="vstack gap-2">
                                <div v-for="(chapter, rank) in current.chapters" :key="rank" class="form-check xm-ccc-check">
                                    <input :id="`ccc-ch-${current.key}-${rank}`" class="form-check-input" type="checkbox" :checked="isSeen(current, rank)" @change="onCheck(current, rank, $event)">
                                    <label class="form-check-label" :for="`ccc-ch-${current.key}-${rank}`">{{ chapter }}</label>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                </div>
            </template>
        </div>

        <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-ccc-live>{{ announce }}</div>

        <!-- Récapitulatif imprimé : caché à l'écran, seul visible à l'impression (ccc.css). -->
        <div class="xm-ccc-print" data-ccc-print>
            <h2 class="h4">{{ t('ccc.manual.summary_heading') }}</h2>
            <p v-if="modules.length === 0">{{ t('ccc.manual.summary_empty') }}</p>
            <template v-else>
                <p class="fw-semibold">{{ t('ccc.manual.progress_global') }}&#160;: {{ t('ccc.manual.progress_percent', { percent: overall.percent }) }} ({{ t('ccc.manual.progress_count', { done: overall.done, total: overall.total }) }})</p>
                <section v-for="module in modules" :key="module.key" class="mb-3">
                    <h3 class="h6 mb-1">{{ t('ccc.manual.summary_line', { title: module.title, done: moduleSeen(module), total: module.chapters.length }) }}</h3>
                    <ul class="mb-0">
                        <li v-for="(chapter, rank) in module.chapters" :key="rank">{{ isSeen(module, rank) ? '☑' : '☐' }} {{ chapter }}</li>
                    </ul>
                </section>
                <p class="small">{{ t('ccc.manual.summary_date', { date: today }) }}</p>
            </template>
        </div>
    </section>
</template>
