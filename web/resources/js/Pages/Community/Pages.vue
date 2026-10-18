<script setup lang="ts">
// Page `/pages` (lot groupes-pages, P8-03 ; prototype pages.php), page Inertia `Community/Pages` dans le gabarit membre
// (col-lg-9 à côté du sidenav) : en-tête « Pages » + « Créer une page » (/pages/creer), onglets Toutes les pages · Mes
// abonnements · Mes pages (ajout : une page en attente d'approbation n'est visible que de sa propriétaire, P12-05),
// catégories (Toutes + celles des pages visibles) et recherche (visite partielle Inertia `only: pages, tab, category,
// q, categories`, adresse ?tab=&categorie=&q= gardée), cartes <PageCard>, « Charger plus » (API `GET pages?page=`),
// « Suivre la page » (community/usePageFollow.ts).
// Props : PagesPageController::index (title, tab, category, q, categories, pages, can_create).
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { communityApi } from '@/community/api';
import { usePageFollow } from '@/community/usePageFollow';
import type { OrganizationPage, PagedList } from '@/community/types';
import PageCard from '@/components/community/PageCard.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    tab?: string;
    category?: string | null;
    q?: string | null;
    categories?: string[];
    pages: PagedList<OrganizationPage>;
    can_create?: boolean;
}>(), { tab: 'all', category: null, q: null, categories: () => [], can_create: true });

const TABS = ['all', 'following', 'mine'] as const;

const { t } = useI18n();
const toast = useToast();
const api = communityApi(useApi());
const items = ref<OrganizationPage[]>([...props.pages.data]);
const page = ref(props.pages.meta.current_page);
const lastPage = ref(props.pages.meta.last_page);
const loading = ref(false);
const switching = ref(false);
const announce = ref('');
const search = ref(props.q ?? '');

const base = route('pages.index', undefined, '/pages');
const createHref = route('pages.create', undefined, '/pages/creer');
const hasMore = computed(() => page.value < lastPage.value);
const emptyText = computed(() => {
    if (props.tab === 'mine' && !props.q && !props.category) return t('community.pages.empty_mine');
    if (props.tab === 'following' && !props.q && !props.category) return t('community.pages.empty_following');
    return t('community.pages.empty');
});

watch(() => props.pages, (list) => {
    items.value = [...list.data];
    page.value = list.meta.current_page;
    lastPage.value = list.meta.last_page;
    announce.value = '';
});

const { busy, toggle } = usePageFollow((updated) => { items.value = items.value.map((item) => (item.id === updated.id ? updated : item)); });

function params(overrides: { tab?: string; category?: string | null; q?: string | null }): Record<string, string> {
    const tab = overrides.tab ?? props.tab;
    const category = 'category' in overrides ? overrides.category : props.category;
    const q = 'q' in overrides ? overrides.q : props.q;
    return { ...(tab !== 'all' ? { tab } : {}), ...(category ? { categorie: category } : {}), ...(q ? { q } : {}) };
}

function href(overrides: { tab?: string; category?: string | null }): string {
    const query = new URLSearchParams(params(overrides)).toString();
    return query ? `${base}?${query}` : base;
}

function visit(next: Record<string, string>) {
    router.get(base, next, {
        only: ['pages', 'tab', 'category', 'q', 'categories'],
        preserveState: true,
        preserveScroll: true,
        onStart: () => { switching.value = true; },
        onFinish: () => { switching.value = false; },
    });
}

function go(event: MouseEvent, overrides: { tab?: string; category?: string | null }) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (switching.value) return;
    visit(params(overrides));
}

const onSearch = () => visit(params({ q: search.value.trim() || null }));

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const next = await api.pages({ tab: props.tab, category: props.category, q: props.q, page: page.value + 1 });
        const known = new Set(items.value.map((item) => item.id));
        const fresh = next.data.filter((item) => !known.has(item.id));
        items.value = [...items.value, ...fresh];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
        announce.value = t('community.pages.loaded', { count: fresh.length }, fresh.length);
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="card card-body">
            <div class="d-sm-flex justify-content-between align-items-center">
                <div><h1 class="h4 mb-1">{{ t('community.pages.title') }}</h1><p class="small text-secondary mb-0">{{ t('community.pages.intro') }}</p></div>
                <Link v-if="can_create" class="btn btn-primary mt-3 mt-sm-0" :href="createHref"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>{{ t('community.pages.actions.create') }}</Link>
            </div>
            <hr>
            <ul class="nav nav-pills gap-2 mb-3 xm-community-tabs">
                <li v-for="key in TABS" :key="key" class="nav-item">
                    <a :class="['nav-link py-1 px-3', tab === key ? 'active' : 'bg-light']" :href="href({ tab: key })" :aria-current="tab === key ? 'page' : undefined" @click="go($event, { tab: key })">{{ t(`community.pages.tabs.${key}`) }}</a>
                </li>
            </ul>
            <div class="d-md-flex justify-content-between gap-3 align-items-center">
                <div class="d-flex flex-wrap gap-2 mb-3 mb-md-0" role="group" :aria-label="t('community.pages.categories_label')">
                    <a :class="['btn btn-sm', !category ? 'btn-primary' : 'btn-light']" :href="href({ category: null })" :aria-current="!category ? 'page' : undefined" @click="go($event, { category: null })">{{ t('community.pages.all_categories') }}</a>
                    <a v-for="name in categories" :key="name" :class="['btn btn-sm', category === name ? 'btn-primary' : 'btn-light']" :href="href({ category: name })" :aria-current="category === name ? 'page' : undefined" @click="go($event, { category: name })">{{ name }}</a>
                </div>
                <form role="search" @submit.prevent="onSearch">
                    <div class="input-group">
                        <input v-model="search" class="form-control" type="search" name="q" :maxlength="60" :placeholder="t('community.pages.search')" :aria-label="t('common.search')">
                        <button class="btn btn-primary" type="submit" :aria-label="t('common.search')"><i class="bi bi-search" aria-hidden="true"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4" :aria-busy="switching || loading ? 'true' : 'false'">
            <template v-if="switching">
                <div v-for="n in 3" :key="`s${n}`" class="col-sm-6 col-xl-4"><XmSkeleton type="card" :label="t('microcopy.loading.more')" /></div>
            </template>
            <template v-else>
                <PageCard v-for="item in items" :key="item.id" :page="item" :busy="busy === item.id" @toggle="toggle" />
                <div v-if="items.length === 0" class="col-12"><div class="card card-body text-center text-secondary">{{ emptyText }}</div></div>
            </template>
        </div>
        <div v-if="!switching && hasMore" class="xm-load-more" data-xm-more>
            <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>
</template>
