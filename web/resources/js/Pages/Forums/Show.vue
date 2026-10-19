<script setup lang="ts">
// Page `/forums/{ulid}` (lot forums, P8-05 ; prototype forum-topic.php), page Inertia `Forums/Show` chargée à la demande
// par app.ts (import.meta.glob) dans le gabarit membre (col-md-8 col-lg-6 + colonne droite) : fil d'Ariane Forums ›
// forum › sujet, carte du sujet (titre, avatar, auteur·rice ou « Membre anonyme » · il y a… · vues, message avec ses
// retours à la ligne), « N réponses » (#reponses), réponses (badge Pair animateur·rice / Équipe Xomiton), « Charger
// plus » (API `GET forums/topics/{ulid}/replies?page=`), formulaire « Ta réponse » (≥ 2 car. vérifié avant l'envoi,
// interrupteur anonyme ; `POST …/replies`, réponse ajoutée et annoncée), sujet fermé → message à la place du
// formulaire. Signaler (modale du fil chargée à la demande, cibles `topic` / `reply`) et supprimer (confirmation).
// Props : ForumsPageController::show (title, topic, replies, reply, report_reasons, events).
import { computed, nextTick, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { route } from '@/app/routes';
import { lazyComponent } from '@/feed/lazy';
import { timeAgo } from '@/feed/format';
import { length } from '@/feed/composer';
import { provideReportReasons } from '@/feed/reportReasons';
import type { ReportTarget } from '@/feed/api';
import type { Option } from '@/feed/types';
import { forumsApi } from '@/forums/api';
import type { ForumReply, ForumTopic, PagedList, ReplyOptions } from '@/forums/types';
import type { EventItem } from '@/components/XmRightColumn.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmBreadcrumb from '@/components/XmBreadcrumb.vue';
import XmModal from '@/components/XmModal.vue';
import XmRightColumn from '@/components/XmRightColumn.vue';

const props = withDefaults(defineProps<{
    title: string;
    topic: ForumTopic;
    replies: PagedList<ForumReply>;
    reply: ReplyOptions;
    report_reasons?: Option[];
    events?: EventItem[];
}>(), { report_reasons: () => [], events: () => [] });

provideReportReasons(props.report_reasons);

const { t } = useI18n();
const toast = useToast();
const api = forumsApi(useApi());

const current = ref<ForumTopic>({ ...props.topic });
const items = ref<ForumReply[]>([...props.replies.data]);
const page = ref(props.replies.meta.current_page);
const lastPage = ref(props.replies.meta.last_page);
const loading = ref(false);
const announce = ref('');
const body = ref('');
const anonymous = ref(false);
const error = ref('');
const sending = ref(false);
const field = ref<HTMLTextAreaElement | null>(null);
const reporting = ref<ReportTarget | null>(null);
const removing = ref<{ kind: 'topic' } | { kind: 'reply'; reply: ForumReply } | null>(null);
const deleting = ref(false);
const confirmDialog = ref<InstanceType<typeof XmModal> | null>(null);
const lockedNotice = ref<HTMLElement | null>(null);

const reportModal = lazyComponent(() => import('@/components/feed/ReportContentModal.vue'));
const ReportContentModal = reportModal.component;

watch(() => props.topic, (topic) => { current.value = { ...topic }; });
watch(() => props.replies, (list) => {
    items.value = [...list.data];
    page.value = list.meta.current_page;
    lastPage.value = list.meta.last_page;
});

const forumsUrl = route('forums.index', undefined, '/forums');
const crumbs = computed(() => [
    { label: t('community.forums.title'), url: forumsUrl },
    ...(current.value.forum ? [{ label: current.value.forum.name, url: current.value.forum.url }] : []),
    { label: current.value.title },
]);
const hasMore = computed(() => page.value < lastPage.value);
const sep = computed(() => ` ${t('common.separator')} `);

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const next = await api.replies(current.value.id, page.value + 1);
        const known = new Set(items.value.map((item) => item.id));
        const fresh = next.data.filter((item) => !known.has(item.id));
        items.value = [...items.value, ...fresh];
        page.value = next.meta.current_page;
        lastPage.value = next.meta.last_page;
        announce.value = t('community.forums.replies_loaded', { count: fresh.length }, fresh.length);
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
}

