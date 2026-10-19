<script setup lang="ts">
// Page `/profil/{onglet?}` et `/membres/{pseudo}/{onglet?}` (lot profil, P8-07, P8-08 ; prototype my-profile*.php,
// partials/profile-head.php, x_profile_side()), page Inertia `Member/Profile` chargée à la demande par app.ts
// (import.meta.glob) dans le gabarit membre : la grille 2/3 + 1/3 du prototype (col-lg-8 + col-lg-4 sans sidenav,
// Q24 a) devient col-md-8 col-lg-6 + col-md-4 col-lg-3 après le sidenav col-lg-3 du gabarit, comme les autres écrans
// membres. En-tête <ProfileHeader> (six onglets en <Link>, données de l'onglet seulement : `panel`), colonne
// <ProfileSide>. Publications : invite « Partage tes pensées… » sur son profil (modale du fil à la demande), cartes
// <XmPost>, « Charger plus » (API `GET users/{id}/posts`). Bandeau du profil progressif (P8-08) chargé à la demande sur
// son profil tant qu'un groupe reste à traiter ; « Plus tard » le masque pour la session.
// Props : ProfilePageController (title, tab, tabs, profile, panel, side, composer, report_reasons, progressive, links).
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { lazyComponent } from '@/feed/lazy';
import { provideReportReasons } from '@/feed/reportReasons';
import { profileApi, type ProfileImages, type SelfProfile } from '@/profile/api';
import type { ComposerOptions, CursorPage, Option, Post } from '@/feed/types';
import type { EventItem } from '@/events/types';
import type {
    ActivityEntry, ConnectionsPage, MemberProfile, ProfileLinks, ProfileMediaItem, ProfileSide, ProfileTab, ProfileTabLink, ProgressiveProps,
} from '@/profile/types';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import ProfileSideColumn from '@/components/profile/ProfileSide.vue';
import ProfileAbout from '@/components/profile/ProfileAbout.vue';
import ProfileConnections from '@/components/profile/ProfileConnections.vue';
import ProfileMedia from '@/components/profile/ProfileMedia.vue';
import ProfileEvents from '@/components/profile/ProfileEvents.vue';
import ProfileActivity from '@/components/profile/ProfileActivity.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmPost from '@/components/XmPost.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    tab?: ProfileTab;
    tabs: ProfileTabLink[];
    profile: MemberProfile;
    panel?: Record<string, unknown> | null;
    side?: ProfileSide;
    composer?: ComposerOptions | null;
    report_reasons?: Option[];
    progressive?: ProgressiveProps | null;
    links: ProfileLinks;
}>(), {
    tab: 'posts', panel: null, side: () => ({ media: [], connections: [] }), composer: null, report_reasons: () => [], progressive: null,
});

const DISMISS_KEY = 'xm_profile_banner_dismissed';

const { t } = useI18n();
const toast = useToast();
const api = profileApi(useApi());
provideReportReasons(props.report_reasons);

const current = ref<MemberProfile>({ ...props.profile });
const posts = ref<Post[]>([]);
const cursor = ref<string | null>(null);
const loading = ref(false);
const announce = ref('');
const writing = ref(false);
const bannerHidden = ref(readDismissed());

const postModal = lazyComponent(() => import('@/components/feed/CreatePostModal.vue'));
const CreatePostModal = postModal.component;
const banner = lazyComponent(() => import('@/components/profile/ProgressiveProfileBanner.vue'));
const ProgressiveProfileBanner = banner.component;

function readDismissed(): boolean {
    try {
        return window.sessionStorage.getItem(DISMISS_KEY) === '1';
    } catch {
        return false;
    }
}

function syncPosts() {
    const page = props.tab === 'posts' ? (props.panel as CursorPage<Post> | null) : null;
    posts.value = [...(page?.data ?? [])];
    cursor.value = page?.meta.next_cursor ?? null;
}

syncPosts();
watch(() => props.profile, (profile) => { current.value = { ...profile }; });
watch(() => [props.tab, props.panel], syncPosts);

const showBanner = computed(() => current.value.is_self && props.progressive !== null && !bannerHidden.value);
watch(showBanner, (show) => { if (show) banner.load(); }, { immediate: true });

const connections = computed(() => (props.tab === 'connections' ? (props.panel as unknown as ConnectionsPage | null) : null));
const media = computed(() => (props.tab === 'media' ? ((props.panel?.data ?? []) as ProfileMediaItem[]) : []));
const events = computed(() => (props.tab === 'events' ? ((props.panel?.data ?? []) as EventItem[]) : []));
const suggestions = computed(() => (props.tab === 'events' ? ((props.panel?.suggestions ?? []) as EventItem[]) : []));
const activity = computed(() => (props.tab === 'activity' ? ((props.panel?.data ?? []) as ActivityEntry[]) : []));
const connectionsAction = computed(() => props.tabs.find((tab) => tab.key === 'connections')?.href ?? current.value.url);

