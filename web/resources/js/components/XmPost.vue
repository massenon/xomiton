<script setup lang="ts">
// Carte de publication du fil (lot fil-ecrans, P7-05) : port de x_render_post (prototype inc/render.php l.10-58) sur
// PostResource (docs/API.md §8.4). En-tête : avatar, pseudonyme (« Membre anonyme » et avatar neutre pour une
// publication anonyme, RG-02, même pour soi), temps relatif, étiquette (`tag_color` = PostTag::badge(), couleurs du
// prototype), visibilité, « modifié » ;
// menu : Enregistrer / Retirer des enregistrés, Signaler cette publication (modale de modération chargée à la demande,
// RG-16, et renvoi vers /signaler?post=), Masquer (retirée du seul fil de la personne, « Annuler » possible),
// Supprimer (auteur·rice ou modération ; confirmation <XmModal type="delete"> chargée à la demande). Corps (retours à la
// ligne gardés), image (lien signé), sondage <XmPoll>, actions : J'aime (optimiste, annulé si l'API refuse, un envoi à
// la fois : un clic pendant l'envoi est ignoré), Commentaires (détail /fil/{ulid}#commentaires), Partager
// (WhatsApp, Telegram, Facebook, copier : lien neutre /p/{ulid}, Q31 b), champ « Ajouter un commentaire bienveillant… ».
// post (PostResource), level (niveau du titre, 3), commentForm (champ de commentaire de la carte ; le détail a le sien).
// Émet `update:post` (état à jour), `removed` (supprimée, ou retirée des enregistrées avec `unsaved`), `comment`
// (clic sur « Commentaires » quand le détail est déjà affiché).
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { ApiError, fieldErrors, useApi } from '@/app/useApi';
import { route } from '@/app/routes';
import { useToast } from '@/app/useToast';
import { useSessionStore } from '@/stores/session';
import { feedApi } from '@/feed/api';
import { VISIBILITY_ICONS, shareLinks, timeAgo } from '@/feed/format';
import { lazyComponent } from '@/feed/lazy';
import type { Poll, Post } from '@/feed/types';
import XmAvatar from './XmAvatar.vue';
import XmBadge from './XmBadge.vue';
import XmPoll from './XmPoll.vue';

const reportModal = lazyComponent(() => import('./feed/ReportContentModal.vue'));
const ReportContentModal = reportModal.component;
const confirmModal = lazyComponent(() => import('./XmModal.vue'));
const ConfirmModal = confirmModal.component;

const props = withDefaults(defineProps<{ post: Post; level?: number; commentForm?: boolean; detail?: boolean }>(), { level: 3, commentForm: true, detail: false });
const emit = defineEmits<{ 'update:post': [post: Post]; removed: [id: string, reason: 'deleted' | 'unsaved']; comment: [] }>();

const { t } = useI18n();
const toast = useToast();
const session = useSessionStore();
const api = feedApi(useApi());

const current = ref<Post>({ ...props.post });
watch(() => props.post, (post) => { current.value = { ...post }; });

const hidden = ref(false);
const reporting = ref(false);
const confirming = ref(false);
const deleting = ref(false);
/** Supprimée : retirée de la liste une fois la modale fermée (jamais démontée en pleine transition Bootstrap). */
let deleted = false;
const confirmDialog = ref<{ close: () => void } | null>(null);
const liking = ref(false);
const busy = ref(false);
const comment = ref('');
const commentError = ref('');
const sending = ref(false);

const headingTag = computed(() => `h${Math.min(6, Math.max(2, props.level))}`);
const authorId = computed(() => `post-${current.value.id}-author`);
const name = computed(() => current.value.author.pseudo);
const ago = computed(() => timeAgo(current.value.published_at, t));
const image = computed(() => current.value.media.find((media) => media.kind === 'image' && media.url) ?? null);
const share = computed(() => shareLinks(current.value.share_url));
const detailHref = computed(() => `${current.value.url}#commentaires`);
const profileHref = computed(() => (current.value.is_mine && !current.value.is_anonymous ? route('profil', undefined, '/profil') : null));
const me = computed(() => session.user);

