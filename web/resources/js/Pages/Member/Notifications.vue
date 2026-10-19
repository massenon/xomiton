<script setup lang="ts">
// Page `/notifications` (lot parametres-notifications, P8-10 ; prototype notifications.php l.8-9, l.31, l.65), page
// Inertia `Member/Notifications` dans le gabarit membre (colonnes du fil : col-md-8 col-lg-6 + colonne droite) :
// en-tête avec le nombre de non lues, « Tout marquer comme lu » (limité au filtre courant), filtres Toutes ·
// Invitations · Événements · Échanges · Système avec leur badge de non lues (visite partielle Inertia, adresse
// ?filtre= gardée), liste avec « Marquer comme lu » par notification (état lu en base, partagé avec la cloche par le
// store `notifications`), « Afficher plus » (API `?tab=&page=`). Ouvrir une notification non lue la marque lue avant
// de suivre son lien.
// Props : NotificationsPageController (title, tab, tabs, counts, notifications, per_page).
import { computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { route } from '@/app/routes';
import { notificationTarget } from '@/app/memberNav';
import { timeAgo } from '@/feed/format';
import { useNotificationsStore, type NotificationItem, type NotificationPage, type NotificationTab, type TabCounts } from '@/stores/notifications';
import XmButton from '@/components/XmButton.vue';
import XmRightColumn from '@/components/XmRightColumn.vue';

const props = withDefaults(defineProps<{
    title: string;
    tab?: NotificationTab;
    tabs?: { key: NotificationTab; param: string | null }[];
    counts?: Partial<TabCounts>;
    notifications: NotificationPage;
    per_page?: number;
}>(), {
    tab: 'all',
    tabs: () => [
        { key: 'all', param: null }, { key: 'invitations', param: 'invitations' }, { key: 'events', param: 'evenements' },
        { key: 'exchanges', param: 'echanges' }, { key: 'system', param: 'systeme' },
    ],
    counts: () => ({}),
    per_page: 20,
});

const RELOAD = ['tab', 'counts', 'notifications'];
const TONES: Record<string, string> = { invitations: 'primary', events: 'success', exchanges: 'info', system: 'secondary' };

const { t } = useI18n();
const api = useApi();
const store = useNotificationsStore();
const base = route('notifications', undefined, '/notifications');

const hydrate = () => store.hydratePage(props.tab, props.notifications, props.counts);
hydrate();
watch(() => [props.tab, props.notifications, props.counts], hydrate);

const items = computed(() => store.pageItems);
const unread = computed(() => store.tabCounts.all.unread);
const tabUnread = computed(() => store.tabCounts[props.tab]?.unread ?? 0);
const hasMore = computed(() => store.pageCurrent < store.pageLast);
const hrefOf = (param: string | null) => (param ? `${base}?filtre=${param}` : base);
const toneOf = (item: NotificationItem) => TONES[item.tab ?? 'system'] ?? 'secondary';
const ago = (iso: string | null | undefined) => timeAgo(iso ?? null, t);

async function markRead(item: NotificationItem) {
    await store.markPageRead(api, item.id).catch(() => undefined);
}

async function markAll() {
    await store.markPageAllRead(api).catch(() => undefined);
}

async function loadMore() {
    await store.loadMorePage(api, props.per_page).catch(() => undefined);
}

/** Ouvrir une notification non lue : marquée lue d'abord (2 s au plus), puis son lien (Inertia ou page Blade). */
async function open(event: MouseEvent, item: NotificationItem) {
    const target = notificationTarget(item.url);
    if (!target || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
    event.preventDefault();
    if (!item.read) await Promise.race([markRead(item), new Promise((resolve) => setTimeout(resolve, 2000))]);
    if (target.kind === 'inertia') router.visit(target.url);
    else window.location.assign(target.url);
}
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <div class="card card-body">
            <div class="d-sm-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1">{{ title }} <span v-if="unread" class="badge bg-danger" data-xm-unread>{{ unread }}<span class="visually-hidden"> {{ t('notifications.page.unread_badge', { count: unread }, unread) }}</span></span></h1>
                    <p class="small text-secondary mb-0">{{ t('notifications.page.subtitle') }}</p>
                </div>
                <XmButton class="mt-3 mt-sm-0" variant="primary-soft" size="sm" icon="check2-all" :disabled="tabUnread === 0" @click="markAll">{{ t('notifications.page.mark_all_read') }}</XmButton>
            </div>
            <nav class="mt-3" :aria-label="t('notifications.page.filters_label')">
                <ul class="nav nav-pills gap-2">
                    <li v-for="item in tabs" :key="item.key" class="nav-item">
                        <Link
                            class="nav-link py-1 px-3"
                            :class="tab === item.key ? 'active' : 'bg-light'"
                            :href="hrefOf(item.param)"
                            :only="RELOAD"
                            preserve-scroll
                            :aria-current="tab === item.key ? 'page' : undefined"
                        >
                            {{ t(`notifications.page.filters.${item.key}`) }}
                            <span v-if="store.tabCounts[item.key]?.unread" class="badge rounded-pill ms-1" :class="tab === item.key ? 'bg-white text-primary' : 'bg-danger'">{{ store.tabCounts[item.key].unread }}<span class="visually-hidden"> {{ t('notifications.page.unread_badge', { count: store.tabCounts[item.key].unread }, store.tabCounts[item.key].unread) }}</span></span>
                        </Link>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="card">
            <ul class="list-group list-group-flush" data-xm-notifications>
                <li v-for="item in items" :key="item.id" class="list-group-item d-flex gap-3 align-items-start py-3" :class="{ 'bg-primary bg-opacity-10': !item.read }" :data-read="item.read ? '1' : '0'">
                    <span class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center icon-lg bg-opacity-10" :class="[`bg-${toneOf(item)}`, `text-${toneOf(item)}`]" aria-hidden="true"><i class="bi fs-5" :class="`bi-${item.icon || 'bell'}`"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <a v-if="item.url" class="text-body" :href="item.url" @click="open($event, item)"><strong>{{ item.title }}</strong><template v-if="item.body"> {{ item.body }}</template></a>
                        <p v-else class="mb-0"><strong>{{ item.title }}</strong><template v-if="item.body"> {{ item.body }}</template></p>
                        <div class="small text-secondary"><span v-if="!item.read" class="visually-hidden">{{ t('notifications.page.unread') }} · </span>{{ ago(item.created_at) }}</div>
                    </div>
                    <XmButton v-if="!item.read" variant="ghost" size="sm" icon="check2" class="text-secondary p-0" :label="t('notifications.page.mark_read_label', { title: item.title || t('notifications.page.title') })" @click="markRead(item)" />
                </li>
                <li v-if="items.length === 0" class="list-group-item text-center text-secondary py-4">{{ tab === 'all' ? t('notifications.page.empty') : t('notifications.page.empty_filter') }}</li>
            </ul>
            <div v-if="hasMore" class="card-footer text-center">
                <XmButton variant="primary-soft" size="sm" :loading="store.pageLoading" @click="loadMore">{{ t('notifications.page.load_more') }}</XmButton>
            </div>
        </div>
    </div>
    <XmRightColumn />
</template>
