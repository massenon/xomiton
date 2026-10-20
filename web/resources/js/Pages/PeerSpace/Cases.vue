<script setup lang="ts">
// Page `/espace-pair/dossiers` (lot espace-pair, P9-08 ; ROLE_ET_PERMISSIONS.md §3 : fiches et suivi 👁 sur ses dossiers),
// page Inertia `PeerSpace/Cases` : liste des dossiers que la pair·e a collectés (scope `visibleTo` côté serveur, jamais
// ceux d'un autre), filtres statut · nature · code (visite partielle, adresse ?status=&kind=&q=&page= gardée), pagination,
// lien vers la fiche par code. Aucune note dans la liste (ADR-33). Pagination en liens (`<Link>`, adresse partageable, visite
// partielle) ; le total de résultats est une région `aria-live` (annoncé après un filtre ou un changement de page).
// Props : PeerCasesPageController (title, filters, statuses, kinds, cases, search_max).
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import type { CaseFilters, CasePage, Option } from '@/peer/types';
import XmBadge from '@/components/XmBadge.vue';
import XmButton from '@/components/XmButton.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import XmField from '@/components/XmField.vue';

const props = withDefaults(defineProps<{
    title: string;
    filters: CaseFilters;
    statuses: Option[];
    kinds: Option[];
    cases: CasePage;
    search_max?: number;
}>(), { search_max: 12 });

const RELOAD = ['filters', 'cases'];

const { t } = useI18n();
const base = route('espace-pair.dossiers', undefined, '/espace-pair/dossiers');
const q = ref(props.filters.q ?? '');
const status = ref(props.filters.status ?? '');
const kind = ref(props.filters.kind ?? '');
const switching = ref(false);

watch(() => props.filters, (next) => {
    q.value = next.q ?? '';
    status.value = next.status ?? '';
    kind.value = next.kind ?? '';
});

const statusOptions = computed(() => ({ '': t('peer.cases.all_statuses'), ...Object.fromEntries(props.statuses.map((item) => [item.value, item.label])) }));
const kindOptions = computed(() => ({ '': t('peer.cases.all_kinds'), ...Object.fromEntries(props.kinds.map((item) => [item.value, item.label])) }));
const filtered = computed(() => Boolean(props.filters.q || props.filters.status || props.filters.kind));

function query(values: { q?: string; status?: string; kind?: string; page?: number }): Record<string, string> {
    const out: Record<string, string> = {};
    if (values.q && values.q.trim() !== '') out.q = values.q.trim();
    if (values.status) out.status = values.status;
    if (values.kind) out.kind = values.kind;
    if (values.page && values.page > 1) out.page = String(values.page);
    return out;
}

function visit(params: Record<string, string>) {
    router.get(base, params, {
        only: RELOAD,
        preserveState: true,
        preserveScroll: true,
        onStart: () => { switching.value = true; },
        onFinish: () => { switching.value = false; },
    });
}

const onFilter = () => visit(query({ q: q.value, status: status.value, kind: kind.value }));
/** Adresse d'une page, filtres gardés (même forme que celle du serveur : ?status=&kind=&q=&page=). */
function pageUrl(page: number): string {
    const params = new URLSearchParams(query({ q: props.filters.q ?? '', status: props.filters.status ?? '', kind: props.filters.kind ?? '', page }));
    const search = params.toString();
    return search === '' ? base : `${base}?${search}`;
}
const hasPrevious = computed(() => props.cases.meta.current_page > 1);
const hasNext = computed(() => props.cases.meta.current_page < props.cases.meta.last_page);

function onReset(event: MouseEvent) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    visit({});
}

