<script setup lang="ts">
// Page `/espace-pair` (lot espace-pair, P9-03 ; prototype espace-pair.php), page Inertia `PeerSpace/Home` chargée à la
// demande par app.ts dans le gabarit membre (col-lg-9) : bandeau de bienvenue avec l'état du réseau et la file de
// synchronisation, 4 KPI de ses dossiers (collectés, ce mois-ci, en cours, clos), raccourcis, tâches du jour et alertes
// routées vers moi. Alertes : résumé (type · lieu, priorité, statut), « Accuser réception » d'un geste, détail dans une
// modale chargée à la demande (récit, contact, position, historique : prendre en charge, clore). Interrogation de
// `GET pair/alerts` toutes les `poll_seconds` secondes si le compte est de garde (pas quand l'onglet est caché ou le
// réseau coupé), sinon à l'ouverture, au retour sur l'onglet et au geste « Actualiser ». « De garde » est relu (visite
// partielle `on_duty`, `poll_seconds`) au retour sur l'onglet et au geste « Actualiser » : une garde qui commence ou finit
// pendant que la page est ouverte démarre ou arrête l'interrogation. Alertes : première page de `per_page` (la taille de
// l'API), total réel = `meta.total` (jamais le nombre d'éléments chargés), « Voir plus » charge la page suivante ; une
// région `role="status"` masquée annonce aux lecteurs d'écran les nouvelles alertes arrivées après une actualisation.
// Props : PeerHomePageController.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { timeAgo } from '@/feed/format';
import { lazyComponent } from '@/feed/lazy';
import { peerApi } from '@/peer/api';
import type { PeerKpi, PeerTask, RoutedAlert, RoutedAlerts } from '@/peer/types';
import { useOutboxStore } from '@/stores/outbox';
import { useSessionStore } from '@/stores/session';
import { useUiStore } from '@/stores/ui';
import XmBadge from '@/components/XmBadge.vue';
import XmButton from '@/components/XmButton.vue';
import XmStat from '@/components/XmStat.vue';

const props = withDefaults(defineProps<{
    title: string;
    kpi: PeerKpi;
    tasks?: PeerTask[];
    alerts: RoutedAlerts;
    per_page?: number;
    max_per_page?: number;
    on_duty?: boolean;
    poll_seconds?: number;
}>(), { tasks: () => [], per_page: 5, max_per_page: 50, on_duty: false, poll_seconds: 0 });

const { t } = useI18n();
const toast = useToast();
const ui = useUiStore();
const outbox = useOutboxStore();
const api = peerApi(useApi());
const session = useSessionStore();
const modal = lazyComponent(() => import('@/components/peer/AlertDetailModal.vue'));
const AlertDetailModal = modal.component;

const items = ref<RoutedAlert[]>([...props.alerts.items]);
const fresh = ref(props.alerts.new);
const open = ref(props.alerts.open);
const refreshing = ref(false);
const loadingMore = ref(false);
const announcement = ref('');
const acting = ref<string | null>(null);
const selected = ref<string | null>(null);
const failed = ref(false);
let timer: ReturnType<typeof setInterval> | null = null;

const name = computed(() => session.pseudo);
const queued = computed(() => outbox.pending);
const shortcuts = computed(() => [
    { key: 'collecte', icon: 'pencil-square', color: 'primary', href: route('collecte', undefined, '/collecte'), inertia: true },
    { key: 'cases', icon: 'folder2-open', color: 'info', href: route('espace-pair.dossiers', undefined, '/espace-pair/dossiers'), inertia: true },
    { key: 'report', icon: 'megaphone', color: 'warning', href: route('signaler', undefined, '/signaler'), inertia: false },
    { key: 'directory', icon: 'person-lines-fill', color: 'info', href: route('annuaire', undefined, '/annuaire'), inertia: true },
    { key: 'ccc', icon: 'collection-play', color: 'success', href: route('ccc', undefined, '/ccc'), inertia: true },
    { key: 'resources', icon: 'book', color: 'secondary', href: route('ressources.index', undefined, '/ressources'), inertia: false },
]);
const taskRows = computed(() => [
    ...props.tasks.map((task) => ({
        key: task.key,
        icon: task.key === 'alerts_to_take' ? 'bell' : 'person-check',
        text: t(`peer.home.tasks.${task.key}`, { count: task.count }, task.count),
        href: task.key === 'alerts_to_take' ? '#alertes' : route('espace-pair.dossiers', undefined, '/espace-pair/dossiers'),
        inertia: task.key !== 'alerts_to_take',
    })),
    { key: 'session', icon: 'images', text: t('peer.home.tasks.session'), href: route('ccc', undefined, '/ccc'), inertia: true },
]);
const remaining = computed(() => Math.max(0, open.value - items.value.length));
const priorityColor = (priority: string): string => (priority === 'critique' ? 'danger' : priority === 'haute' ? 'warning' : 'secondary');

watch(() => props.alerts, (next) => {
    items.value = [...next.items];
    fresh.value = next.new;
    open.value = next.open;
});

