<script setup lang="ts">
// Page `/recherche` (lot recherche-membre, P8-13), page Inertia `Search/Index` chargée à la demande par app.ts
// (import.meta.glob) dans le gabarit membre (col-lg-9 à côté du sidenav), atteinte par le champ de l'en-tête (XmHeader)
// ou ses suggestions. Formulaire `role="search"` (adresse ?q= gardée, visite Inertia), familles en pastilles (Tout ·
// Ressources · Thématiques · Contacts · Membres, `aria-current`), vue d'ensemble par section (h2 + compteur, quatre
// résultats, « Voir les n résultats » → ?type=) ou une famille avec « Charger plus » (API `GET search?type=&page=`,
// focus sur le premier résultat ajouté, nombre annoncé). Résultats <SearchHit>. Terme trop court, aucun résultat,
// recherche indisponible : messages dédiés. Confidentialité : rien n'est gardé côté client (ni stockage local, ni
// historique de recherches), mention « Xomiton n'enregistre pas tes recherches » avec le rappel de l'historique du
// navigateur ; en mode discret (profile.discreet_mode, Q84), le terme reste hors de l'adresse : recherche par l'API
// (vue d'ensemble, famille) en état local, terme confié par l'en-tête en mémoire (takeHeldTerm). Props : SearchPageController.
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { searchApi } from '@/search/api';
import { canSuggest, cleanTerm, searchHref, takeHeldTerm } from '@/search/suggest';
import { useSessionStore } from '@/stores/session';
import type { SearchHit as Hit, SearchLimits, SearchPagination, SearchSection, SearchTypeInfo, SearchTypeValue } from '@/search/types';
import SearchHit from '@/components/search/SearchHit.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    q?: string;
    type?: SearchTypeValue | null;
    types?: SearchTypeInfo[];
    sections?: SearchSection[];
    pagination?: SearchPagination | null;
    too_short?: boolean;
    failed?: boolean;
    limits?: SearchLimits;
}>(), {
    q: '',
    type: null,
    types: () => [],
    sections: () => [],
    pagination: null,
    too_short: false,
    failed: false,
    limits: () => ({ min_length: 2, max_length: 80 }),
});

const { t } = useI18n();
const toast = useToast();
const api = searchApi(useApi());
const base = route('recherche', undefined, '/recherche');
const ressources = route('ressources.index', undefined, '/ressources');
const session = useSessionStore();
// Mode discret (Q84) : le terme reste hors de l'adresse (historique du navigateur) ; résultats en état local seulement.
const discreet = computed(() => session.user?.profile?.discreet_mode === true);

/** Résultats affichés : props du serveur, ou réponse de l'API en mode discret. */
interface View {
    q: string;
    type: SearchTypeValue | null;
    sections: SearchSection[];
    pagination: SearchPagination | null;
    too_short: boolean;
    failed: boolean;
}
const fromProps = (): View => ({ q: props.q, type: props.type, sections: props.sections, pagination: props.pagination, too_short: props.too_short, failed: props.failed });
const view = ref<View>(fromProps());

const input = ref(view.value.q);
const items = ref<Hit[]>([...(view.value.sections[0]?.items ?? [])]);
const page = ref(view.value.pagination?.current_page ?? 1);
const lastPage = ref(view.value.pagination?.last_page ?? 1);
const switching = ref(false);
const loading = ref(false);
const announce = ref('');

watch(() => [props.q, props.type, props.sections, props.pagination, props.too_short, props.failed] as const, () => { view.value = fromProps(); });
watch(view, (next) => {
    input.value = next.q;
    items.value = [...(next.sections[0]?.items ?? [])];
    page.value = next.pagination?.current_page ?? 1;
    lastPage.value = next.pagination?.last_page ?? 1;
});

const searched = computed(() => view.value.q !== '' && !view.value.too_short);
const visible = computed(() => view.value.sections.filter((section) => section.total > 0));
const grandTotal = computed(() => view.value.sections.reduce((sum, section) => sum + section.total, 0));
const current = computed(() => view.value.sections[0] ?? null);
const hasMore = computed(() => view.value.type !== null && page.value < lastPage.value);
const totals = computed<Record<string, number>>(() => Object.fromEntries(view.value.sections.map((section) => [section.type, section.total])));
const sectionId = (type: string) => `xm-search-section-${type}`;

