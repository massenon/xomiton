<script setup lang="ts">
// Cloche de notifications : même HTML que <x-bell> (bouton bi-bell + badge compteur, panneau Non lus / Lues / Toutes,
// « Tout marquer comme lu », liste .xm-notif-list, état vide, lien de pied ; NOTIFICATIONS_INSTRUCTION §3). Le panneau
// est un dropdown Bootstrap ; le filtre des onglets est tenu par Vue. Émet `read` (id) au clic d'une notification non
// lue et `read-all` ; la mise à jour réelle (store `notifications`, polling `bell_poll_seconds`) est branchée par
// l'en-tête membre (P6-03). items : [{ id, icon, color, text, time, href, read }] ; unread (défaut : calculé) ; href /
// hrefLabel (pied) ; id ; variant : member (nav-link icon-md) | admin (btn-light btn-sm) ; label : nom accessible du
// bouton (défaut « Notifications » ; l'en-tête membre y met le compteur, qu'un aria-label fixe masquerait).
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

export interface BellItem {
    id: string;
    text: string;
    href?: string | null;
    icon?: string | null;
    color?: string | null;
    time?: string | null;
    read?: boolean;
}

const props = withDefaults(defineProps<{
    items?: BellItem[];
    unread?: number | null;
    href?: string | null;
    hrefLabel?: string | null;
    id?: string;
    variant?: 'member' | 'admin';
    label?: string | null;
}>(), { items: () => [], unread: null, href: null, hrefLabel: null, id: 'xmNotif', variant: 'member', label: null });

const emit = defineEmits<{ read: [id: string]; 'read-all': [] }>();

const TABS = ['unread', 'read', 'all'] as const;
type Tab = typeof TABS[number];

const { t } = useI18n();
const tab = ref<Tab>('unread');
const count = computed(() => props.unread ?? props.items.filter((n) => !n.read).length);
const visible = (n: BellItem) => tab.value === 'all' || (tab.value === 'read' ? Boolean(n.read) : !n.read);
const shown = computed(() => props.items.filter(visible));

function open(n: BellItem) {
    if (!n.read) emit('read', n.id);
}
</script>

<template>
    <div :id="id" class="dropdown xm-bell">
        <button :class="variant === 'member' ? 'nav-link bg-light icon-md btn btn-light p-0 position-relative' : 'btn btn-light btn-sm position-relative'" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" :aria-label="label ?? t('components.bell.label')" :title="t('components.bell.label')">
            <i class="bi bi-bell" aria-hidden="true"></i>
            <span :class="['position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger', { 'd-none': !count }]">{{ count }}<span class="visually-hidden"> {{ t('components.bell.unread', count) }}</span></span>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg xm-notif-menu">
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                <strong>{{ t('components.bell.label') }}</strong>
                <button type="button" class="btn btn-link btn-sm p-0" @click="emit('read-all')">{{ t('components.bell.mark_all_read') }}</button>
            </div>
            <ul class="nav nav-pills nav-fill small p-2 gap-1" role="tablist" :aria-label="t('components.bell.filter')">
                <li v-for="key in TABS" :key="key" class="nav-item" role="presentation"><button :class="['nav-link py-1', { active: tab === key }]" type="button" role="tab" :aria-selected="tab === key ? 'true' : 'false'" :aria-controls="`${id}List`" @click="tab = key">{{ t(`components.bell.tabs.${key}`) }}</button></li>
            </ul>
            <div :id="`${id}List`" class="list-group list-group-flush xm-notif-list" role="tabpanel">
                <a v-for="n in shown" :key="n.id" :class="['list-group-item list-group-item-action d-flex gap-2 align-items-start', { 'xm-notif-unread': !n.read }]" :href="n.href ?? '#'" :data-notif-id="n.id" :data-notif-read="n.read ? '1' : '0'" @click="open(n)">
                    <span :class="`icon-md rounded-circle bg-${n.color ?? 'primary'} bg-opacity-10 text-${n.color ?? 'primary'} flex-shrink-0`"><i :class="`bi bi-${n.icon ?? 'bell'}`" aria-hidden="true"></i></span>
                    <span class="small flex-grow-1"><span class="d-block">{{ n.text }}</span><span v-if="n.time" class="text-secondary">{{ n.time }}</span><span v-if="!n.read" class="visually-hidden"> {{ t('components.bell.unread_item') }}</span></span>
                    <span class="xm-notif-dot" aria-hidden="true"></span>
                </a>
                <div :class="['list-group-item text-center text-secondary small', { 'd-none': shown.length > 0 }]">{{ t('components.bell.empty') }}</div>
            </div>
            <div v-if="href" class="p-2 border-top text-center"><a class="small" :href="href">{{ hrefLabel ?? t('components.bell.see_all') }}</a></div>
        </div>
    </div>
</template>
