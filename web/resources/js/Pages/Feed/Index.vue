<script setup lang="ts">
// Page `/fil` (lot fil-ecrans, P7-03 ; prototype fil.php), page Inertia `Feed/Index` chargée à la demande par app.ts
// (import.meta.glob) dans le gabarit membre : accès rapides SOS · Signaler · Assistant · Urgences, carte « Créer une
// publication » (modales chargées à la demande, feed/lazy.ts : rien dans le paquet app.ts), « À la une –
// Thématiques », filtres Tout · Témoignages · Questions · Ressources · Événements · Sondages (visite partielle Inertia
// `only: feed, tag`, adresse ?tag= gardée, squelettes pendant le chargement), liste <XmPost> par curseur avec
// « Charger plus » (API `GET feed`), état vide, colonne droite (événements, urgence). Une publication mise en file hors
// connexion et envoyée plus tard (store `outbox`, type `post`) est annoncée puis le fil est relu ; refusée au rejeu,
// sa modale est rouverte avec le contenu (brouillon lié à l'entrée) et les erreurs du serveur. `data-xm-feed-page` :
// public/outbox-notices.js laisse la page annoncer elle-même ces envois. Motifs de signalement fournis aux cartes.
// Pastille « Événement » : modale « Créer un événement » (P8-04, réglages `composer.event`), fiche ouverte après création.
// Props : FeedPageController::index (title, tag, feed, featured, events, who_to_follow, composer, report_reasons).
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { LINKS, hrefOf } from '@/app/memberNav';
import { useToast } from '@/app/useToast';
import { useOutboxStore, type FlushDetail } from '@/stores/outbox';
import { OUTBOX_TYPE, type RecoveredEntry } from '@/feed/useComposer';
import { DRAFT_POLL, DRAFT_POST } from '@/feed/composer';
import { provideReportReasons } from '@/feed/reportReasons';
import { useDraftsStore } from '@/stores/drafts';
import { useFeedList } from '@/feed/useFeedList';
import { lazyComponent } from '@/feed/lazy';
import type { ComposerOptions, CursorPage, FeaturedThematique, Option, Post } from '@/feed/types';
import FeedComposer from '@/components/feed/FeedComposer.vue';
import XmPost from '@/components/XmPost.vue';
import XmRightColumn, { type EventItem, type Suggestion } from '@/components/XmRightColumn.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{
    title: string;
    tag?: string | null;
    feed: CursorPage<Post>;
    featured?: FeaturedThematique[];
    events?: EventItem[];
    who_to_follow?: (Suggestion & { invite_url: string })[];
    composer: ComposerOptions;
    report_reasons?: Option[];
}>(), { tag: null, featured: () => [], events: () => [], who_to_follow: () => [], report_reasons: () => [] });

// Carte « Qui suivre » (P8-06) : « + » ouvre /qui-suivre?invite=<id>, qui demande confirmation avant d'envoyer.
const inviteFromCard = (id: number | string) => {
    const person = props.who_to_follow.find((item) => item.id === id);
    if (person) router.visit(person.invite_url);
};

const postModal = lazyComponent(() => import('@/components/feed/CreatePostModal.vue'));
const pollModal = lazyComponent(() => import('@/components/feed/CreatePollModal.vue'));
// P8-04 : pastille « Événement » → modale « Créer un événement » (même morceau que /evenements, chargé à la demande).
const eventModal = lazyComponent(() => import('@/components/events/CreateEventModal.vue'));
const CreatePostModal = postModal.component;
const CreatePollModal = pollModal.component;
const CreateEventModal = eventModal.component;

/** Filtres du prototype (fil.php $tags) ; valeurs App\Enums\PostTag, libellés feed.filters.*. */
const FILTERS = ['', 'temoignage', 'question', 'ressource', 'evenement', 'sondage'] as const;

const { t } = useI18n();
const toast = useToast();
const outbox = useOutboxStore();
const { items, loading, hasMore, finished, announce, reset, loadMore, remove, replace, prepend } = useFeedList('feed', props.feed, () => props.tag ?? null);
const filtering = ref(false);
const modal = ref<{ kind: 'post' | 'poll' | 'event'; focus: 'photo' | null } | null>(null);
const recovered = ref<RecoveredEntry | null>(null);
const drafts = useDraftsStore();
provideReportReasons(props.report_reasons);

