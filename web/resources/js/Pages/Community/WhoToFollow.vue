<script setup lang="ts">
// Page `/qui-suivre` (lot qui-suivre, P8-06 ; prototype who-to-follow.php), page Inertia `Community/WhoToFollow` dans le
// gabarit membre (col-lg-9 à côté du sidenav, comme le prototype) : en-tête, onglets Suggestions · Invitations reçues ·
// Invitations envoyées avec compteurs (visite partielle Inertia, adresse ?tab=recues|envoyees gardée), filtres des
// suggestions (pseudonyme, zone, profil ; adresse ?q=&zone=&role= gardée), cartes <PersonCard> (inviter, suivre),
// listes <InvitationItem> (accepter, refuser, annuler), « Charger plus » (API, par curseur `meta.next_cursor` : un geste
// qui retire un élément ne fait rien sauter de la suite). `?invite=<id>` (carte « Qui suivre » du fil) : panneau de
// confirmation, rien n'est envoyé sans le geste « Envoyer l'invitation ». Focus (WCAG 2.4.3) : un élément qui disparaît
// sous le focus (ligne tranchée, panneau fermé, bouton remplacé) le rend à la ligne suivante, sinon au titre.
// Props : WhoToFollowPageController (title, tab, filters, zones, roles, search_max, counts, suggestions, invitations,
// invite, can).
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { networkApi } from '@/network/api';
import { useNetworkActions } from '@/network/useNetworkActions';
import type { Counts, Invitation, InvitePreview, PagedList, PublicRole, Suggestion, Tab } from '@/network/types';
import InvitationItem from '@/components/network/InvitationItem.vue';
import PersonCard from '@/components/network/PersonCard.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

interface Filters {
    q: string | null;
    zone: number | null;
    role: string | null;
}

const props = withDefaults(defineProps<{
    title: string;
    tab?: Tab;
    filters?: Filters;
    zones?: { id: number; name: string }[];
    roles?: string[];
    search_max?: number;
    counts?: Counts;
    suggestions: PagedList<Suggestion>;
    invitations: PagedList<Invitation>;
    invite?: InvitePreview | null;
    can?: { access: boolean; invite: boolean; follow: boolean };
}>(), {
    tab: 'suggestions',
    filters: () => ({ q: null, zone: null, role: null }),
    zones: () => [],
    roles: () => ['membre', 'pair', 'equipe'],
    search_max: 60,
    counts: () => ({ received: 0, sent: 0 }),
    invite: null,
    can: () => ({ access: true, invite: true, follow: true }),
});

const TABS: Tab[] = ['suggestions', 'received', 'sent'];
const TAB_PARAM: Record<Tab, string | null> = { suggestions: null, received: 'recues', sent: 'envoyees' };
/** Valeur `role` de l'adresse (prototype) → rôle public de l'API. */
const ROLE_PUBLIC: Record<string, PublicRole> = { membre: 'member', pair: 'peer', equipe: 'team' };
const ROLE_LABEL: Record<string, string> = { membre: 'member', pair: 'peer', equipe: 'team' };
const RELOAD = ['tab', 'filters', 'counts', 'suggestions', 'invitations', 'invite'];

const { t } = useI18n();
const toast = useToast();
const api = networkApi(useApi());
const base = route('qui-suivre', undefined, '/qui-suivre');

const counts = ref<Counts>({ ...props.counts });
const people = ref<Suggestion[]>([...props.suggestions.data]);
const invitations = ref<Invitation[]>([...props.invitations.data]);
const list = computed(() => (props.tab === 'suggestions' ? props.suggestions : props.invitations));
const nextCursor = ref<string | null>(list.value.meta.next_cursor ?? null);
const invited = ref<Set<number>>(new Set());
const preview = ref<InvitePreview | null>(props.invite);
const loading = ref(false);
const switching = ref(false);
const announce = ref('');
const confirmHeading = ref<HTMLElement | null>(null);
const titleRef = ref<HTMLElement | null>(null);
const listRef = ref<HTMLElement | null>(null);
const q = ref(props.filters.q ?? '');
const zone = ref<string>(props.filters.zone !== null ? String(props.filters.zone) : '');
const role = ref<string>(props.filters.role ?? '');

