<script setup lang="ts">
// Page `/groupes/{ulid}` (lot groupes-pages, P8-02 ; prototype group-details.php), page Inertia `Community/GroupShow`
// dans le gabarit membre (colonne col-md-8 col-lg-6 + colonne droite col-lg-3 : le sidenav du gabarit remplace la
// pleine largeur col-lg-8 / col-lg-4 du prototype). Carte d'en-tête : couverture 240 px, nom, Groupe public / privé,
// membres, zone, bouton Rejoindre le groupe / Demander à rejoindre / Demande envoyée / Membre – Quitter (confirmation),
// « Discussion du groupe » (conversation de groupe P8-01, /messages?c=) ; onglets À propos · Publications · Membres
// (?tab=, sans rechargement). Publications : invite « Écrire dans le groupe… » (modale du fil chargée à la demande,
// `group` envoyé à POST posts), cartes <XmPost>, « Charger plus » (API `GET groups/{ulid}/posts`) ; non-membre : « Rejoins
// le groupe pour publier et commenter ». Membres : components/community/GroupMembersPanel.vue. Groupe privé dont la
// personne n'est pas membre : fiche réduite (FR-SOC-05, Q75) — ni description, ni publications, ni membres.
// Props : GroupsPageController::show (title, tab, group, posts, members, events, composer, config).
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { useSessionStore } from '@/stores/session';
import { lazyComponent } from '@/feed/lazy';
import { communityApi } from '@/community/api';
import { useGroupActions } from '@/community/useGroupActions';
import type { ComposerOptions, CursorPage, Post } from '@/feed/types';
import type { Group, GroupMember } from '@/community/types';
import type { EventItem } from '@/components/XmRightColumn.vue';
import GroupMembersPanel from '@/components/community/GroupMembersPanel.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmModal from '@/components/XmModal.vue';
import XmPost from '@/components/XmPost.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    tab?: string;
    group: Group;
    posts?: CursorPage<Post> | null;
    members?: { data: GroupMember[]; hidden_count: number } | null;
    events?: EventItem[];
    composer?: ComposerOptions | null;
}>(), { tab: 'about', posts: null, members: null, events: () => [], composer: null });

type Tab = 'about' | 'posts' | 'members';

const { t } = useI18n();
const toast = useToast();
const session = useSessionStore();
const api = communityApi(useApi());

const current = ref<Group>({ ...props.group });
const active = ref<Tab>((['about', 'posts', 'members'].includes(props.tab) ? props.tab : 'about') as Tab);
const items = ref<Post[]>([...(props.posts?.data ?? [])]);
const cursor = ref<string | null>(props.posts?.meta.next_cursor ?? null);
const loading = ref(false);
const announce = ref('');
const leaving = ref(false);
const leaveModal = ref<InstanceType<typeof XmModal> | null>(null);
const writing = ref(false);

const postModal = lazyComponent(() => import('@/components/feed/CreatePostModal.vue'));
const CreatePostModal = postModal.component;

watch(() => props.group, (group) => { current.value = { ...group }; });
watch(() => props.posts, (page) => {
    items.value = [...(page?.data ?? [])];
    cursor.value = page?.meta.next_cursor ?? null;
});

const isPublic = computed(() => current.value.privacy === 'public');
const member = computed(() => current.value.membership?.status === 'actif');
const requested = computed(() => current.value.membership?.status === 'demande');
const readable = computed(() => !current.value.restricted);
const me = computed(() => session.user);
const allGroups = route('groupes.index', undefined, '/groupes');
const tabs = computed<Tab[]>(() => (readable.value ? ['about', 'posts', 'members'] : ['about']));
const tabHref = (tab: Tab) => `${current.value.url}${tab === 'about' ? '' : `?tab=${tab}`}`;
const sidebarMembers = computed(() => props.members?.data ?? []);

const { busy, join, cancelRequest, leave, discuss } = useGroupActions((group) => { current.value = group; });

function selectTab(event: MouseEvent, tab: Tab) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    active.value = tab;
    if (typeof window !== 'undefined') window.history.replaceState(window.history.state, '', tabHref(tab));
}

/** Contenu réservé aux membres relu après une adhésion ou un départ (visite partielle Inertia, même adresse). */
const refresh = () => router.reload({ only: ['group', 'posts', 'members', 'events', 'composer'] });