async function send() {
    if (sending.value) return;
    const text = body.value.trim();
    error.value = '';
    if (text === '') error.value = t('community.forums.reply.empty');
    else if (length(text) < props.reply.limits.min) error.value = t('community.forums.reply.min', { min: props.reply.limits.min });
    else if (length(text) > props.reply.limits.max) error.value = t('feed.create.text_max', { max: props.reply.limits.max });
    if (error.value) {
        await nextTick();
        field.value?.focus();
        return;
    }
    sending.value = true;
    try {
        const { reply, repliesCount } = await api.reply(current.value.id, { body: text, is_anonymous: anonymous.value });
        // La dernière page est complète : la nouvelle réponse y est ajoutée ; sinon elle viendra avec « Charger plus ».
        if (!hasMore.value) items.value = [...items.value.filter((item) => item.id !== reply.id), reply];
        current.value = { ...current.value, replies_count: repliesCount };
        body.value = '';
        anonymous.value = false;
        toast.show(t('community.forums.toasts.reply_posted'), 'success');
        announce.value = t('community.forums.toasts.reply_posted');
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) {
            const errors = fieldErrors(cause);
            error.value = errors.body ?? cause.message;
            if (cause.code === 'topic_locked') {
                // Le formulaire disparaît : le refus est annoncé et le focus passe sur l'avis « sujet fermé » (pas sur body).
                current.value = { ...current.value, is_locked: true, can: { ...current.value.can, reply: false } };
                announce.value = cause.message;
                await nextTick();
                lockedNotice.value?.focus();
            }
        } else if (cause instanceof ApiError && cause.status === 0) {
            error.value = t('microcopy.error.unreachable');
        } else if (cause instanceof ApiError && cause.status < 500) {
            error.value = cause.message;
        } else {
            error.value = t('microcopy.error.generic');
        }
    } finally {
        sending.value = false;
    }
}