const update = (patch: Partial<Post>) => {
    current.value = { ...current.value, ...patch };
    emit('update:post', current.value);
};

const unreachable = (cause: unknown) => {
    // useApi annonce déjà 419, 429 et 5xx ; réseau coupé et autres refus : message ici.
    if (cause instanceof ApiError && (cause.status === 0 || (cause.status >= 400 && cause.status < 500 && ![419, 429].includes(cause.status)))) {
        toast.show(cause.status === 0 ? t('microcopy.error.unreachable') : cause.message, 'warning');
    }
};

async function toggleLike() {
    // Un envoi à la fois : POST et DELETE croisés pourraient revenir dans le désordre et contredire le serveur.
    if (liking.value) return;
    liking.value = true;
    const before = { liked: current.value.liked, likes_count: current.value.likes_count };
    const liked = !before.liked;
    update({ liked, likes_count: Math.max(0, before.likes_count + (liked ? 1 : -1)) });
    try {
        const result = await api.like(current.value.id, liked);
        update({ liked: result.liked, likes_count: result.likes_count });
    } catch (cause) {
        update(before);
        unreachable(cause);
    } finally {
        liking.value = false;
    }
}

async function toggleSave() {
    const saved = !current.value.saved;
    try {
        await api.save(current.value.id, saved);
        update({ saved });
        toast.show(saved ? t('feed.toasts.saved') : t('feed.toasts.unsaved'), 'success');
        if (!saved) emit('removed', current.value.id, 'unsaved');
    } catch (cause) {
        unreachable(cause);
    }
}

async function setHidden(value: boolean) {
    if (busy.value) return;
    busy.value = true;
    try {
        await api.hide(current.value.id, value);
        hidden.value = value;
        toast.show(value ? t('feed.toasts.hidden') : t('feed.toasts.unhidden'), 'success');
    } catch (cause) {
        unreachable(cause);
    } finally {
        busy.value = false;
    }
}