const hasMore = computed(() => nextCursor.value !== null);
const previewMessage = computed(() => {
    const status = preview.value?.status;
    const name = preview.value?.person?.pseudo ?? '';
    switch (status) {
        case 'pending': return t('community.follow.toasts.already_invited', { name });
        case 'connected': return t('community.follow.errors.connected');
        case 'from_them': return t('community.follow.errors.invited_you');
        case 'unavailable': return t('community.follow.errors.unavailable');
        default: return '';
    }
});

const { busy, invite, follow, accept, refuse, cancel } = useNetworkActions((next) => { counts.value = { ...next }; });

watch(() => props.counts, (next) => { counts.value = { ...next }; });
watch(() => props.invite, (next) => { preview.value = next; });
watch(list, (next) => {
    nextCursor.value = next.meta.next_cursor ?? null;
    announce.value = '';
});
watch(() => props.suggestions, (next) => { people.value = [...next.data]; });
watch(() => props.invitations, (next) => { invitations.value = [...next.data]; });
watch(() => props.filters, (next) => {
    q.value = next.q ?? '';
    zone.value = next.zone !== null ? String(next.zone) : '';
    role.value = next.role ?? '';
});

onMounted(async () => {
    if (preview.value?.status === 'ready') {
        await nextTick();
        confirmHeading.value?.focus();
    }
});

const focusable = (root: Element | null | undefined): HTMLElement | null => root?.querySelector<HTMLElement>('button:not([disabled]), a[href]') ?? null;

/** Rend le focus perdu (élément retiré sous le focus) à `pick()`, sinon au titre de la page. */
async function refocus(pick: () => HTMLElement | null = () => null) {
    await nextTick();
    const active = document.activeElement;
    if (active instanceof HTMLElement && active !== document.body && active.isConnected) return;
    (pick() ?? titleRef.value)?.focus();
}

function href(tab: Tab): string {
    const param = TAB_PARAM[tab];
    return param ? `${base}?tab=${param}` : base;
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

function go(event: MouseEvent, tab: Tab) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (switching.value) return;
    const param = TAB_PARAM[tab];
    visit(param ? { tab: param } : {});
}

function onFilter() {
    const params: Record<string, string> = {};
    if (q.value.trim()) params.q = q.value.trim();
    if (zone.value) params.zone = zone.value;
    if (role.value) params.role = role.value;
    visit(params);
}

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    let firstFresh: string | null = null;
    try {
        if (props.tab === 'suggestions') {
            const next = await api.suggestions({ q: props.filters.q, zone: props.filters.zone, role: props.filters.role ? ROLE_PUBLIC[props.filters.role] ?? null : null, cursor: nextCursor.value });
            const known = new Set(people.value.map((person) => person.id));
            const fresh = next.data.filter((person) => !known.has(person.id));
            people.value = [...people.value, ...fresh];
            nextCursor.value = next.meta.next_cursor ?? null;
            announce.value = t('community.follow.loaded', { count: fresh.length }, fresh.length);
            if (fresh[0]) firstFresh = `[data-xm-person="${fresh[0].id}"]`;
        } else {
            const next = await api.invitations(props.tab, nextCursor.value);
            const known = new Set(invitations.value.map((item) => item.id));
            const fresh = next.data.filter((item) => !known.has(item.id));
            invitations.value = [...invitations.value, ...fresh];
            nextCursor.value = next.meta.next_cursor ?? null;
            if (next.meta.counts) counts.value = { ...next.meta.counts };
            announce.value = t('community.follow.loaded_invitations', { count: fresh.length }, fresh.length);
            if (fresh[0]) firstFresh = `[data-xm-invitation="${fresh[0].id}"]`;
        }
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
    // Dernière page : « Charger plus » disparaît sous le focus, qui passe au premier élément ajouté.
    await refocus(() => (firstFresh ? focusable(document.querySelector(firstFresh)) : null));
}

