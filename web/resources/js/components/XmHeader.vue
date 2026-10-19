<script setup lang="ts">
// En-tête de l'espace membre (P6-03) : portage de prototype partials/header.php (et du gabarit Blade provisoire de P3).
// Logo → /fil ; recherche (GET /ressources?q=) ; menus Fil · Apprendre ▾ · Communauté ▾ · Aide & protection ▾ · Espace
// pair (si `peer_space.access`) ; SOS, messages (compteur des conversations non lues), paramètres, cloche <XmBell>
// branchée sur le store `notifications` (API P6-07 : liste chargée à l'ouverture, compteur relu toutes les
// `ui.bell_poll_seconds` secondes, au moins 15 (D7), onglet visible et réseau présent ; lecture enregistrée avant d'ouvrir
// le lien : visite Inertia vers une page membre, chargement complet sinon, jamais un schéma autre que http(s)), avatar
// avec menu (profil, paramètres, aide, administration si `shell.admin_url`, déconnexion par app/logout.ts).
// Accessibilité : <header> + <nav> nommée, lien actif `aria-current="page"`, compteurs dans le nom accessible des
// boutons messages et cloche, hausse du compteur de la cloche annoncée dans une région polite (MASTER.md), menus fermés
// au début de chaque visite Inertia (l'en-tête persiste d'une page à l'autre), cibles de 44 px sur écran tactile
// (resources/css/app/member.css).
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { loadedBootstrap } from '@/shared/bootstrap-lazy.js';
import { logout } from '@/app/logout';
import { route } from '@/app/routes';
import { useApi } from '@/app/useApi';
import { BELL_MIN_SECONDS, HEADER_MENUS, LINKS, allowed, browser, hrefOf, isActive, notificationTarget, type NavLink } from '@/app/memberNav';
import { useNotificationsStore } from '@/stores/notifications';
import { useSessionStore } from '@/stores/session';
import { useUiStore } from '@/stores/ui';
import XmAvatar from './XmAvatar.vue';
import XmBell from './XmBell.vue';
import XmLogo from './XmLogo.vue';

const ROLES = ['membre', 'pair', 'admin', 'superadmin'];

const { t } = useI18n();
const page = usePage();
const session = useSessionStore();
const notifications = useNotificationsStore();
const ui = useUiStore();
const api = useApi();

const root = ref<HTMLElement | null>(null);
const bellItem = ref<HTMLElement | null>(null);

const url = computed(() => page.url ?? '');
const user = computed(() => session.user);
const can = (ability: string) => session.can(ability);
const active = (item: NavLink) => isActive(item, url.value);
const tag = (item: NavLink) => (item.inertia ? Link : 'a');
const menus = HEADER_MENUS;
const fil = { ...LINKS.fil, label: 'menus.member.feed' };
const searchAction = route('ressources.index', undefined, '/ressources');
const roleLabel = computed(() => {
    const role = user.value?.role ?? 'membre';
    return t(`menus.member_roles.${ROLES.includes(role) ? role : 'membre'}`);
});
const messagesLabel = computed(() => t('menus.member_more.messages_label', notifications.messages));
const bellLabel = computed(() => t('menus.member_more.bell_label', notifications.unread));
/** Liens des notifications : schéma autre que http(s) ou relatif écarté (l'adresse vient de l'API). */
const bellItems = computed(() => notifications.bellItems.map((item) => ({ ...item, href: notificationTarget(item.href)?.url ?? null })));

/**
 * Ferme le menu replié (< 992 px) et les menus déroulants ouverts : l'en-tête reste monté pendant la visite. Bootstrap
 * est chargé à la demande (shared/bootstrap-lazy.js) : absent, aucun menu n'a pu s'ouvrir.
 */
function closeMenus() {
    const el = root.value;
    const bootstrap = loadedBootstrap();
    if (!el || !bootstrap) return;
    el.querySelectorAll<HTMLElement>('[data-bs-toggle="dropdown"]').forEach((toggle) => bootstrap.Dropdown.getInstance(toggle)?.hide());
    const collapse = el.querySelector<HTMLElement>('#memberNavbar');
    if (collapse?.classList.contains('show')) bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false }).hide();
}

// Cloche --------------------------------------------------------------------------------------------------------------
let timer: ReturnType<typeof setInterval> | null = null;
let pending: ReturnType<typeof notificationTarget> = null;

/** Région polite : une hausse du compteur (polling, rechargement) est lue, une baisse (lecture) ne l'est pas. */
const bellAnnouncement = ref('');
watch(() => notifications.unread, async (count, previous) => {
    if (!(count > (previous ?? 0))) return;
    bellAnnouncement.value = '';
    await nextTick();
    bellAnnouncement.value = t('menus.member_more.bell_announce', count);
});