async function askDelete() {
    confirming.value = true;
    if (!(await confirmModal.load())) {
        confirming.value = false;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}

async function remove() {
    if (deleting.value) return;
    deleting.value = true;
    try {
        await api.deletePost(current.value.id);
        deleted = true;
        toast.show(t('feed.toasts.deleted'), 'success');
        confirmDialog.value?.close();
    } catch (cause) {
        unreachable(cause);
    } finally {
        deleting.value = false;
    }
}

async function copyLink() {
    try {
        if (!navigator.clipboard) throw new Error('clipboard');
        await navigator.clipboard.writeText(current.value.share_url);
        toast.show(t('feed.share.copied'), 'success');
    } catch {
        toast.show(t('feed.share.copy_failed'), 'warning');
    }
}

async function sendComment() {
    commentError.value = '';
    const body = comment.value.trim();
    if (Array.from(body).length < 2) {
        commentError.value = t('feed.comments.too_short');
        return;
    }
    sending.value = true;
    try {
        const { count } = await api.addComment(current.value.id, body);
        comment.value = '';
        update({ comments_count: count || current.value.comments_count + 1 });
        toast.show(t('feed.comments.posted'), 'success');
    } catch (cause) {
        if (cause instanceof ApiError && cause.status === 422) commentError.value = fieldErrors(cause).body ?? cause.message;
        else if (cause instanceof ApiError && cause.status === 0) commentError.value = t('microcopy.error.unreachable');
        else if (cause instanceof ApiError && cause.status === 403) commentError.value = cause.message;
    } finally {
        sending.value = false;
    }
}

function onConfirmHidden() {
    confirming.value = false;
    if (deleted) emit('removed', current.value.id, 'deleted');
}

const onPoll = (poll: Poll) => update({ poll });

async function openReport() {
    reporting.value = true;
    if (!(await reportModal.load())) {
        reporting.value = false;
        toast.show(t('microcopy.error.unreachable'), 'warning');
    }
}
</script>

<template>
    <div v-if="hidden" class="card card-body small d-flex flex-row flex-wrap align-items-center justify-content-between gap-2" :id="`post-${current.id}`" role="status">
        <span><i class="bi bi-eye-slash me-1" aria-hidden="true"></i>{{ t('feed.post.hidden') }}</span>
        <button class="btn btn-link btn-sm p-0" type="button" :disabled="busy" @click="setHidden(false)">{{ t('feed.post.undo_hide') }}</button>
    </div>
    <article v-else class="card" :id="`post-${current.id}`" :aria-labelledby="authorId" data-xm-post>
        <div class="card-header border-0 pb-0">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <XmAvatar class="me-2" :name="name" :src="current.author.avatar_url" />
                    <div>
                        <div class="nav nav-divider">
                            <component :is="headingTag" :id="authorId" class="nav-item card-title h6 mb-0">
                                <Link v-if="profileHref" :href="profileHref">{{ name }}</Link><template v-else>{{ name }}</template>
                            </component>
                            <span class="nav-item small"><time :datetime="current.published_at ?? undefined">{{ ago }}</time></span>
                        </div>
                        <p class="mb-0 small">
                            <XmBadge :color="current.tag_color || 'secondary'" soft>{{ current.tag_label }}</XmBadge>
                            <i :class="`bi bi-${VISIBILITY_ICONS[current.visibility] ?? 'globe2'} text-secondary ms-2`" :title="t('feed.post.visibility_label', { visibility: current.visibility_label })" aria-hidden="true"></i>
                            <span class="visually-hidden">{{ t('feed.post.visibility_label', { visibility: current.visibility_label }) }}</span>
                            <span v-if="current.edited" class="text-secondary ms-2">{{ t('feed.post.edited') }}</span>
                        </p>
                    </div>
                </div>
                <div class="dropdown">
                    <button type="button" class="text-secondary btn btn-secondary-soft-hover py-1 px-2 xm-post-menu-toggle" data-bs-toggle="dropdown" aria-expanded="false" :aria-label="t('feed.post.menu')" :title="t('feed.post.menu')"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" type="button" @click="toggleSave"><i :class="['bi xm-fw pe-2', current.saved ? 'bi-bookmark-fill' : 'bi-bookmark']" aria-hidden="true"></i>{{ current.saved ? t('feed.post.unsave') : t('feed.post.save') }}</button></li>
                        <li v-if="current.can.report"><button class="dropdown-item" type="button" @click="openReport"><i class="bi bi-flag xm-fw pe-2" aria-hidden="true"></i>{{ t('feed.post.report') }}</button></li>
                        <li v-if="!current.is_mine && !detail"><button class="dropdown-item" type="button" @click="setHidden(true)"><i class="bi bi-x-circle xm-fw pe-2" aria-hidden="true"></i>{{ t('feed.post.hide') }}</button></li>
                        <li v-if="current.can.delete"><button class="dropdown-item text-danger" type="button" aria-haspopup="dialog" @click="askDelete"><i class="bi bi-trash xm-fw pe-2" aria-hidden="true"></i>{{ t('feed.post.delete') }}</button></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <p v-if="current.moderated" class="alert alert-warning small"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>{{ t('feed.post.moderated') }}</p>
            <p v-if="current.body && current.body.trim() !== ''" class="xm-post-body">{{ current.body }}</p>
            <img v-if="image" class="card-img" :src="image.url" :alt="image.alt || t('feed.post.image_alt', { name })" :width="image.width ?? undefined" :height="image.height ?? undefined" loading="lazy" decoding="async">
            <div v-if="current.poll" :class="{ 'mt-3': image }">
                <XmPoll :post-id="current.id" :poll="current.poll" :level="level + 1" @update:poll="onPoll" />
            </div>
            <ul class="nav nav-stack py-3 small xm-post-actions">
                <li class="nav-item">
                    <button type="button" :class="['nav-link', { active: current.liked }]" :aria-pressed="current.liked ? 'true' : 'false'" data-xm-like @click="toggleLike">
                        <i :class="['bi pe-1', current.liked ? 'bi-hand-thumbs-up-fill' : 'bi-hand-thumbs-up']" aria-hidden="true"></i>{{ t('feed.post.like') }} ({{ current.likes_count }})
                    </button>
                </li>
                <li class="nav-item">
                    <button v-if="detail" type="button" class="nav-link" @click="emit('comment')"><i class="bi bi-chat-fill pe-1" aria-hidden="true"></i>{{ t('feed.comments.title') }} ({{ current.comments_count }})</button>
                    <Link v-else class="nav-link" :href="detailHref"><i class="bi bi-chat-fill pe-1" aria-hidden="true"></i>{{ t('feed.comments.title') }} ({{ current.comments_count }})</Link>
                </li>
                <li class="nav-item ms-sm-auto dropdown">
                    <button type="button" class="nav-link mb-0" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-reply-fill flip-horizontal ps-1" aria-hidden="true"></i>{{ t('feed.post.share') }}</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><p class="dropdown-item-text small text-secondary mb-0">{{ t('feed.share.intro') }}</p></li>
                        <li v-for="link in share" :key="link.key"><a class="dropdown-item" :href="link.href" target="_blank" rel="noopener noreferrer"><i :class="`bi bi-${link.icon} xm-fw pe-2`" aria-hidden="true"></i>{{ t(`feed.share.${link.key}`) }}<span class="visually-hidden"> {{ t('microcopy.a11y.opens_new_tab') }}</span></a></li>
                        <li><button class="dropdown-item" type="button" @click="copyLink"><i class="bi bi-link-45deg xm-fw pe-2" aria-hidden="true"></i>{{ t('feed.share.copy') }}</button></li>
                    </ul>
                </li>
            </ul>
            <div v-if="commentForm && current.can.comment" class="d-flex">
                <XmAvatar class="me-2" size="xs" :name="me?.pseudo ?? ''" :src="me?.avatar_url" />
                <form class="nav nav-item w-100 position-relative flex-wrap" novalidate @submit.prevent="sendComment">
                    <label class="visually-hidden" :for="`comment-${current.id}`">{{ t('feed.comments.field_label') }}</label>
                    <textarea :id="`comment-${current.id}`" v-model="comment" class="form-control pe-5 bg-light" rows="1" :placeholder="t('feed.comments.placeholder')" :aria-invalid="commentError ? 'true' : undefined" :aria-describedby="commentError ? `comment-${current.id}-error` : undefined" maxlength="1000"></textarea>
                    <button class="nav-link bg-transparent px-3 position-absolute top-50 end-0 translate-middle-y border-0" type="submit" :disabled="sending" :aria-label="t('feed.comments.send')"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
                    <div v-if="commentError" :id="`comment-${current.id}-error`" class="invalid-feedback d-block w-100">{{ commentError }}</div>
                </form>
            </div>
        </div>
        <component :is="ReportContentModal" v-if="reporting && ReportContentModal" :target="{ post: current.id }" @hidden="reporting = false" />
        <component :is="ConfirmModal" v-if="confirming && ConfirmModal" ref="confirmDialog" :id="`modalDeletePost-${current.id}`" type="delete" :title="t('microcopy.confirm.delete_title')" :open="true" :busy="deleting" @action="remove" @hidden="onConfirmHidden">
            <p class="mb-0">{{ t('microcopy.confirm.delete_post') }}</p>
        </component>
    </article>
</template>