const fil = hrefOf(LINKS.fil);
const filterHref = (value: string) => (value ? `${fil}?tag=${encodeURIComponent(value)}` : fil);
const filterLabel = (value: string) => t(`feed.filters.${value || 'all'}`);
const active = (value: string) => (props.tag ?? '') === value;
const emptyText = computed(() => (props.tag ? t('microcopy.empty.feed_filter') : t('microcopy.empty.feed')));

watch(() => props.feed, (page) => reset(page));

function filter(event: MouseEvent, value: string) {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
    event.preventDefault();
    if (active(value) || filtering.value) return;
    router.get(fil, value ? { tag: value } : {}, {
        only: ['feed', 'tag'],
        preserveState: true,
        preserveScroll: true,
        onStart: () => { filtering.value = true; },
        onFinish: () => { filtering.value = false; },
    });
}

const loaderOf = (kind: 'post' | 'poll' | 'event') => (kind === 'poll' ? pollModal : kind === 'event' ? eventModal : postModal);

async function open(kind: 'post' | 'poll' | 'event', focus: 'photo' | null) {
    if (kind === 'poll' && !props.composer.can_poll) return;
    if (kind === 'event' && !props.composer.event) return;
    modal.value = { kind, focus };
    if (!(await loaderOf(kind).load())) {
        modal.value = null;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

/** Morceaux des modales demandés dès le survol de l'invite (réseau lent). */
const warm = (kind: 'post' | 'poll' | 'event') => { void loaderOf(kind).load(); };

/** Événement créé depuis le fil : annoncé, puis sa fiche ouverte. */
function onEventCreated(event: { url: string }) {
    toast.show(t('community.events.toasts.created'), 'success');
    router.visit(event.url);
}

function onPublished(post: Post) {
    if (!props.tag || props.tag === post.tag) prepend(post);
}

/** Supprimée : retirée de la liste ; retirée des enregistrées : reste dans le fil. */
const onRemoved = (id: string, reason: 'deleted' | 'unsaved') => { if (reason === 'deleted') remove(id); };

// Publication mise en file hors connexion puis envoyée (ou refusée) au retour du réseau.
const stopFlush = watch(() => outbox.lastFlush, (detail: FlushDetail | null) => {
    if (!detail) return;
    const sent = detail.sent.filter((entry) => entry.type === OUTBOX_TYPE);
    const failed = detail.failed.filter((entry) => entry.type === OUTBOX_TYPE);
    if (sent.length) {
        toast.show(t('feed.offline.sent'), 'success');
        router.reload({ only: ['feed'] });
    }
    if (failed.length) {
        toast.show(t('feed.offline.failed'), 'warning', { urgent: true });
        void reopen(failed[0] as FlushDetail['failed'][number] & { errors?: RecoveredEntry['errors'] });
    }
});

/** Modale (publication ou sondage) dont le brouillon est lié à l'entrée refusée. */
async function draftKind(id: string): Promise<'post' | 'poll' | null> {
    for (const [kind, name] of [['post', DRAFT_POST], ['poll', DRAFT_POLL]] as const) {
        try {
            if ((await drafts.load<{ outbox?: string }>(name))?.outbox === id) return kind;
        } catch {
            return null; // IndexedDB indisponible : rien à rouvrir
        }
    }
    return null;
}

/** Refus au rejeu : modale rouverte avec le contenu et les erreurs (sauf si une modale est déjà ouverte). */
async function reopen(result: { id: string; status: number; errors?: RecoveredEntry['errors'] }) {
    if (modal.value) return;
    const kind = await draftKind(result.id);
    if (!kind || modal.value) return;
    recovered.value = { id: result.id, status: result.status, errors: result.errors };
    await open(kind, null);
}

const closeModal = () => {
    modal.value = null;
    recovered.value = null;
};

const quick = [
    { key: 'sos', link: LINKS.sos, icon: 'broadcast', iconClass: 'text-danger', card: 'border-danger', inertia: false },
    { key: 'report', link: LINKS.signaler, icon: 'megaphone', iconClass: 'text-primary', card: '', inertia: false },
    { key: 'assistant', link: LINKS.assistant, icon: 'robot', iconClass: 'xm-feed-assistant-icon', card: '', inertia: true },
    { key: 'emergency', link: LINKS.urgences, icon: 'telephone-fill', iconClass: 'text-success', card: '', inertia: false },
];

onMounted(() => { if (outbox.pending > 0) outbox.refresh().catch(() => undefined); });
onBeforeUnmount(() => stopFlush());
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4" data-xm-feed-page>
        <h1 class="visually-hidden">{{ t('feed.title') }}</h1>

        <nav class="row g-2 xm-feed-quick" :aria-label="t('feed.quick.title')">
            <div v-for="item in quick" :key="item.key" class="col-6 col-sm-3">
                <component :is="item.inertia ? Link : 'a'" :class="['card card-body text-center py-3 h-100 xm-card-hover', item.card]" :href="hrefOf(item.link)">
                    <i :class="`bi bi-${item.icon} fs-3 ${item.iconClass}`" aria-hidden="true"></i><span class="small fw-bold mt-1">{{ t(`feed.quick.${item.key}`) }}</span>
                </component>
            </div>
        </nav>

        <FeedComposer v-if="composer.can_post" :options="composer" @open="open" @warm="warm" />

        <section v-if="featured.length" aria-labelledby="feedFeaturedTitle">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 id="feedFeaturedTitle" class="h6 mb-0">{{ t('feed.featured.title') }}</h2>
                <a class="small" :href="hrefOf(LINKS.thematiques)">{{ t('feed.featured.see_all') }}</a>
            </div>
            <div class="d-flex gap-3 overflow-auto pb-2">
                <a v-for="item in featured" :key="item.slug" :href="item.href" class="card xm-card-hover flex-shrink-0 overflow-hidden xm-feed-featured-card">
                    <div class="xm-ratio-4x3"><img class="xm-img-cover" :src="item.cover_url" alt="" loading="lazy" decoding="async"></div>
                    <div class="card-body p-2"><span v-if="item.category" class="xm-chip mb-1">{{ item.category }}</span><p class="small fw-bold mb-0 lh-sm xm-feed-featured-title">{{ item.title }}</p></div>
                </a>
            </div>
        </section>

        <section class="vstack gap-4" aria-labelledby="feedListTitle">
            <h2 id="feedListTitle" class="visually-hidden">{{ t('feed.list.title') }}</h2>
            <ul class="nav nav-pills gap-2 xm-feed-filters" :aria-label="t('feed.list.filters_label')">
                <li v-for="value in FILTERS" :key="value" class="nav-item">
                    <a :class="['nav-link py-1 px-3', active(value) ? 'active' : 'bg-light']" :href="filterHref(value)" :aria-current="active(value) ? 'page' : undefined" @click="filter($event, value)">{{ filterLabel(value) }}</a>
                </li>
            </ul>

            <div id="liste" class="vstack gap-4" :aria-busy="filtering || loading ? 'true' : 'false'">
                <template v-if="filtering">
                    <XmSkeleton v-for="n in 3" :key="`s${n}`" type="card" :label="t('microcopy.loading.feed')" />
                </template>
                <template v-else>
                    <XmPost v-for="post in items" :key="post.id" :post="post" @update:post="replace" @removed="onRemoved" />
                    <div v-if="items.length === 0" class="card card-body text-center text-secondary">{{ emptyText }}</div>
                    <XmSkeleton v-if="loading" type="card" :label="t('microcopy.loading.more')" />
                </template>
            </div>

            <div v-if="!filtering && hasMore" class="xm-load-more" data-xm-more>
                <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore()">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
            </div>
            <p v-else-if="!filtering && finished" class="text-center small text-secondary mb-0" data-xm-more>{{ t('microcopy.empty.end_of_list', { count: items.length }) }}</p>
            <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
        </section>
    </div>
    <XmRightColumn :events="events" :suggestions="who_to_follow" @invite="inviteFromCard" />

    <component :is="CreatePostModal" v-if="modal?.kind === 'post' && CreatePostModal" :options="composer" :focus="modal.focus" :recovered="recovered" @published="onPublished" @hidden="closeModal" />
    <component :is="CreatePollModal" v-if="modal?.kind === 'poll' && CreatePollModal" :options="composer" :recovered="recovered" @published="onPublished" @hidden="closeModal" />
    <component :is="CreateEventModal" v-if="modal?.kind === 'event' && CreateEventModal && composer.event" :options="composer.event" @created="onEventCreated" @hidden="closeModal" />
</template>