function dismissBanner() {
    bannerHidden.value = true;
    try {
        window.sessionStorage.setItem(DISMISS_KEY, '1');
    } catch {
        // Stockage indisponible : le bandeau reviendra à la prochaine visite.
    }
}

function reopenBanner() {
    bannerHidden.value = false;
    try {
        window.sessionStorage.removeItem(DISMISS_KEY);
    } catch {
        // Rien à faire.
    }
}

const onProgress = (profile: SelfProfile) => {
    current.value = { ...current.value, pronouns: profile.pronouns, completion: profile.completion };
};

const onImages = (images: ProfileImages) => {
    current.value = { ...current.value, ...images };
};

async function loadMore() {
    if (loading.value || cursor.value === null) return;
    loading.value = true;
    try {
        const page = await api.posts(current.value.id, cursor.value);
        const known = new Set(posts.value.map((post) => post.id));
        const fresh = page.data.filter((post) => !known.has(post.id));
        posts.value = [...posts.value, ...fresh];
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

const onPublished = (post: Post) => {
    posts.value = [post, ...posts.value.filter((item) => item.id !== post.id)];
    current.value = { ...current.value, stats: { ...current.value.stats, posts: current.value.stats.posts + 1 } };
};
const replacePost = (post: Post) => { posts.value = posts.value.map((item) => (item.id === post.id ? post : item)); };
const onRemoved = (id: string, reason: 'deleted' | 'unsaved') => { if (reason === 'deleted') posts.value = posts.value.filter((post) => post.id !== id); };

const tabLinks = computed(() => props.tabs.map((tab) => (tab.key === 'posts' ? { ...tab, count: current.value.stats.posts } : tab)));
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <component :is="ProgressiveProfileBanner" v-if="showBanner && ProgressiveProfileBanner && progressive" :progressive="progressive" @updated="onProgress" @dismiss="dismissBanner" />

        <ProfileHeader :profile="current" :tabs="tabLinks" :active="tab" :links="links" @images="onImages" />

        <section v-if="tab === 'posts'" class="vstack gap-4" aria-labelledby="profilePostsTitle">
            <h2 id="profilePostsTitle" class="visually-hidden">{{ t('profile.tabs.posts') }}</h2>
            <div v-if="composer" class="card card-body">
                <div class="d-flex">
                    <XmAvatar class="me-2" size="xs" :name="current.pseudo" :src="current.avatar_url" />
                    <button class="form-control text-start text-secondary border-0 bg-light" type="button" aria-haspopup="dialog" @click="write" @pointerenter="postModal.load()" @focus="postModal.load()">{{ t('profile.composer_prompt', { name: current.pseudo }) }}</button>
                </div>
            </div>
            <div class="vstack gap-4" :aria-busy="loading ? 'true' : 'false'">
                <XmPost v-for="post in posts" :key="post.id" :post="post" @update:post="replacePost" @removed="onRemoved" />
                <div v-if="posts.length === 0" class="card card-body text-center text-secondary">
                    <i class="bi bi-chat-square-heart fs-2 mb-2" aria-hidden="true"></i>{{ current.is_self ? t('profile.empty.posts') : t('profile.empty.posts_other', { name: current.pseudo }) }}
                </div>
                <XmSkeleton v-if="loading" type="card" :label="t('microcopy.loading.more')" />
            </div>
            <div v-if="cursor !== null" class="xm-load-more">
                <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
            </div>
            <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
        </section>

        <ProfileAbout v-else-if="tab === 'about'" :profile="current" :links="links" :can-complete="current.is_self && progressive !== null && bannerHidden" @complete="reopenBanner" />
        <ProfileConnections v-else-if="tab === 'connections' && connections" :profile="current" :page="connections" :links="links" :action="connectionsAction" />
        <ProfileMedia v-else-if="tab === 'media'" :profile="current" :items="media" :links="links" />
        <ProfileEvents v-else-if="tab === 'events'" :profile="current" :events="events" :suggestions="suggestions" :links="links" />
        <ProfileActivity v-else-if="tab === 'activity'" :entries="activity" />
    </div>

    <ProfileSideColumn :profile="current" :side="side" :tabs="tabLinks" />

    <component :is="CreatePostModal" v-if="writing && CreatePostModal && composer" :options="composer" :focus="null" @published="onPublished" @hidden="writing = false" />
</template>