const caseUrl = (code: string) => route('espace-pair.dossiers.show', { code }, `/espace-pair/dossiers/${code}`);
const place = (item: CasePage['data'][number]) => [item.commune?.name, item.zone?.name].filter(Boolean).join(', ');
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h1 class="h3 mb-0">{{ t('peer.cases.heading') }}</h1>
                <p class="text-secondary mb-0">{{ t('peer.cases.intro') }}</p>
            </div>
            <XmButton :href="route('collecte', undefined, '/collecte')" inertia icon="pencil-square">{{ t('peer.cases.new') }}</XmButton>
        </div>

        <form class="card card-body" role="search" :aria-label="t('peer.cases.filters_label')" novalidate @submit.prevent="onFilter">
            <div class="row g-3 align-items-end">
                <div class="col-md-4"><XmField v-model="q" name="q" type="search" :label="t('peer.cases.search')" :placeholder="t('peer.cases.search_placeholder')" :maxlength="search_max" /></div>
                <div class="col-md-3"><XmField v-model="status" name="status" type="select" :label="t('peer.cases.status')" :options="statusOptions" /></div>
                <div class="col-md-3"><XmField v-model="kind" name="kind" type="select" :label="t('peer.cases.kind')" :options="kindOptions" /></div>
                <div class="col-md-2 d-flex gap-2 mb-3">
                    <XmButton type="submit" size="sm" :loading="switching">{{ t('peer.cases.filter') }}</XmButton>
                    <a v-if="filtered" class="btn btn-light btn-sm" :href="base" @click="onReset">{{ t('peer.cases.reset') }}</a>
                </div>
            </div>
        </form>

        <section class="card" aria-labelledby="peerCasesList">
            <div class="card-header"><h2 id="peerCasesList" class="h5 mb-0" aria-live="polite" aria-atomic="true" data-peer-results>{{ t('peer.cases.results', { count: cases.meta.total }, cases.meta.total) }}<span v-if="cases.meta.last_page > 1" class="visually-hidden"> {{ t('peer.cases.page', { page: cases.meta.current_page, total: cases.meta.last_page }) }}</span></h2></div>
            <div v-if="cases.data.length > 0" class="xm-table-wrap">
                <table class="table table-hover align-middle mb-0" data-peer-cases>
                    <thead><tr><th scope="col">{{ t('peer.cases.columns.code') }}</th><th scope="col">{{ t('peer.cases.columns.kind') }}</th><th scope="col">{{ t('peer.cases.columns.date') }}</th><th scope="col">{{ t('peer.cases.columns.place') }}</th><th scope="col">{{ t('peer.cases.columns.violence') }}</th><th scope="col">{{ t('peer.cases.columns.status') }}</th><th scope="col" class="text-end">{{ t('peer.cases.columns.followups') }}</th></tr></thead>
                    <tbody>
                        <tr v-for="item in cases.data" :key="item.id">
                            <td class="fw-semibold"><Link :href="caseUrl(item.code)">{{ item.code }}<span class="visually-hidden"> — {{ t('peer.cases.open') }}</span></Link></td>
                            <td><XmBadge color="neutral">{{ item.kind }}</XmBadge></td>
                            <td>{{ item.occurred_on }}</td>
                            <td>{{ place(item) }}</td>
                            <td>{{ item.violence_type?.label }}</td>
                            <td><XmBadge :color="item.status_badge">{{ item.status_label }}</XmBadge></td>
                            <td class="text-end">{{ item.followups_count ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <XmEmptyState v-else :title="filtered ? t('peer.cases.empty_filtered') : t('peer.cases.empty')" icon="folder2-open" compact>
                <template v-if="!filtered">{{ t('peer.cases.empty_text') }}</template>
                <template #action><XmButton :href="route('collecte', undefined, '/collecte')" inertia icon="pencil-square">{{ t('peer.cases.new') }}</XmButton></template>
            </XmEmptyState>
            <nav v-if="cases.meta.last_page > 1" class="card-footer d-flex justify-content-between align-items-center" :aria-label="t('peer.cases.pagination')">
                <Link v-if="hasPrevious" class="btn btn-outline-secondary btn-sm" :href="pageUrl(cases.meta.current_page - 1)" :only="RELOAD" preserve-state preserve-scroll rel="prev"><i class="bi bi-chevron-left me-1" aria-hidden="true"></i>{{ t('peer.cases.previous') }}</Link>
                <span v-else class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true"><i class="bi bi-chevron-left me-1" aria-hidden="true"></i>{{ t('peer.cases.previous') }}</span>
                <span class="small text-secondary">{{ t('peer.cases.page', { page: cases.meta.current_page, total: cases.meta.last_page }) }}</span>
                <Link v-if="hasNext" class="btn btn-outline-secondary btn-sm" :href="pageUrl(cases.meta.current_page + 1)" :only="RELOAD" preserve-state preserve-scroll rel="next">{{ t('peer.cases.next') }}<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i></Link>
                <span v-else class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">{{ t('peer.cases.next') }}<i class="bi bi-chevron-right ms-1" aria-hidden="true"></i></span>
            </nav>
        </section>
    </div>
</template>