/** Annonce (région `role="status"`) les alertes nouvelles apparues depuis la dernière lecture. */
async function announce(next: number) {
    const added = next - fresh.value;
    if (added <= 0) return;
    announcement.value = '';
    await nextTick();
    announcement.value = t('peer.alerts.announce_new', { count: added }, added);
}

/** « De garde » relu côté serveur (visite partielle) : l'interrogation démarre ou s'arrête sans recharger la page. */
function syncDuty() {
    if (!ui.online) return;
    router.reload({ only: ['on_duty', 'poll_seconds'] });
}

async function refresh(silent = true) {
    if (refreshing.value) return;
    refreshing.value = true;
    try {
        // Une seule requête qui couvre tout ce qui est déjà affiché (au plus `max_per_page`), pour que le compteur et la
        // liste restent d'accord.
        const size = Math.min(props.max_per_page, Math.max(props.per_page, items.value.length));
        const next = await api.alerts(silent, 1, size);
        await announce(next.new);
        items.value = next.items;
        fresh.value = next.new;
        open.value = next.total;
        failed.value = false;
    } catch (cause) {
        failed.value = true;
        if (!silent && cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        refreshing.value = false;
    }
}

async function loadMore() {
    if (loadingMore.value || refreshing.value || remaining.value === 0) return;
    loadingMore.value = true;
    try {
        // Page suivante de `per_page` éléments ; le recouvrement éventuel avec la liste actuelle est dédoublonné.
        const page = Math.floor(items.value.length / props.per_page) + 1;
        const next = await api.alerts(false, page, props.per_page);
        const known = new Set(items.value.map((alert) => alert.id));
        items.value = [...items.value, ...next.items.filter((alert) => !known.has(alert.id))];
        fresh.value = next.new;
        open.value = next.total;
        failed.value = false;
    } catch (cause) {
        failed.value = true;
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loadingMore.value = false;
    }
}

function manualRefresh() {
    syncDuty();
    void refresh(false);
}

function schedule() {
    if (timer) clearInterval(timer);
    timer = props.poll_seconds > 0 ? setInterval(tick, props.poll_seconds * 1000) : null;
}

function tick() {
    if (ui.online && (typeof document === 'undefined' || document.visibilityState !== 'hidden')) void refresh();
}

function onVisible() {
    if (document.visibilityState === 'visible') {
        syncDuty();
        tick();
    }
}

async function acknowledge(alert: RoutedAlert) {
    if (acting.value) return;
    acting.value = alert.id;
    try {
        await api.acknowledge(alert.id);
        toast.show(t('peer.alerts.acknowledged'), 'success');
        await refresh();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
        else if (cause instanceof ApiError && cause.status !== 429) toast.show(cause.message, 'danger');
    } finally {
        acting.value = null;
    }
}

async function showDetail(alert: RoutedAlert) {
    await modal.load();
    selected.value = alert.id;
}

watch(() => props.poll_seconds, schedule);

onMounted(() => {
    schedule();
    document.addEventListener('visibilitychange', onVisible);
    void outbox.refresh().catch(() => undefined);
});
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="card overflow-hidden">
            <div class="card-body p-4">
                <XmBadge color="primary" soft class="mb-2">{{ t('peer.home.badge') }}</XmBadge>
                <h1 class="h3">{{ t('peer.home.greeting', { name }) }}</h1>
                <p class="text-secondary mb-2">{{ t('peer.home.intro') }}</p>
                <div class="small d-flex flex-wrap align-items-center gap-2">
                    <XmBadge :color="ui.online ? 'success' : 'warning'" data-peer-online>{{ ui.online ? t('peer.status.online') : t('peer.status.offline') }}</XmBadge>
                    <span data-peer-queue><i class="bi bi-cloud-arrow-up me-1" aria-hidden="true"></i>{{ t('peer.status.queue', { count: queued }, queued) }}</span>
                </div>
            </div>
        </div>

        <section class="row g-3" :aria-label="t('peer.home.kpi_label')">
            <div class="col-6 col-md-3"><XmStat :label="t('peer.home.kpi.collected')" :value="kpi.collected" icon="folder2-open" :href="route('espace-pair.dossiers', undefined, '/espace-pair/dossiers')" inertia /></div>
            <div class="col-6 col-md-3"><XmStat :label="t('peer.home.kpi.this_month')" :value="kpi.this_month" icon="calendar-check" variant="success" /></div>
            <div class="col-6 col-md-3"><XmStat :label="t('peer.home.kpi.in_progress')" :value="kpi.in_progress" icon="hourglass-split" variant="warning" /></div>
            <div class="col-6 col-md-3"><XmStat :label="t('peer.home.kpi.closed')" :value="kpi.closed" icon="check2-circle" variant="success" :href="`${route('espace-pair.dossiers', undefined, '/espace-pair/dossiers')}?status=clos`" inertia /></div>
        </section>

        <nav class="row g-3" :aria-label="t('peer.home.shortcuts_label')">
            <div v-for="shortcut in shortcuts" :key="shortcut.key" class="col-4 col-md-2">
                <component :is="shortcut.inertia ? Link : 'a'" class="card card-body text-center p-2 py-3 h-100 xm-card-hover text-decoration-none" :href="shortcut.href">
                    <i :class="`bi bi-${shortcut.icon} fs-3 text-${shortcut.color}`" aria-hidden="true"></i>
                    <span class="small fw-semibold mt-1">{{ t(`peer.home.shortcuts.${shortcut.key}`) }}</span>
                </component>
            </div>
        </nav>

        <div class="row g-4">
            <div class="col-lg-5">
                <section class="card h-100" aria-labelledby="peerTasks">
                    <div class="card-header"><h2 id="peerTasks" class="h5 mb-0"><i class="bi bi-list-check me-2 text-primary" aria-hidden="true"></i>{{ t('peer.home.tasks.title') }}</h2></div>
                    <ul class="list-group list-group-flush">
                        <li v-for="task in taskRows" :key="task.key" class="list-group-item p-0">
                            <component :is="task.inertia ? Link : 'a'" class="list-group-item-action d-flex align-items-center gap-3 p-3 text-decoration-none text-body" :href="task.href">
                                <i :class="`bi bi-${task.icon} text-primary fs-5`" aria-hidden="true"></i>
                                <span class="small">{{ task.text }}</span>
                                <i class="bi bi-chevron-right ms-auto text-secondary" aria-hidden="true"></i>
                            </component>
                        </li>
                    </ul>
                </section>
            </div>
            <div id="alertes" class="col-lg-7">
                <section class="card h-100" aria-labelledby="peerAlerts">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <h2 id="peerAlerts" class="h5 mb-0"><i class="bi bi-bell me-2 text-danger" aria-hidden="true"></i>{{ t('peer.alerts.title') }}</h2>
                        <span class="d-flex align-items-center gap-2">
                            <XmBadge color="danger" data-peer-alerts-count>{{ open }}<span class="visually-hidden"> {{ t('peer.alerts.open_count', open) }}</span></XmBadge>
                            <XmButton variant="light" size="sm" icon="arrow-clockwise" :loading="refreshing" @click="manualRefresh">{{ t('peer.alerts.refresh') }}</XmButton>
                        </span>
                    </div>
                    <p class="small text-secondary px-3 pt-2 mb-0">
                        <i :class="['bi me-1', on_duty ? 'bi-broadcast' : 'bi-info-circle']" aria-hidden="true"></i>
                        {{ on_duty ? t('peer.alerts.on_duty', { seconds: poll_seconds }) : t('peer.alerts.not_on_duty') }}
                        <template v-if="fresh > 0"> · {{ t('peer.alerts.new_count', { count: fresh }, fresh) }}</template>
                    </p>
                    <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-peer-announce>{{ announcement }}</div>
                    <p v-if="failed" class="small text-warning-emphasis px-3 pt-2 mb-0" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i>{{ t('peer.alerts.stale') }}</p>
                    <ul class="list-group list-group-flush" data-peer-alerts>
                        <li v-for="alert in items" :key="alert.id" class="list-group-item" :data-peer-alert="alert.id">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <span class="fw-semibold small">{{ alert.type_label }} · {{ alert.place || t('peer.alerts.unknown_zone') }}</span>
                                <span class="d-flex gap-1"><XmBadge :color="priorityColor(alert.priority)">{{ alert.priority_label }}</XmBadge><XmBadge color="neutral">{{ alert.status_label }}</XmBadge></span>
                            </div>
                            <div class="small text-secondary"><time :datetime="alert.created_at ?? undefined">{{ t('peer.alerts.ago', { time: timeAgo(alert.created_at, t) }) }}</time></div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <XmButton v-if="!alert.acknowledged" size="sm" variant="primary" icon="check2" :loading="acting === alert.id" :disabled="acting !== null" @click="acknowledge(alert)">{{ t('peer.alerts.acknowledge') }}</XmButton>
                                <XmButton size="sm" variant="outline" icon="eye" :aria-label="t('peer.alerts.detail_for', { type: alert.type_label, place: alert.place || t('peer.alerts.unknown_zone') })" @click="showDetail(alert)">{{ t('peer.alerts.detail') }}</XmButton>
                            </div>
                        </li>
                        <li v-if="items.length === 0" class="list-group-item text-secondary small py-4 text-center">{{ t('peer.alerts.empty') }}</li>
                    </ul>
                    <div v-if="remaining > 0" class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="small text-secondary" data-peer-alerts-shown>{{ t('peer.alerts.shown', { shown: items.length, total: open }) }}</span>
                        <XmButton variant="outline" size="sm" icon="chevron-down" :loading="loadingMore" @click="loadMore">{{ t('peer.alerts.more', { count: remaining }, remaining) }}</XmButton>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <component :is="AlertDetailModal" v-if="AlertDetailModal && selected" :alert-id="selected" @changed="refresh()" @hidden="selected = null" />
</template>