async function onInvite(person: Suggestion, fromPreview = false) {
    const outcome = await invite(person);
    if (!outcome.ok) return;
    invited.value = new Set([...invited.value, person.id]);
    if (preview.value?.person?.id === person.id) preview.value = null;
    // Le bouton « Envoyer une invitation » (carte) ou le panneau de confirmation viennent de disparaître.
    await refocus(() => (fromPreview ? null : focusable(document.querySelector(`[data-xm-person="${person.id}"]`))));
}

async function onFollow(person: Suggestion) {
    const outcome = await follow(person);
    if (outcome.ok) people.value = people.value.map((item) => (item.id === person.id ? { ...item, following: outcome.value.following } : item));
}

function drop(invitation: Invitation) {
    const index = invitations.value.findIndex((item) => item.id === invitation.id);
    invitations.value = invitations.value.filter((item) => item.id !== invitation.id);
    void refocus(() => {
        const rows = listRef.value ? Array.from(listRef.value.querySelectorAll('[data-xm-invitation]')) : [];
        return rows.length > 0 ? focusable(rows[Math.min(Math.max(index, 0), rows.length - 1)]) : null;
    });
}

async function onAccept(invitation: Invitation) {
    const outcome = await accept(invitation);
    if (outcome.ok || outcome.gone) drop(invitation);
}

async function onRefuse(invitation: Invitation) {
    const outcome = await refuse(invitation);
    if (outcome.ok || outcome.gone) drop(invitation);
}

async function onCancel(invitation: Invitation) {
    const outcome = await cancel(invitation);
    if (outcome.ok) counts.value = { ...counts.value, sent: Math.max(0, counts.value.sent - 1) };
    if (outcome.ok || outcome.gone) drop(invitation);
}

async function sendPreview() {
    const person = preview.value?.person;
    if (person) await onInvite(person, true);
}