async function onJoin() {
    const updated = await join(current.value);
    if (updated?.membership?.status === 'actif') refresh();
}

async function confirmLeave() {
    const updated = await leave(current.value);
    if (!updated) return;
    leaveModal.value?.close();
    refresh();
}

async function loadMore() {
    if (loading.value || cursor.value === null) return;
    loading.value = true;
    try {
        const page = await api.posts(current.value.id, cursor.value);
        const known = new Set(items.value.map((post) => post.id));
        const fresh = page.data.filter((post) => !known.has(post.id));
        items.value = [...items.value, ...fresh];
        cursor.value = page.meta.next_cursor ?? null;
        announce.value = t('feed.list.loaded', { count: fresh.length }, fresh.length);
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
}

async function write() {
    if (!props.composer) return;
    writing.value = true;
    if (!(await postModal.load())) {
        writing.value = false;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

const onPublished = (post: Post) => { items.value = [post, ...items.value.filter((item) => item.id !== post.id)]; };
const replacePost = (post: Post) => { items.value = items.value.map((item) => (item.id === post.id ? post : item)); };
const onRemoved = (id: string, reason: 'deleted' | 'unsaved') => { if (reason === 'deleted') items.value = items.value.filter((post) => post.id !== id); };
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <div class="card overflow-hidden">
            <div class="xm-group-cover"><img class="xm-img-cover" :src="current.cover_url" :alt="t('community.groups.cover_alt', { name: current.name })" decoding="async" fetchpriority="high"></div>
            <div class="card-body">
                <div class="d-md-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="h4 mb-1">{{ current.name }}</h1>
                        <ul class="nav nav-divider small text-secondary">
                            <li class="nav-item"><i :class="`bi bi-${isPublic ? 'globe2' : 'lock'} me-1`" aria-hidden="true"></i>{{ t(isPublic ? 'community.groups.detail.kind_public' : 'community.groups.detail.kind_private') }}</li>
                            <li class="nav-item"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ t('community.groups.members', { count: current.members_count }, current.members_count) }}</li>
                            <li class="nav-item"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>{{ current.zone_label }}</li>
                        </ul>
                    </div>
                    <div class="mt-3 mt-md-0 d-flex gap-2">
                        <button v-if="member" class="btn btn-success-soft" type="button" :disabled="busy !== null" aria-haspopup="dialog" @click="leaving = true"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ t('community.groups.actions.member_leave') }}</button>
                        <button v-else-if="requested" class="btn btn-light" type="button" :disabled="busy !== null" :aria-busy="busy ? 'true' : undefined" @click="cancelRequest(current)"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ t('community.groups.actions.requested') }}<span class="visually-hidden"> – {{ t('community.groups.actions.cancel_request') }}</span></button>
                        <button v-else-if="current.can.join" class="btn btn-primary" type="button" :disabled="busy !== null" :aria-busy="busy ? 'true' : undefined" @click="onJoin"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ isPublic ? t('community.groups.actions.join_group') : t('community.groups.actions.request') }}</button>
                        <button v-if="current.can.discuss" class="btn btn-light" type="button" :disabled="busy !== null" :title="t('community.groups.detail.discussion')" :aria-label="t('community.groups.detail.discussion')" @click="discuss(current)"><i class="bi bi-chat-dots" aria-hidden="true"></i></button>
                    </div>
                </div>
            </div>
            <div class="card-footer pt-0 border-0">
                <ul class="nav nav-pills nav-fill small gap-1 xm-community-tabs">
                    <li v-for="key in tabs" :key="key" class="nav-item">
                        <a :class="['nav-link', active === key ? 'active' : 'bg-light']" :href="tabHref(key)" :aria-current="active === key ? 'page' : undefined" @click="selectTab($event, key)">{{ t(`community.groups.detail.${key}`) }}<span v-if="key === 'members' && current.pending_requests" class="badge bg-warning text-dark ms-1">{{ current.pending_requests }}<span class="visually-hidden"> {{ t('community.groups.detail.requests') }}</span></span></a>
                    </li>
                </ul>
            </div>
        </div>

        <template v-if="active === 'about' || !readable">
            <div class="card card-body">
                <h2 class="h6">{{ t('community.groups.detail.about') }}</h2>
                <p v-if="current.description" class="xm-group-description-full">{{ current.description }}</p>
                <p class="small text-secondary mb-0">{{ t('community.groups.detail.charter') }}</p>
            </div>
            <div v-if="!readable" class="alert alert-info mb-0"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ requested ? t('community.groups.detail.pending') : t('community.groups.detail.private_hidden') }}</div>
        </template>

        <section v-else-if="active === 'posts'" class="vstack gap-4" aria-labelledby="groupPostsTitle">
            <h2 id="groupPostsTitle" class="visually-hidden">{{ t('community.groups.detail.posts') }}</h2>
            <div v-if="composer" class="card card-body">
                <div class="d-flex">
                    <XmAvatar class="me-2" size="xs" :name="me?.pseudo ?? ''" :src="me?.avatar_url" />
                    <button class="form-control text-start text-secondary border-0 bg-light" type="button" aria-haspopup="dialog" @click="write" @pointerenter="postModal.load()" @focus="postModal.load()">{{ t('community.groups.detail.write') }}</button>
                </div>
            </div>
            <div v-else class="alert alert-info mb-0">{{ t('community.groups.detail.join_to_post') }}</div>
            <div class="vstack gap-4" :aria-busy="loading ? 'true' : 'false'">
                <XmPost v-for="post in items" :key="post.id" :post="post" @update:post="replacePost" @removed="onRemoved" />
                <div v-if="items.length === 0" class="card card-body text-center text-secondary">{{ t('community.groups.detail.posts_empty') }}</div>
                <XmSkeleton v-if="loading" type="card" :label="t('microcopy.loading.more')" />
            </div>
            <div v-if="cursor !== null" class="xm-load-more">
                <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
            </div>
            <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
        </section>

        <GroupMembersPanel v-else :group="current" @updated="current = $event" />
    </div>

    <aside class="col-lg-3" :aria-label="t('community.groups.detail.aside')">
        <div class="row g-4">
            <div v-if="readable && sidebarMembers.length" class="col-sm-6 col-lg-12">
                <div class="card">
                    <div class="card-header pb-0 border-0 d-flex justify-content-between"><h2 class="h5 card-title mb-0">{{ t('community.groups.detail.members') }}</h2><a class="small" :href="tabHref('members')" @click="selectTab($event, 'members')">{{ t('community.groups.detail.see_all') }}</a></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <XmAvatar v-for="person in sidebarMembers" :key="person.id" :name="person.pseudo" :src="person.avatar_url" :title="person.pseudo" />
                        </div>
                    </div>
                </div>
            </div>
            <div v-if="events.length" class="col-sm-6 col-lg-12">
                <div class="card">
                    <div class="card-header pb-0 border-0"><h2 class="h5 card-title mb-0">{{ t('community.groups.detail.events') }}</h2></div>
                    <div class="card-body">
                        <div v-for="event in events" :key="event.id" class="d-flex mb-3">
                            <div class="avatar"><span class="avatar-img rounded-2 bg-light d-flex align-items-center justify-content-center"><i class="bi bi-calendar-event text-primary" aria-hidden="true"></i></span></div>
                            <div class="ms-2"><h3 class="h6 card-title mb-0"><Link :href="event.href">{{ event.title }}</Link></h3><p v-if="event.meta" class="small mb-0">{{ event.meta }}</p></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12"><Link class="btn btn-light w-100" :href="allGroups"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ t('community.groups.detail.all_groups') }}</Link></div>
        </div>
    </aside>

    <XmModal v-if="leaving" ref="leaveModal" id="modalLeaveGroup" :title="t('community.groups.detail.leave_title')" type="confirm" :open="true" :action="t('community.groups.actions.leave')" :busy="busy !== null" @action="confirmLeave" @hidden="leaving = false">
        <p class="mb-0">{{ isPublic ? t('community.groups.detail.leave_body') : t('community.groups.detail.leave_private_body') }}</p>
    </XmModal>
    <component :is="CreatePostModal" v-if="writing && CreatePostModal && composer" :options="composer" :focus="null" @published="onPublished" @hidden="writing = false" />
</template>