/** Mode discret : même recherche par l'API, sans visite (l'adresse reste `/recherche`). */
async function searchInPlace(q: string, type: SearchTypeValue | null) {
    input.value = q;
    if (!canSuggest(q, props.limits.min_length)) {
        view.value = { q, type, sections: [], pagination: null, too_short: q !== '', failed: false };
        return;
    }
    switching.value = true;
    try {
        if (type === null) {
            view.value = { q, type, sections: await api.overview(q), pagination: null, too_short: false, failed: false };
        } else {
            const { data, meta } = await api.page(q, type, 1, true);
            const info = props.types.find((item) => item.type === type) ?? { type, label: type, icon: 'bi-search' };
            view.value = {
                q, type, too_short: false, failed: false,
                sections: [{ ...info, total: meta.total, more: meta.current_page < meta.last_page, items: data }],
                pagination: { current_page: meta.current_page, last_page: meta.last_page, per_page: meta.per_page, total: meta.total },
            };
        }
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
        else view.value = { q, type, sections: [], pagination: null, too_short: false, failed: true };
    } finally {
        switching.value = false;
    }
}

function visit(q: string, type: SearchTypeValue | null) {
    if (discreet.value) {
        void searchInPlace(q, type);
        return;
    }
    router.get(base, { ...(q !== '' ? { q } : {}), ...(type ? { type } : {}) }, {
        preserveScroll: false,
        onStart: () => { switching.value = true; },
        onFinish: () => { switching.value = false; },
    });
}

function onSubmit() {
    visit(cleanTerm(input.value, props.limits.max_length), view.value.type);
}

function typeHref(type: SearchTypeValue | null): string {
    return searchHref(base, discreet.value ? '' : view.value.q, type);
}

function onType(event: MouseEvent, type: SearchTypeValue | null) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    visit(view.value.q, type);
}