async function openReport(target: ReportTarget) {
    reporting.value = target;
    if (!(await reportModal.load())) {
        reporting.value = null;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

async function remove() {
    const target = removing.value;
    if (!target || deleting.value) return;
    deleting.value = true;
    try {
        if (target.kind === 'topic') {
            await api.deleteTopic(current.value.id);
            toast.show(t('community.forums.toasts.topic_deleted'), 'success');
            confirmDialog.value?.close();
            router.visit(current.value.forum?.url ?? forumsUrl);
            return;
        }
        await api.deleteReply(target.reply.id);
        items.value = items.value.filter((item) => item.id !== target.reply.id);
        current.value = { ...current.value, replies_count: Math.max(0, current.value.replies_count - 1) };
        toast.show(t('community.forums.toasts.reply_deleted'), 'success');
        confirmDialog.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
        else if (cause instanceof ApiError && cause.status >= 400 && cause.status < 500 && ![419, 429].includes(cause.status)) toast.show(cause.message, 'warning');
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <XmBreadcrumb inertia :items="crumbs" :label="t('community.forums.detail.breadcrumb')" />

        <div v-if="current.moderated" class="alert alert-warning small mb-0" role="status"><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>{{ t('community.forums.moderated') }}</div>

        <article class="card" aria-labelledby="topicTitle">
            <div class="card-body">
                <h1 id="topicTitle" class="h5 text-break">{{ current.title }}</h1>
                <div class="d-flex align-items-center mb-3">
                    <XmAvatar class="me-2" size="xs" :name="current.author.pseudo" :src="current.author.avatar_url" />
                    <div class="small">
                        <strong>{{ current.author.pseudo }}</strong>{{ sep }}<time :datetime="current.created_at ?? undefined">{{ timeAgo(current.created_at, t) }}</time>{{ sep }}<i class="bi bi-eye" aria-hidden="true"></i> <span aria-hidden="true">{{ current.views_count }}</span><span class="visually-hidden">{{ t('community.forums.views', { count: current.views_count }, current.views_count) }}</span>
                        <span v-if="current.is_locked" class="badge bg-light text-secondary ms-1"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ t('community.forums.locked') }}</span>
                    </div>
                </div>
                <p class="mb-0 xm-forum-text">{{ current.body }}</p>
                <div v-if="current.can.report || current.can.delete" class="d-flex gap-3 mt-3">
                    <button v-if="current.can.report" class="btn btn-link btn-sm p-0 small text-secondary" type="button" aria-haspopup="dialog" @click="openReport({ topic: current.id })"><i class="bi bi-flag me-1" aria-hidden="true"></i>{{ t('community.forums.detail.report_topic') }}</button>
                    <button v-if="current.can.delete" class="btn btn-link btn-sm p-0 small text-danger" type="button" aria-haspopup="dialog" @click="removing = { kind: 'topic' }"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ t('community.forums.detail.delete_topic') }}</button>
                </div>
            </div>
        </article>

        <section class="vstack gap-4" aria-labelledby="reponses">
            <h2 id="reponses" class="h6 mb-0">{{ t('community.forums.replies', { count: current.replies_count }, current.replies_count) }}</h2>
            <article v-for="item in items" :id="`reponse-${item.id}`" :key="item.id" class="card">
                <div class="card-body d-flex gap-3">
                    <XmAvatar class="flex-shrink-0" :name="item.author.pseudo" :src="item.author.avatar_url" />
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="small mb-1"><strong>{{ item.author.pseudo }}</strong><template v-if="item.author.badge"> <span class="badge bg-primary bg-opacity-10 text-primary">{{ item.author.badge }}</span></template>{{ sep }}<time :datetime="item.created_at ?? undefined">{{ timeAgo(item.created_at, t) }}</time></div>
                        <p class="mb-0 xm-forum-text">{{ item.body }}</p>
                        <div v-if="item.can.report || item.can.delete" class="d-flex gap-3 mt-2">
                            <button v-if="item.can.report" class="btn btn-link btn-sm p-0 small text-secondary" type="button" aria-haspopup="dialog" @click="openReport({ reply: item.id })">{{ t('community.forums.detail.report_reply') }}</button>
                            <button v-if="item.can.delete" class="btn btn-link btn-sm p-0 small text-danger" type="button" aria-haspopup="dialog" @click="removing = { kind: 'reply', reply: item }">{{ t('community.forums.detail.delete_reply') }}</button>
                        </div>
                    </div>
                </div>
            </article>
            <p v-if="items.length === 0" class="small text-secondary mb-0">{{ t('community.forums.detail.replies_empty') }}</p>
            <div v-if="hasMore" class="xm-load-more">
                <button class="btn btn-primary-soft px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('common.load_more') }}</button>
            </div>
        </section>

        <div v-if="current.is_locked" ref="lockedNotice" class="alert alert-info mb-0" role="status" tabindex="-1"><i class="bi bi-lock me-1" aria-hidden="true"></i>{{ t('community.forums.errors.locked') }}</div>
        <div v-else-if="current.can.reply" class="card">
            <div class="card-body">
                <form novalidate @submit.prevent="send">
                    <label class="form-label" for="rtext">{{ t('community.forums.reply.title') }}</label>
                    <textarea id="rtext" ref="field" v-model="body" class="form-control mb-3" name="body" rows="3" required :minlength="reply.limits.min" :maxlength="reply.limits.max" :placeholder="t('community.forums.reply.placeholder')" :aria-invalid="error ? 'true' : undefined" :aria-describedby="error ? 'rtext-error' : undefined"></textarea>
                    <div v-if="error" id="rtext-error" class="invalid-feedback d-block mb-3"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ error }}</div>
                    <div class="d-flex justify-content-between align-items-center gap-3">
                        <div class="form-check form-switch small">
                            <input id="ranon" v-model="anonymous" class="form-check-input" type="checkbox" role="switch" name="is_anonymous">
                            <label class="form-check-label" for="ranon">{{ t('community.forums.reply.anonymous') }}</label>
                        </div>
                        <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending ? 'true' : undefined">{{ t('community.forums.reply.submit') }}</button>
                    </div>
                </form>
            </div>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </div>

    <XmRightColumn :events="events" />

    <component :is="ReportContentModal" v-if="reporting !== null && ReportContentModal" :target="reporting" @hidden="reporting = null" />
    <XmModal v-if="removing" ref="confirmDialog" id="modalDeleteForumContent" type="delete" :open="true" :busy="deleting"
        :title="removing.kind === 'topic' ? t('community.forums.detail.delete_topic_title') : t('community.forums.detail.delete_reply_title')"
        :action="removing.kind === 'topic' ? t('community.forums.detail.delete_topic') : t('community.forums.detail.delete_reply')"
        @action="remove" @hidden="removing = null">
        <p class="mb-0">{{ removing.kind === 'topic' ? t('community.forums.detail.delete_topic_body') : t('community.forums.detail.delete_reply_body') }}</p>
    </XmModal>
</template>
