<script setup lang="ts">
// Page `/forums` (lot forums, P8-05 ; prototype forums.php), page Inertia `Forums/Index` chargée à la demande par app.ts
// (import.meta.glob, aucun import statique) dans le gabarit membre (col-md-8 col-lg-6 + colonne droite) : en-tête
// « Forums » + « Nouveau sujet » (modale chargée à la demande, components/forums/CreateTopicModal.vue), cartes des
// 5 forums (« N sujets · M messages », filtre ?forum= en visite partielle Inertia `only: topics, forum, q`, adresse
// gardée), « Sujets récents » (– forum filtré + « Tous les forums »), recherche, liste (avatar, titre, auteur·rice ou
// « Membre anonyme » · forum · « Dernière réponse il y a… » ou, sans réponse, il y a… : la liste est triée par dernière
// activité, Q78), réponses et vues, « Charger plus » (API `GET forums/topics?page=`), état vide.
// Props : ForumsPageController::index (title, forum, q, search_max, forums, topics, create, events).
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { lazyComponent } from '@/feed/lazy';
import { timeAgo } from '@/feed/format';
import { forumsApi } from '@/forums/api';
import type { Forum, ForumTopic, PagedList, TopicCreateOptions } from '@/forums/types';
import type { EventItem } from '@/components/XmRightColumn.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmRightColumn from '@/components/XmRightColumn.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    forum?: string | null;
    q?: string | null;
    search_max?: number;
    forums: Forum[];
    topics: PagedList<ForumTopic>;
    create: TopicCreateOptions;
    events?: EventItem[];
}>(), { forum: null, q: null, search_max: 60, events: () => [] });

const { t } = useI18n();
const toast = useToast();
const api = forumsApi(useApi());
const items = ref<ForumTopic[]>([...props.topics.data]);
const page = ref(props.topics.meta.current_page);
const lastPage = ref(props.topics.meta.last_page);
const loading = ref(false);
const switching = ref(false);
const announce = ref('');
const search = ref(props.q ?? '');
const creating = ref(false);

const modal = lazyComponent(() => import('@/components/forums/CreateTopicModal.vue'));
const CreateTopicModal = modal.component;

const base = route('forums.index', undefined, '/forums');
const current = computed(() => props.forums.find((item) => item.key === props.forum) ?? null);
const hasMore = computed(() => page.value < lastPage.value);
const emptyText = computed(() => (props.q ? t('community.forums.empty_search') : t('community.forums.empty')));
const activity = (topic: ForumTopic) => (topic.last_reply_at ? t('community.forums.last_reply', { ago: timeAgo(topic.last_reply_at, t) }) : timeAgo(topic.created_at, t));
const meta = (topic: ForumTopic) => [topic.author.pseudo, topic.forum?.name, activity(topic)].filter(Boolean).join(` ${t('common.separator')} `);

watch(() => props.topics, (list) => {
    items.value = [...list.data];
    page.value = list.meta.current_page;
    lastPage.value = list.meta.last_page;
    announce.value = '';
});
watch(() => props.q, (value) => { search.value = value ?? ''; });

function visit(params: Record<string, string>) {
    router.get(base, params, {
        only: ['topics', 'forum', 'q'],
        preserveState: true,
        preserveScroll: true,
        onStart: () => { switching.value = true; },
        onFinish: () => { switching.value = false; },
    });
}

function selectForum(event: MouseEvent, key: string | null) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (key === props.forum || switching.value) return;
    visit({ ...(key ? { forum: key } : {}), ...(props.q ? { q: props.q } : {}) });
}

function onSearch() {
    const q = search.value.trim();
    visit({ ...(props.forum ? { forum: props.forum } : {}), ...(q ? { q } : {}) });
}

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const next = await api.topics({ forum: props.forum, q: props.q, page: page.value + 1 });
        const known = new Set(items.value.map((topic) => topic.id));
        const fresh = next.data.filter((topic) => !known.has(topic.id));
        items.value = [...items.value, ...fresh];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
        announce.value = t('community.forums.loaded', { count: fresh.length }, fresh.length);
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