async function loadMore() {
    const { q, type } = view.value;
    if (loading.value || !hasMore.value || type === null) return;
    loading.value = true;
    let first: string | null = null;
    try {
        const next = await api.page(q, type, page.value + 1);
        const known = new Set(items.value.map((item) => `${item.type}-${item.id}`));
        const fresh = next.data.filter((item) => !known.has(`${item.type}-${item.id}`));
        items.value = [...items.value, ...fresh];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
        announce.value = t('search.loaded', { count: fresh.length }, fresh.length);
        first = fresh[0] ? `${fresh[0].type}-${fresh[0].id}` : null;
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
    // « Charger plus » disparaît à la dernière page : le focus passe au premier résultat ajouté.
    await nextTick();
    if (first !== null) document.querySelector<HTMLElement>(`[data-xm-hit="${first}"] h3 a`)?.focus();
}

// Terme confié par l'en-tête en mode discret (visite de `/recherche` sans `?q=`).
const held = takeHeldTerm();
if (held !== null) void searchInPlace(held, null);
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div>
            <h1 class="h3 mb-0">{{ t('search.heading') }}</h1>
            <p class="text-secondary mb-0">{{ t('search.intro') }}</p>
        </div>

        <form class="card card-body" role="search" :aria-label="t('search.form.label')" :action="base" method="get" @submit.prevent="onSubmit">
            <div class="d-flex flex-wrap gap-2">
                <label for="xmSearchPageInput" class="visually-hidden">{{ t('search.form.label') }}</label>
                <div class="input-group flex-grow-1 xm-search-page__field">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input id="xmSearchPageInput" v-model="input" class="form-control" type="search" name="q" autocomplete="off" :maxlength="limits.max_length" :placeholder="t('search.form.placeholder')" aria-describedby="xmSearchPageHint">
                </div>
                <input v-if="view.type" type="hidden" name="type" :value="view.type">
                <button class="btn btn-primary" type="submit">{{ t('search.form.submit') }}</button>
            </div>
            <p id="xmSearchPageHint" class="small text-secondary mt-2 mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i>{{ discreet ? t('search.privacy_discreet') : t('search.privacy') }} {{ t('search.form.hint', { min: limits.min_length }) }}</p>
        </form>

        <nav v-if="searched && types.length > 0" :aria-label="t('search.types.label')">
            <ul class="nav nav-pills gap-2 small">
                <li class="nav-item">
                    <a :class="['nav-link', { active: view.type === null }]" :href="typeHref(null)" :aria-current="view.type === null ? 'page' : undefined" @click="onType($event, null)">{{ t('search.types.all') }}</a>
                </li>
                <li v-for="item in types" :key="item.type" class="nav-item">
                    <a :class="['nav-link', { active: view.type === item.type }]" :href="typeHref(item.type)" :aria-current="view.type === item.type ? 'page' : undefined" @click="onType($event, item.type)">
                        <i :class="`bi ${item.icon} me-1`" aria-hidden="true"></i>{{ item.label }}<span v-if="view.type === null && totals[item.type] !== undefined" class="badge rounded-pill bg-light text-body ms-1">{{ totals[item.type] }}<span class="visually-hidden">&nbsp;{{ t('search.results_unit', totals[item.type]) }}</span></span>
                    </a>
                </li>
            </ul>
        </nav>

        <div :aria-busy="switching || loading ? 'true' : 'false'">
            <div v-if="switching" class="vstack gap-3">
                <XmSkeleton v-for="n in 2" :key="n" type="card" :label="t('microcopy.loading.default')" />
            </div>

            <div v-else-if="view.failed" class="alert alert-warning d-flex gap-2 mb-0" role="alert">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i><span>{{ t('search.failed') }}</span>
            </div>

            <div v-else-if="!searched" class="card card-body text-center text-secondary" role="status">
                <i class="bi bi-search fs-1" aria-hidden="true"></i>
                <p class="mb-0">{{ view.too_short ? t('search.too_short', { min: limits.min_length }) : t('search.start', { min: limits.min_length }) }}</p>
            </div>

            <template v-else-if="view.type === null">
                <p class="small text-secondary" role="status">{{ t('search.results', { count: grandTotal }, grandTotal) }}</p>
                <div v-if="visible.length === 0" class="card">
                    <XmEmptyState :title="t('search.empty')" icon="search">
                        <template #action><a class="btn btn-primary-soft" :href="ressources">{{ t('search.browse') }}</a></template>
                    </XmEmptyState>
                </div>
                <div v-else class="vstack gap-4">
                    <section v-for="section in visible" :key="section.type" class="card" :aria-labelledby="sectionId(section.type)">
                        <div class="card-header bg-transparent d-flex align-items-center gap-2">
                            <h2 :id="sectionId(section.type)" class="h5 mb-0"><i :class="`bi ${section.icon} me-2`" aria-hidden="true"></i>{{ section.label }}</h2>
                            <span class="badge rounded-pill bg-primary-soft text-primary">{{ section.total }}<span class="visually-hidden">&nbsp;{{ t('search.results_unit', section.total) }}</span></span>
                        </div>
                        <ul class="list-group list-group-flush px-3 mb-0">
                            <SearchHit v-for="hit in section.items" :key="`${hit.type}-${hit.id}`" :hit="hit" />
                        </ul>
                        <div v-if="section.more" class="card-footer bg-transparent">
                            <a class="btn btn-sm btn-primary-soft" :href="typeHref(section.type)" @click="onType($event, section.type)">{{ t('search.see_all_named', { count: section.total, type: section.label }, section.total) }}</a>
                        </div>
                    </section>
                </div>
            </template>

            <template v-else-if="current">
                <section class="card" :aria-labelledby="sectionId(current.type)">
                    <div class="card-header bg-transparent d-flex align-items-center gap-2">
                        <h2 :id="sectionId(current.type)" class="h5 mb-0"><i :class="`bi ${current.icon} me-2`" aria-hidden="true"></i>{{ current.label }}</h2>
                        <span class="badge rounded-pill bg-primary-soft text-primary" role="status">{{ t('search.results', { count: current.total }, current.total) }}</span>
                    </div>
                    <ul v-if="items.length > 0" class="list-group list-group-flush px-3 mb-0">
                        <SearchHit v-for="hit in items" :key="`${hit.type}-${hit.id}`" :hit="hit" />
                    </ul>
                    <XmEmptyState v-else compact :title="t('search.empty_section')" icon="search">
                        <template #action><a :href="typeHref(null)" @click="onType($event, null)">{{ t('search.back_all') }}</a></template>
                    </XmEmptyState>
                </section>
                <div v-if="hasMore" class="xm-load-more mt-3">
                    <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
                </div>
            </template>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>
</template>
