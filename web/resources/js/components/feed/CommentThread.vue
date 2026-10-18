<script setup lang="ts">
// Commentaires du détail d'une publication (lot fil-ecrans, P7-06 ; prototype post-details.php l.43-60) : carte
// #commentaires, nombre de commentaires, liste chronologique (CommentResource : « Membre anonyme » pour un
// commentaire anonyme, RG-02), suite par curseur (`GET posts/{ulid}/comments`, « Voir la suite des commentaires »),
// champ « Ajouter un commentaire bienveillant… » (2 à 1000 caractères, filtre de mots côté serveur), suppression (auteur·rice
// ou modération, confirmée par <XmModal type="delete">) et signalement (modales chargées à la demande).
// postId, canComment, initial (première page, props Inertia), count ; émet `count` (nouveau total). focus() exposé.
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { useToast } from '@/app/useToast';
import { useSessionStore } from '@/stores/session';
import { feedApi } from '@/feed/api';
import { timeAgo } from '@/feed/format';
import { lazyComponent } from '@/feed/lazy';
import type { Comment, CursorPage } from '@/feed/types';
import XmAvatar from '@/components/XmAvatar.vue';

const reportModal = lazyComponent(() => import('./ReportContentModal.vue'));
const ReportContentModal = reportModal.component;
const confirmModal = lazyComponent(() => import('@/components/XmModal.vue'));
const ConfirmModal = confirmModal.component;

const COMMENT_MAX = 1000;

const props = defineProps<{ postId: string; canComment: boolean; initial: CursorPage<Comment>; count: number }>();
const emit = defineEmits<{ count: [count: number] }>();

const { t } = useI18n();
const toast = useToast();
const session = useSessionStore();
const api = feedApi(useApi());

const comments = ref<Comment[]>([...props.initial.data]);
const cursor = ref<string | null>(props.initial.meta.next_cursor ?? null);
const loading = ref(false);
const body = ref('');
const error = ref('');
const sending = ref(false);
const reporting = ref<number | null>(null);
const confirming = ref<Comment | null>(null);
const deleting = ref(false);
const confirmDialog = ref<{ close: () => void } | null>(null);
const field = ref<HTMLTextAreaElement | null>(null);
const me = computed(() => session.user);

async function loadMore() {
    if (loading.value || cursor.value === null) return;
    loading.value = true;
    try {
        const page = await api.comments(props.postId, cursor.value);
        const known = new Set(comments.value.map((comment) => comment.id));
        comments.value = [...comments.value, ...page.data.filter((comment) => !known.has(comment.id))];
        cursor.value = page.meta.next_cursor ?? null;
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
    } finally {
        loading.value = false;
    }
}

async function send() {
    error.value = '';
    const text = body.value.trim();
    if (Array.from(text).length < 2) {
        error.value = t('feed.comments.too_short');
        field.value?.focus();
        return;
    }
    sending.value = true;
    try {
        const { comment, count } = await api.addComment(props.postId, text);
        comments.value = [...comments.value.filter((item) => item.id !== comment.id), comment];
        body.value = '';
        emit('count', count || props.count + 1);
        toast.show(t('feed.comments.posted'), 'success');
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) error.value = fieldErrors(cause).body ?? cause.message;
        else if (cause instanceof ApiError && cause.status === 0) error.value = t('microcopy.error.unreachable');
        else if (cause instanceof ApiError && cause.status === 403) error.value = cause.message;
        if (error.value) field.value?.focus();
    } finally {
        sending.value = false;
    }
}