const poll = () => {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return;
    if (!ui.online) return;
    notifications.refreshCount(api).catch(() => undefined);
};
const onVisibility = () => { if (document.visibilityState === 'visible') poll(); };
const onBellOpen = () => { notifications.load(api).catch(() => undefined); };

/** Ouvre le lien d'une notification : visite Inertia vers une page membre (gabarit, minuteries et SOS gardés). */
function go(target: ReturnType<typeof notificationTarget>) {
    if (!target) return;
    if (target.kind === 'inertia') router.visit(target.url);
    else browser.assign(target.url);
}

/**
 * Clic simple sur une notification : non lue, la navigation attend l'enregistrement de la lecture (sinon annulée par le
 * départ) ; déjà lue, visite Inertia directe vers une page membre ; sans lien, rien n'est ouvert.
 */
function onBellClick(event: MouseEvent) {
    const anchor = (event.target as Element | null)?.closest?.('a[data-notif-id]') as HTMLAnchorElement | null;
    if (!anchor) return;
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
    const target = notificationTarget(anchor.getAttribute('href'));
    // Sans lien utilisable (« # ») : pas de saut en haut de page ; la lecture est tout de même enregistrée.
    if (!target) {
        event.preventDefault();
        return;
    }
    if (anchor.dataset.notifRead === '0') {
        event.preventDefault();
        pending = target;
    } else if (target.kind === 'inertia') {
        event.preventDefault();
        go(target);
    }
}

const settle = (promise: Promise<unknown>, ms: number) => Promise.race([promise.catch(() => undefined), new Promise((resolve) => setTimeout(resolve, ms))]);

async function onRead(id: string) {
    const target = pending;
    pending = null;
    await settle(notifications.markRead(api, id), 2000);
    go(target);
}

const onReadAll = () => { notifications.markAllRead(api).catch(() => undefined); };

const onLogout = () => { logout(); };

let offStart: (() => void) | null = null;

onMounted(() => {
    const seconds = Math.max(BELL_MIN_SECONDS, Number(session.bellPollSeconds) || 30);
    timer = setInterval(poll, seconds * 1000);
    document.addEventListener('visibilitychange', onVisibility);
    bellItem.value?.addEventListener('show.bs.dropdown', onBellOpen);
    offStart = router.on('start', closeMenus);
});

onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisibility);
    bellItem.value?.removeEventListener('show.bs.dropdown', onBellOpen);
    offStart?.();
});

defineExpose({ poll, closeMenus });
</script>