function dismissPreview() {
    preview.value = null;
    void refocus();
}
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4">
        <div class="card card-body">
            <h1 ref="titleRef" class="h4 mb-1" tabindex="-1">{{ t('community.follow.title') }}</h1>
            <p class="small text-secondary mb-3">{{ t('community.follow.intro') }}</p>
            <ul class="nav nav-pills gap-2" :aria-label="t('community.follow.tabs_label')">
                <li v-for="key in TABS" :key="key" class="nav-item">
                    <a :class="['nav-link py-1 px-3', tab === key ? 'active' : 'bg-light']" :href="href(key)" :aria-current="tab === key ? 'page' : undefined" @click="go($event, key)">
                        {{ t(`community.follow.tabs.${key}`) }}
                        <template v-if="key !== 'suggestions'">
                            <span :class="['badge ms-1', key === 'received' ? 'bg-danger' : 'bg-secondary']" aria-hidden="true">{{ counts[key] }}</span>
                            <span class="visually-hidden">({{ t('community.follow.pending_count', { count: counts[key] }, counts[key]) }})</span>
                        </template>
                    </a>
                </li>
            </ul>
        </div>

        <section v-if="preview && preview.status === 'ready' && preview.person" class="card card-body" aria-labelledby="xm-invite-confirm">
            <div class="d-sm-flex gap-3 align-items-center">
                <XmAvatar class="mb-2 mb-sm-0" size="lg" :name="preview.person.pseudo" :src="preview.person.avatar_url" />
                <div class="flex-grow-1">
                    <h2 id="xm-invite-confirm" ref="confirmHeading" class="h6 mb-1" tabindex="-1">{{ t('community.follow.confirm.title', { name: preview.person.pseudo }) }}</h2>
                    <p class="small text-secondary mb-0">{{ [preview.person.role_label, preview.person.zone?.name].filter(Boolean).join(' · ') }}</p>
                    <p class="small mb-0 mt-1">{{ t('community.follow.confirm.text', { name: preview.person.pseudo }) }}</p>
                </div>
            </div>
            <p v-if="!can.invite" class="small text-secondary mt-3 mb-0">{{ t('community.follow.verify_to_invite') }}</p>
            <div v-else class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-primary btn-sm" type="button" :disabled="busy.includes(`invite:${preview.person.id}`)" :aria-busy="busy.includes(`invite:${preview.person.id}`) ? 'true' : undefined" @click="sendPreview"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>{{ t('community.follow.confirm.send') }}</button>
                <button class="btn btn-light btn-sm" type="button" @click="dismissPreview">{{ t('community.follow.confirm.dismiss') }}</button>
            </div>
        </section>
        <div v-else-if="preview && previewMessage" class="alert alert-info d-flex align-items-start gap-2 mb-0" role="status">
            <i class="bi bi-info-circle" aria-hidden="true"></i><span class="flex-grow-1">{{ previewMessage }}</span>
            <button type="button" class="btn-close" :aria-label="t('common.close')" @click="dismissPreview"></button>
        </div>

        <div v-if="!can.access" class="card card-body text-center text-secondary">{{ t('community.follow.no_access') }}</div>

        <template v-else-if="tab === 'suggestions'">
            <form class="card card-body" role="search" :aria-label="t('community.follow.filters_label')" @submit.prevent="onFilter">
                <div class="row g-2">
                    <div class="col-md-5"><input v-model="q" class="form-control" type="search" name="q" :maxlength="search_max" :placeholder="t('community.follow.search')" :aria-label="t('community.follow.search_label')"></div>
                    <div class="col-6 col-md-3">
                        <select v-model="zone" class="form-select" name="zone" :aria-label="t('community.follow.filter_zone')">
                            <option value="">{{ t('community.follow.all_zones') }}</option>
                            <option v-for="item in zones" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <select v-model="role" class="form-select" name="role" :aria-label="t('community.follow.filter_role')">
                            <option value="">{{ t('community.follow.all_roles') }}</option>
                            <option v-for="item in roles" :key="item" :value="item">{{ t(`community.follow.roles.${ROLE_LABEL[item] ?? 'member'}`) }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">{{ t('community.follow.filter') }}</button></div>
                </div>
                <p v-if="!can.invite" class="small text-secondary mt-2 mb-0">{{ t('community.follow.verify_to_invite') }}</p>
            </form>
            <div class="row g-3" :aria-busy="switching || loading ? 'true' : 'false'">
                <template v-if="switching">
                    <div v-for="n in 3" :key="`s${n}`" class="col-sm-6 col-xl-4"><XmSkeleton type="card" :label="t('microcopy.loading.more')" /></div>
                </template>
                <template v-else>
                    <PersonCard v-for="person in people" :key="person.id" :person="person" :invited="invited.has(person.id)" :can-invite="can.invite" :busy="busy" @invite="onInvite" @follow="onFollow" />
                    <div v-if="people.length === 0" class="col-12"><div class="card card-body text-center text-secondary">{{ t('community.follow.empty_suggestions') }}</div></div>
                </template>
            </div>
        </template>

        <div v-else class="card" :aria-busy="switching || loading ? 'true' : 'false'">
            <div ref="listRef" class="list-group list-group-flush">
                <template v-if="switching">
                    <div v-for="n in 2" :key="`s${n}`" class="list-group-item py-3"><XmSkeleton type="avatar" :label="t('microcopy.loading.more')" /></div>
                </template>
                <template v-else>
                    <InvitationItem v-for="item in invitations" :key="item.id" :invitation="item" :busy="busy" @accept="onAccept" @refuse="onRefuse" @cancel="onCancel" />
                    <div v-if="invitations.length === 0" class="list-group-item text-center text-secondary py-4">
                        <template v-if="tab === 'received'">{{ t('community.follow.empty_received') }}</template>
                        <template v-else>{{ t('community.follow.empty_sent') }} <a :href="href('suggestions')" @click="go($event, 'suggestions')">{{ t('community.follow.empty_sent_link') }}</a>.</template>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="can.access && !switching && hasMore" class="xm-load-more" data-xm-more>
            <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>
</template>