async function askDelete(comment: Comment) {
    confirming.value = comment;
    if (!(await confirmModal.load())) {
        confirming.value = null;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

async function remove() {
    const comment = confirming.value;
    if (!comment || deleting.value) return;
    deleting.value = true;
    try {
        await api.deleteComment(comment.id);
        comments.value = comments.value.filter((item) => item.id !== comment.id);
        emit('count', Math.max(0, props.count - 1));
        toast.show(t('feed.comments.deleted'), 'success');
        confirmDialog.value?.close();
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 0) toast.show(t('microcopy.error.unreachable'), 'warning');
        else if (cause instanceof ApiError && cause.status >= 400 && cause.status < 500 && ![419, 429].includes(cause.status)) toast.show(cause.message, 'warning');
    } finally {
        deleting.value = false;
    }
}

async function openReport(id: number) {
    reporting.value = id;
    if (!(await reportModal.load())) {
        reporting.value = null;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

defineExpose({ focus: () => field.value?.focus() });
</script>

<template>
    <section id="commentaires" class="card" aria-labelledby="commentsTitle">
        <div class="card-header border-0 pb-0"><h2 id="commentsTitle" class="h6 mb-0">{{ t('feed.comments.count', { count }, count) }}</h2></div>
        <div class="card-body">
            <p v-if="comments.length === 0" class="small text-secondary">{{ t('feed.comments.empty') }}</p>
            <ul v-else class="list-unstyled mb-0">
                <li v-for="comment in comments" :key="comment.id" class="d-flex gap-2 mb-3" :id="`comment-${comment.id}`">
                    <XmAvatar class="flex-shrink-0" size="xs" :name="comment.author.pseudo" :src="comment.author.avatar_url" />
                    <div class="bg-light rounded p-2 px-3 flex-grow-1">
                        <div class="d-flex justify-content-between gap-2">
                            <strong class="small">{{ comment.author.pseudo }}</strong>
                            <span class="small text-secondary"><time :datetime="comment.created_at ?? undefined">{{ timeAgo(comment.created_at, t) }}</time></span>
                        </div>
                        <p class="small mb-0 xm-comment-body">{{ comment.body }}</p>
                        <div v-if="comment.can.delete || comment.can.report" class="d-flex gap-3 mt-1">
                            <button v-if="comment.can.report" class="btn btn-link btn-sm p-0 small text-secondary" type="button" @click="openReport(comment.id)">{{ t('feed.comments.report') }}</button>
                            <button v-if="comment.can.delete" class="btn btn-link btn-sm p-0 small text-danger" type="button" aria-haspopup="dialog" @click="askDelete(comment)">{{ t('feed.comments.delete') }}</button>
                        </div>
                    </div>
                </li>
            </ul>
            <div v-if="cursor !== null" class="xm-load-more mb-3">
                <button class="btn btn-primary-soft btn-sm px-4" type="button" :disabled="loading" :aria-busy="loading ? 'true' : undefined" @click="loadMore">{{ loading ? t('microcopy.loading.more') : t('feed.comments.load_next') }}</button>
            </div>
            <form v-if="canComment" class="d-flex gap-2 mt-3 flex-wrap" novalidate @submit.prevent="send">
                <XmAvatar class="flex-shrink-0" size="xs" :name="me?.pseudo ?? ''" :src="me?.avatar_url" />
                <label class="visually-hidden" for="f-comment">{{ t('feed.comments.field_label') }}</label>
                <textarea id="f-comment" ref="field" v-model="body" class="form-control bg-light flex-grow-1 w-auto" name="body" rows="1" required minlength="2" :maxlength="COMMENT_MAX" :placeholder="t('feed.comments.placeholder')" :aria-invalid="error ? 'true' : undefined" :aria-describedby="error ? 'f-comment-error' : undefined"></textarea>
                <button class="btn btn-primary" type="submit" :disabled="sending" :aria-busy="sending ? 'true' : undefined">{{ t('feed.create.submit') }}</button>
                <div v-if="error" id="f-comment-error" class="invalid-feedback d-block w-100">{{ error }}</div>
            </form>
        </div>
        <component :is="ReportContentModal" v-if="reporting !== null && ReportContentModal" :target="{ comment: reporting }" @hidden="reporting = null" />
        <component :is="ConfirmModal" v-if="confirming && ConfirmModal" ref="confirmDialog" id="modalDeleteComment" type="delete" :title="t('microcopy.confirm.delete_title')" :open="true" :busy="deleting" @action="remove" @hidden="confirming = null">
            <p class="mb-0">{{ t('microcopy.confirm.delete_comment') }}</p>
        </component>
    </section>
</template>
