<script setup lang="ts">
// Publications enregistrées `/fil/enregistres` (lot fil-ecrans, P7-05 / P7-06 ; « Enregistrer » du menu d'une carte,
// FR-SOC-05), page Inertia `Feed/Saved` chargée à la demande : en-tête, liste <XmPost> par curseur (`GET feed/saved`,
// « Charger plus »), retrait immédiat d'une publication retirée des enregistrées ou supprimée, état vide.
// Props : FeedPageController::saved (title, saved, events, report_reasons : motifs de signalement fournis aux cartes).
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { LINKS, hrefOf } from '@/app/memberNav';
import { useFeedList } from '@/feed/useFeedList';
import { provideReportReasons } from '@/feed/reportReasons';
import type { CursorPage, Option, Post } from '@/feed/types';
import XmEmptyState from '@/components/XmEmptyState.vue';
import XmPost from '@/components/XmPost.vue';
import XmRightColumn, { type EventItem } from '@/components/XmRightColumn.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const props = withDefaults(defineProps<{ title: string; saved: CursorPage<Post>; events?: EventItem[]; report_reasons?: Option[] }>(), { events: () => [], report_reasons: () => [] });

const { t } = useI18n();
provideReportReasons(props.report_reasons);
const { items, loading, hasMore, finished, announce, loadMore, remove, replace } = useFeedList('saved', props.saved);
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <Link class="small" :href="hrefOf(LINKS.fil)"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ t('feed.post.back') }}</Link>
        <div class="card card-body">
            <h1 class="h5 mb-1"><i class="bi bi-bookmark-fill text-primary me-2" aria-hidden="true"></i>{{ title }}</h1>
            <p class="small text-secondary mb-0">{{ t('feed.post.saved_intro') }}</p>
        </div>
        <div class="vstack gap-4" :aria-busy="loading ? 'true' : 'false'">
            <XmPost v-for="post in items" :key="post.id" :post="post" @update:post="replace" @removed="remove" />
            <div v-if="items.length === 0" class="card">
                <XmEmptyState icon="bookmark" :title="t('microcopy.empty.title')">
                    {{ t('microcopy.empty.saved_posts') }}
                    <template #action><Link class="btn btn-primary-soft" :href="hrefOf(LINKS.fil)">{{ t('feed.post.back') }}</Link></template>
                </XmEmptyState>
            </div>
            <XmSkeleton v-if="loading" type="card" :label="t('microcopy.loading.more')" />
        </div>
        <div v-if="hasMore" class="xm-load-more">
            <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore()">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
        </div>
        <p v-else-if="finished" class="text-center small text-secondary mb-0">{{ t('microcopy.empty.end_of_list', { count: items.length }) }}</p>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>
    <XmRightColumn :events="events" />
</template>