<template>
    <header id="memberHeader" ref="root" class="navbar-light bg-mode sticky-top shadow-sm no-print header-static">
        <nav class="navbar navbar-expand-lg" :aria-label="t('menus.a11y.member_nav')">
            <div class="container">
                <Link class="navbar-brand" :href="hrefOf(LINKS.fil)" :aria-label="t('components.logo.home')"><XmLogo :link="false" adaptive :width="140" img-class="navbar-brand-item" /></Link>

                <button class="navbar-toggler ms-auto icon-md btn btn-light p-0" type="button" data-bs-toggle="collapse" data-bs-target="#memberNavbar" aria-controls="memberNavbar" aria-expanded="false" :aria-label="t('menus.a11y.open_menu')">
                    <span class="navbar-toggler-animation"><span></span><span></span><span></span></span>
                </button>

                <div id="memberNavbar" class="collapse navbar-collapse">
                    <div class="nav mt-3 mt-lg-0 flex-nowrap align-items-center px-4 px-lg-0">
                        <div class="nav-item w-100">
                            <form class="rounded position-relative" :action="searchAction" method="get" role="search">
                                <label for="memberSearch" class="visually-hidden">{{ t('menus.member.search') }}</label>
                                <input id="memberSearch" class="form-control ps-5 bg-light" type="search" name="q" :placeholder="t('menus.member.search_placeholder')">
                                <button class="btn bg-transparent px-2 py-0 position-absolute top-50 start-0 translate-middle-y" type="submit" :aria-label="t('menus.member.search')"><i class="bi bi-search fs-5" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    </div>

                    <ul class="navbar-nav navbar-nav-scroll ms-auto">
                        <li class="nav-item">
                            <Link :class="['nav-link', { 'active fw-bold': active(fil) }]" :href="hrefOf(fil)" :aria-current="active(fil) ? 'page' : undefined">{{ t(fil.label) }}</Link>
                        </li>
                        <li v-for="menu in menus" :key="menu.id" class="nav-item dropdown">
                            <button :id="menu.id" class="nav-link dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">{{ t(menu.label) }}</button>
                            <ul class="dropdown-menu" :aria-labelledby="menu.id">
                                <template v-for="(entry, index) in menu.entries" :key="entry === 'divider' ? `d${index}` : entry.key">
                                    <li v-if="entry === 'divider'" class="dropdown-divider" role="separator"></li>
                                    <li v-else-if="allowed(entry, can)">
                                        <component :is="tag(entry)" :class="['dropdown-item', { active: active(entry), 'text-danger fw-bold': entry.danger }]" :href="hrefOf(entry)" :aria-current="active(entry) ? 'page' : undefined"><i :class="`bi bi-${entry.icon} xm-fw me-2`" aria-hidden="true"></i>{{ t(entry.label) }}</component>
                                    </li>
                                </template>
                            </ul>
                        </li>
                        <li v-if="can('peer_space.access')" class="nav-item">
                            <Link :class="['nav-link', { 'active fw-bold': active(LINKS.espacePair) }]" :href="hrefOf(LINKS.espacePair)" :aria-current="active(LINKS.espacePair) ? 'page' : undefined">{{ t('menus.member.peer_space') }}</Link>
                        </li>
                    </ul>
                </div>

                <ul class="nav flex-nowrap align-items-center ms-sm-3 list-unstyled">
                    <li class="nav-item ms-2 d-none d-sm-block">
                        <a class="btn btn-danger btn-sm fw-bold" :href="hrefOf(LINKS.sos)" :title="t('common.sos_title')"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ t('common.sos') }}</a>
                    </li>
                    <li class="nav-item ms-2">
                        <Link class="nav-link bg-light icon-md btn btn-light p-0 position-relative" :href="hrefOf(LINKS.messages)" :title="t('menus.member.messages')" :aria-label="messagesLabel" :aria-current="active(LINKS.messages) ? 'page' : undefined">
                            <i class="bi bi-chat-left-text-fill fs-6" aria-hidden="true"></i>
                            <span v-if="notifications.messages > 0" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" aria-hidden="true" data-xm-messages-count>{{ notifications.messages }}</span>
                        </Link>
                    </li>
                    <li class="nav-item ms-2">
                        <Link class="nav-link bg-light icon-md btn btn-light p-0" :href="hrefOf(LINKS.parametres)" :title="t('menus.member.settings')" :aria-label="t('menus.member.settings')" :aria-current="active(LINKS.parametres) ? 'page' : undefined"><i class="bi bi-gear-fill fs-6" aria-hidden="true"></i></Link>
                    </li>
                    <li ref="bellItem" class="nav-item ms-2" @click.capture="onBellClick">
                        <XmBell id="memberBell" variant="member" :items="bellItems" :unread="notifications.unread" :label="bellLabel" :href="hrefOf(LINKS.notifications)" :href-label="t('notifications.page.see_all')" @read="onRead" @read-all="onReadAll" />
                    </li>
                    <li class="nav-item ms-2 dropdown">
                        <button id="profileDropdown" class="nav-link btn icon-md p-0" type="button" data-bs-auto-close="outside" data-bs-display="static" data-bs-toggle="dropdown" aria-expanded="false" :aria-label="t('microcopy.a11y.user_menu')">
                            <img v-if="user?.avatar_url" class="avatar-img rounded-2" :src="user.avatar_url" :alt="t('components.avatar.alt', { name: user.pseudo })" :title="user.pseudo" width="40" height="40">
                            <XmAvatar v-else :name="user?.pseudo ?? ''" size="sm" rounded="2" />
                        </button>
                        <ul class="dropdown-menu dropdown-animation dropdown-menu-end pt-3 small me-md-n3" aria-labelledby="profileDropdown">
                            <li class="px-3">
                                <div class="d-flex align-items-center position-relative">
                                    <XmAvatar :name="user?.pseudo ?? ''" :src="user?.avatar_url" class="me-3" />
                                    <div>
                                        <Link class="h6 stretched-link" :href="hrefOf(LINKS.profil)">{{ user?.pseudo }}</Link>
                                        <p class="small m-0">{{ roleLabel }}</p>
                                    </div>
                                </div>
                                <Link class="dropdown-item btn btn-primary-soft btn-sm my-2 text-center" :href="hrefOf(LINKS.profil)">{{ t('menus.member.view_profile') }}</Link>
                            </li>
                            <li><Link class="dropdown-item" :href="hrefOf(LINKS.parametres)"><i class="bi bi-gear xm-fw me-2" aria-hidden="true"></i>{{ t('menus.member.settings_privacy') }}</Link></li>
                            <li><a class="dropdown-item" :href="hrefOf(LINKS.aide)"><i class="bi bi-life-preserver xm-fw me-2" aria-hidden="true"></i>{{ t('menus.member.help_center') }}</a></li>
                            <li v-if="session.shell?.admin_url"><a class="dropdown-item" :href="session.shell.admin_url"><i class="bi bi-speedometer2 xm-fw me-2" aria-hidden="true"></i>{{ t('menus.member.administration') }}</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button type="button" class="dropdown-item bg-danger-soft-hover" data-xm-logout @click="onLogout"><i class="bi bi-power xm-fw me-2" aria-hidden="true"></i>{{ t('menus.member.logout') }}</button></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </nav>
        <span class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-xm-bell-live>{{ bellAnnouncement }}</span>
    </header>
</template>
