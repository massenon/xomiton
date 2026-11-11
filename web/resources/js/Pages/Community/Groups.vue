<script setup lang="ts">
// Page `/groupes` (lot groupes-pages, P8-02 ; prototype groups.php), page Inertia `Community/Groups` chargée à la
// demande par app.ts (import.meta.glob) dans le gabarit membre (col-lg-9 à côté du sidenav) : en-tête « Groupes » +
// « Créer un groupe » (modale chargée à la demande, components/community/CreateGroupModal.vue), onglets Découvrir · Mes
// groupes (visite partielle Inertia `only: groups, tab, q`, adresse ?tab=&q= gardée), recherche, cartes <GroupCard>,
// « Charger plus » (API `GET groups?page=`), état vide. Rejoindre / Demander à rejoindre / annuler sa demande :
// community/useGroupActions.ts (carte mise à jour à la réponse). Un groupe privé n'apparaît jamais dans « Découvrir »
// pour une non-membre (FR-SOC-05, Q75).
// Props : GroupsPageController::index (title, tab, q, groups, create).
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { lazyComponent } from '@/feed/lazy';
import { communityApi } from '@/community/api';
import { useGroupActions } from '@/community/useGroupActions';
import type { Group, GroupCreateOptions, PagedList } from '@/community/types';
import GroupCard from '@/components/community/GroupCard.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    tab?: string;
    q?: string | null;
    groups: PagedList<Group>;
    create: GroupCreateOptions;
}>(), { tab: 'discover', q: null });

const TABS = ['discover', 'mine'] as const;

const { t } = useI18n();
const toast = useToast();
const api = communityApi(useApi());
const items = ref<Group[]>([...props.groups.data]);
const page = ref(props.groups.meta.current_page);
const lastPage = ref(props.groups.meta.last_page);
const loading = ref(false);
const switching = ref(false);
const announce = ref('');
const search = ref(props.q ?? '');
const creating = ref(false);

const modal = lazyComponent(() => import('@/components/community/CreateGroupModal.vue'));
const CreateGroupModal = modal.component;

const base = route('groupes.index', undefined, '/groupes');
const hasMore = computed(() => page.value < lastPage.value);
const emptyText = computed(() => (props.tab === 'mine' && !props.q ? t('community.groups.empty_mine') : t('community.groups.empty')));
const tabHref = (tab: string) => {
    const query = new URLSearchParams();
    if (tab !== 'discover') query.set('tab', tab);
    if (props.q) query.set('q', props.q);
    const text = query.toString();
    return text ? `${base}?${text}` : base;
};

watch(() => props.groups, (list) => {
    items.value = [...list.data];
    page.value = list.meta.current_page;
    lastPage.value = list.meta.last_page;
    announce.value = '';
});

const replace = (group: Group) => { items.value = items.value.map((item) => (item.id === group.id ? group : item)); };
const { busy, join, cancelRequest } = useGroupActions(replace);

function visit(params: Record<string, string>) {
    router.get(base, params, {
        only: ['groups', 'tab', 'q'],
        preserveState: true,
        preserveScroll: true,
        onStart: () => { switching.value = true; },
        onFinish: () => { switching.value = false; },
    });
}

function selectTab(event: MouseEvent, tab: string) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (tab === props.tab || switching.value) return;
    visit({ ...(tab !== 'discover' ? { tab } : {}), ...(props.q ? { q: props.q } : {}) });
}

function onSearch() {
    const q = search.value.trim();
    visit({ ...(props.tab !== 'discover' ? { tab: props.tab } : {}), ...(q ? { q } : {}) });
}

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const next = await api.groups({ tab: props.tab, q: props.q, page: page.value + 1 });
        const known = new Set(items.value.map((group) => group.id));
        const fresh = next.data.filter((group) => !known.has(group.id));
        items.value = [...items.value, ...fresh];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
        announce.value = t('community.groups.loaded', { count: fresh.length }, fresh.length);
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
}

async function openCreate() {
    creating.value = true;
    if (!(await modal.load())) {
        creating.value = false;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

function onCreated(group: Group) {
    toast.show(t('community.groups.toasts.created'), 'success');
    router.visit(group.url);
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="card card-body">
            <div class="d-sm-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1">{{ t('community.groups.title') }}</h1>
                    <p class="small text-secondary mb-0">{{ t('community.groups.intro') }}</p>
                </div>
                <button v-if="create.can" class="btn btn-primary mt-3 mt-sm-0" type="button" aria-haspopup="dialog" @click="openCreate" @pointerenter="modal.load()"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>{{ t('community.groups.create.title') }}</button>
            </div>
            <hr>
            <div class="d-sm-flex justify-content-between align-items-center gap-3">
                <ul class="nav nav-pills gap-2 mb-3 mb-sm-0 xm-community-tabs">
                    <li v-for="key in TABS" :key="key" class="nav-item">
                        <a :class="['nav-link py-1 px-3', tab === key ? 'active' : 'bg-light']" :href="tabHref(key)" :aria-current="tab === key ? 'page' : undefined" @click="selectTab($event, key)">{{ t(`community.groups.tabs.${key}`) }}</a>
                    </li>
                </ul>
                <form role="search" @submit.prevent="onSearch">
                    <div class="input-group">
                        <input v-model="search" class="form-control" type="search" name="q" :maxlength="60" :placeholder="t('community.groups.search')" :aria-label="t('common.search')">
                        <button class="btn btn-primary" type="submit" :aria-label="t('common.search')"><i class="bi bi-search" aria-hidden="true"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div id="liste" class="row g-4" :aria-busy="switching || loading ? 'true' : 'false'">
            <template v-if="switching">
                <div v-for="n in 3" :key="`s${n}`" class="col-sm-6 col-xl-4"><XmSkeleton type="card" :label="t('microcopy.loading.more')" /></div>
            </template>
            <template v-else>
                <GroupCard v-for="group in items" :key="group.id" :group="group" :busy="busy === group.id" @join="join" @cancel="cancelRequest" />
                <div v-if="items.length === 0" class="col-12"><div class="card card-body text-center text-secondary">{{ emptyText }}</div></div>
            </template>
        </div>
        <div v-if="!switching && hasMore" class="xm-load-more" data-xm-more>
            <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>

    <component :is="CreateGroupModal" v-if="creating && CreateGroupModal" :options="create" @created="onCreated" @hidden="creating = false" />
</template>
