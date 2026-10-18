<script setup lang="ts">
// Détail d'une publication `/fil/{ulid}` (lot fil-ecrans, P7-06 ; prototype post-details.php), page Inertia
// `Feed/Show` chargée à la demande : « Retour au fil », carte complète <XmPost> (sans le champ de commentaire de la
// carte : « Commentaires » mène au champ du détail), commentaires paginés <CommentThread>, colonne droite.
// Publication supprimée depuis le détail : retour au fil. Props : FeedPageController::show (title, post, comments, events,
// report_reasons : motifs de signalement fournis à la carte et aux commentaires).
import { computed, nextTick, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { LINKS, hrefOf } from '@/app/memberNav';
import { provideReportReasons } from '@/feed/reportReasons';
import type { Comment, CursorPage, Option, Post } from '@/feed/types';
import CommentThread from '@/components/feed/CommentThread.vue';
import XmPost from '@/components/XmPost.vue';
import XmRightColumn, { type EventItem } from '@/components/XmRightColumn.vue';

const props = withDefaults(defineProps<{ title: string; post: Post; comments: CursorPage<Comment>; events?: EventItem[]; report_reasons?: Option[] }>(), { events: () => [], report_reasons: () => [] });

const { t } = useI18n();
provideReportReasons(props.report_reasons);
const current = ref<Post>({ ...props.post });
const thread = ref<InstanceType<typeof CommentThread> | null>(null);
const count = computed(() => current.value.comments_count);

const onCount = (value: number) => { current.value = { ...current.value, comments_count: value }; };
const onRemoved = (_id: string, reason: 'deleted' | 'unsaved') => { if (reason === 'deleted') router.visit(hrefOf(LINKS.fil)); };
const toComments = () => nextTick(() => thread.value?.focus());
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <h1 class="visually-hidden">{{ t('feed.post.detail_title', { name: current.author.pseudo }) }}</h1>
        <Link class="small" :href="hrefOf(LINKS.fil)"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ t('feed.post.back') }}</Link>
        <XmPost :post="current" :level="2" :comment-form="false" detail @update:post="current = $event" @removed="onRemoved" @comment="toComments" />
        <CommentThread ref="thread" :post-id="current.id" :can-comment="current.can.comment" :initial="comments" :count="count" @count="onCount" />
    </div>
    <XmRightColumn :events="events" />
</template>
