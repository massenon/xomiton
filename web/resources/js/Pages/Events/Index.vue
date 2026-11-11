<script setup lang="ts">
// Page `/evenements` des membres (lot evenements, P8-04 ; prototype events.php), page Inertia `Events/Index` chargée à
// la demande par app.ts (import.meta.glob) dans le gabarit membre (col-lg-9 à côté du sidenav) : en-tête « Événements »
// + « Créer un événement » (modale chargée à la demande, components/events/CreateEventModal.vue), onglets À venir · Mes
// participations · Passés, puces de catégorie « Tous · … », recherche (visite partielle Inertia `only: events, filter,
// category, q`, adresse ?filter=&cat=&q= gardée, squelettes), cartes <EventCard>, « Charger plus » (API `GET events?page=`),
// état vide. « Je participe ? » / « Je participe » : events/useRsvp.ts (carte mise à jour à la réponse).
// Props : EventsPageController::index (title, filter, category, q, categories, events, create).
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { lazyComponent } from '@/feed/lazy';
import { eventsApi } from '@/events/api';
import { useRsvp } from '@/events/useRsvp';
import type { EventCategory, EventCreateOptions, EventItem, EventPage } from '@/events/types';
import EventCard from '@/components/events/EventCard.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    filter?: string;
    category?: string | null;
    q?: string | null;
    categories?: EventCategory[];
    events: EventPage;
    create: EventCreateOptions;
}>(), { filter: 'upcoming', category: null, q: null, categories: () => [] });

const FILTERS = ['upcoming', 'going', 'past'] as const;
const FILTER_LABEL: Record<string, string> = { upcoming: 'community.events.upcoming', going: 'community.events.mine', past: 'community.events.past' };
const EMPTY_LABEL: Record<string, string> = { upcoming: 'community.events.empty_upcoming', going: 'community.events.empty_mine', past: 'community.events.empty_past' };

const { t } = useI18n();
const toast = useToast();
const api = eventsApi(useApi());
const items = ref<EventItem[]>([...props.events.data]);
const page = ref(props.events.meta.current_page);
const lastPage = ref(props.events.meta.last_page);
const loading = ref(false);
const switching = ref(false);
const announce = ref('');
const search = ref(props.q ?? '');
const creating = ref(false);

const modal = lazyComponent(() => import('@/components/events/CreateEventModal.vue'));
const CreateEventModal = modal.component;

const base = route('evenements.index', undefined, '/evenements');
const hasMore = computed(() => page.value < lastPage.value);
const emptyText = computed(() => (props.category || props.q ? t('community.events.empty') : t(EMPTY_LABEL[props.filter] ?? 'community.events.empty')));

/** Paramètres d'adresse de la page (valeurs par défaut omises). */
const params = (over: { filter?: string; category?: string | null; q?: string | null } = {}) => {
    const next = { filter: props.filter, category: props.category, q: props.q, ...over };
    return {
        ...(next.filter && next.filter !== 'upcoming' ? { filter: next.filter } : {}),
        ...(next.category ? { cat: next.category } : {}),
        ...(next.q ? { q: next.q } : {}),
    } as Record<string, string>;
};
const hrefWith = (over: Parameters<typeof params>[0]) => {
    const text = new URLSearchParams(params(over)).toString();
    return text ? `${base}?${text}` : base;
};

watch(() => props.events, (list) => {
    items.value = [...list.data];
    page.value = list.meta.current_page;
    lastPage.value = list.meta.last_page;
    announce.value = '';
});

const replace = (event: EventItem) => { items.value = items.value.map((item) => (item.id === event.id ? event : item)); };
const { busy, toggle, report } = useRsvp(replace);

function visit(query: Record<string, string>) {
    router.get(base, query, {
        only: ['events', 'filter', 'category', 'q'],
        preserveState: true,
        preserveScroll: true,
        onStart: () => { switching.value = true; },
        onFinish: () => { switching.value = false; },
    });
}

function navigate(event: MouseEvent, over: Parameters<typeof params>[0], active: boolean) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (active || switching.value) return;
    visit(params(over));
}

function onSearch() {
    visit(params({ q: search.value.trim() || null }));
}

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const next = await api.list({ filter: props.filter, category: props.category, q: props.q, page: page.value + 1 });
        const known = new Set(items.value.map((event) => event.id));
        const fresh = next.data.filter((event) => !known.has(event.id));
        items.value = [...items.value, ...fresh];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
        announce.value = t('community.events.loaded', { count: fresh.length }, fresh.length);
    } catch (cause) {
        report(cause);
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

function onCreated(event: EventItem) {
    toast.show(t('community.events.toasts.created'), 'success');
    router.visit(event.url);
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="card card-body">
            <div class="d-sm-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1">{{ t('community.events.title') }}</h1>
                    <p class="small text-secondary mb-0">{{ t('community.events.intro') }}</p>
                </div>
                <button v-if="create.can" class="btn btn-primary mt-3 mt-sm-0" type="button" aria-haspopup="dialog" @click="openCreate" @pointerenter="modal.load()"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>{{ t('community.events.create.title') }}</button>
            </div>
            <hr>
            <ul class="nav nav-pills gap-2 mb-3 xm-community-tabs" :aria-label="t('community.events.tabs_label')">
                <li v-for="key in FILTERS" :key="key" class="nav-item">
                    <a :class="['nav-link py-1 px-3', filter === key ? 'active' : 'bg-light']" :href="hrefWith({ filter: key })" :aria-current="filter === key ? 'page' : undefined" @click="navigate($event, { filter: key }, filter === key)">{{ t(FILTER_LABEL[key]) }}</a>
                </li>
            </ul>
            <div class="d-md-flex justify-content-between gap-3 align-items-center">
                <nav v-if="categories.length" :aria-label="t('community.events.filters_label')" class="mb-3 mb-md-0">
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mb-0">
                        <li><a :class="['btn btn-sm', category ? 'btn-light' : 'btn-primary']" :href="hrefWith({ category: null })" :aria-current="category ? undefined : 'true'" @click="navigate($event, { category: null }, !category)">{{ t('community.events.all') }}</a></li>
                        <li v-for="item in categories" :key="item.slug"><a :class="['btn btn-sm', category === item.slug ? 'btn-primary' : 'btn-light']" :href="hrefWith({ category: item.slug })" :aria-current="category === item.slug ? 'true' : undefined" @click="navigate($event, { category: item.slug }, category === item.slug)">{{ item.label }}</a></li>
                    </ul>
                </nav>
                <form role="search" @submit.prevent="onSearch">
                    <div class="input-group">
                        <input v-model="search" class="form-control" type="search" name="q" :maxlength="60" :placeholder="t('community.events.search')" :aria-label="t('common.search')">
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
                <EventCard v-for="event in items" :key="event.id" :event="event" :busy="busy === event.id" @toggle="toggle" />
                <div v-if="items.length === 0" class="col-12"><div class="card card-body text-center text-secondary">{{ emptyText }}</div></div>
            </template>
        </div>
        <div v-if="!switching && hasMore" class="xm-load-more" data-xm-more>
            <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>

    <component :is="CreateEventModal" v-if="creating && CreateEventModal" :options="create" @created="onCreated" @hidden="creating = false" />
</template>