function onCreated(topic: ForumTopic) {
    toast.show(t('community.forums.toasts.topic_created'), 'success');
    router.visit(topic.url);
}
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <div class="card card-body">
            <div class="d-sm-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1">{{ t('community.forums.title') }}</h1>
                    <p class="small text-secondary mb-0">{{ t('community.forums.intro') }}</p>
                </div>
                <button v-if="create.can" class="btn btn-primary mt-3 mt-sm-0" type="button" aria-haspopup="dialog" @click="openCreate" @pointerenter="modal.load()"><i class="bi bi-plus-circle me-1" aria-hidden="true"></i>{{ t('community.forums.create.title') }}</button>
            </div>
        </div>

        <div class="row g-3">
            <div v-for="item in forums" :key="item.key" class="col-sm-6">
                <a :class="['card card-body h-100 xm-card-hover text-decoration-none', { 'border-primary': item.key === forum }]" :href="item.url" :aria-current="item.key === forum ? 'page' : undefined" @click="selectForum($event, item.key)">
                    <div class="d-flex gap-3">
                        <span class="xm-feature-icon"><i :class="`bi bi-${item.icon} fs-4 text-primary`" aria-hidden="true"></i></span>
                        <div>
                            <h2 class="h6 mb-1">{{ item.name }}</h2>
                            <p class="small text-secondary mb-1">{{ item.description }}</p>
                            <p class="small mb-0">{{ t('community.forums.topics', { count: item.topics_count }, item.topics_count) }} {{ t('common.separator') }} {{ t('community.forums.messages', { count: item.posts_count }, item.posts_count) }}</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-0 d-md-flex justify-content-between align-items-center">
                <h2 class="h6 mb-2 mb-md-0">
                    <template v-if="current">{{ t('community.forums.recent_in', { forum: current.name }) }} <a class="small ms-2" :href="base" @click="selectForum($event, null)">{{ t('community.forums.all') }}</a></template>
                    <template v-else>{{ t('community.forums.recent') }}</template>
                </h2>
                <form role="search" @submit.prevent="onSearch">
                    <div class="input-group input-group-sm">
                        <input v-model="search" class="form-control" type="search" name="q" :maxlength="search_max" :placeholder="t('community.forums.search')" :aria-label="t('community.forums.search_label')">
                        <button class="btn btn-primary" type="submit" :aria-label="t('common.search')"><i class="bi bi-search" aria-hidden="true"></i></button>
                    </div>
                </form>
            </div>
            <div id="liste" class="list-group list-group-flush" :aria-busy="switching || loading ? 'true' : 'false'">
                <div v-if="switching" class="list-group-item"><XmSkeleton :lines="3" :label="t('microcopy.loading.more')" /></div>
                <template v-else>
                    <Link v-for="topic in items" :key="topic.id" class="list-group-item list-group-item-action d-flex gap-3 py-3" :href="topic.url">
                        <XmAvatar class="flex-shrink-0" :name="topic.author.pseudo" :src="topic.author.avatar_url" />
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-bold text-break">
                                {{ topic.title }}
                                <span v-if="topic.is_pinned" class="badge bg-primary bg-opacity-10 text-primary ms-1"><i class="bi bi-pin-angle me-1" aria-hidden="true"></i>{{ t('community.forums.pinned') }}</span>
                                <span v-if="topic.is_locked" class="badge bg-light text-secondary ms-1"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ t('community.forums.locked') }}</span>
                            </div>
                            <div class="small text-secondary">{{ meta(topic) }}</div>
                        </div>
                        <div class="text-end small flex-shrink-0">
                            <span class="d-block"><i class="bi bi-chat me-1" aria-hidden="true"></i><span aria-hidden="true">{{ topic.replies_count }}</span><span class="visually-hidden">{{ t('community.forums.replies', { count: topic.replies_count }, topic.replies_count) }}</span></span>
                            <span class="d-block text-secondary"><i class="bi bi-eye me-1" aria-hidden="true"></i><span aria-hidden="true">{{ topic.views_count }}</span><span class="visually-hidden">{{ t('community.forums.views', { count: topic.views_count }, topic.views_count) }}</span></span>
                        </div>
                    </Link>
                    <div v-if="items.length === 0" class="list-group-item text-center text-secondary py-4">{{ emptyText }}</div>
                </template>
            </div>
        </div>
        <div v-if="!switching && hasMore" class="xm-load-more" data-xm-more>
            <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>

    <XmRightColumn :events="events" />

    <component :is="CreateTopicModal" v-if="creating && CreateTopicModal" :options="create" :forum="forum" @created="onCreated" @hidden="creating = false" />
</template>
