<script setup lang="ts">
// Section « Mes vidéos » (P7-10, Q96 ; docs/API.md §8.7 `GET posts/review`) : les vidéos de la personne qui ne sont pas
// encore en ligne — en attente de relecture ou refusées (50 au plus, les plus récentes d'abord). Chaque carte est une
// <XmPost> : badge d'état, motif du refus (`review_reason`) et bouton « Retirer ma vidéo » (DELETE). Placée sur le profil
// de la personne (Member/Profile, onglet Publications, ancre #mes-videos, lien « Mes vidéos » de la carte du fil) :
// aucune route serveur de plus, chargée à la demande (lazyComponent), rien n'est lu avant l'ouverture. Chargement,
// vide, erreur avec « Réessayer », nombre annoncé dans une région polie. Expose `reload()` (après un nouvel envoi).
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useApi } from '@/app/useApi';
import { feedApi } from '@/feed/api';
import type { Post } from '@/feed/types';
import XmPost from '@/components/XmPost.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';

const { t } = useI18n();
const api = feedApi(useApi());
const items = ref<Post[]>([]);
const state = ref<'loading' | 'ready' | 'error'>('loading');
const announce = ref('');

async function reload() {
    state.value = 'loading';
    try {
        items.value = await api.review();
        state.value = 'ready';
        announce.value = t('feed.video.mine_loaded', { count: items.value.length }, items.value.length);
    } catch {
        state.value = 'error';
    }
}

const replace = (post: Post) => { items.value = items.value.map((item) => (item.id === post.id ? post : item)); };
const onRemoved = (id: string) => { items.value = items.value.filter((item) => item.id !== id); };

onMounted(reload);
defineExpose({ reload });
</script>

<template>
    <section id="mes-videos" class="vstack gap-3" aria-labelledby="myVideosTitle" tabindex="-1" data-xm-my-videos>
        <div>
            <h2 id="myVideosTitle" class="h5 mb-1">{{ t('feed.video.mine_title') }}</h2>
            <p class="small text-secondary mb-0">{{ t('feed.video.mine_intro') }}</p>
        </div>
        <div class="vstack gap-3" :aria-busy="state === 'loading' ? 'true' : 'false'">
            <XmSkeleton v-if="state === 'loading'" type="card" :label="t('feed.video.mine_loading')" />
            <div v-else-if="state === 'error'" class="alert alert-warning small mb-0" role="alert">
                <p class="mb-2">{{ t('feed.video.mine_error') }}</p>
                <button type="button" class="btn btn-outline-secondary btn-sm xm-media-btn" @click="reload">{{ t('feed.video.mine_retry') }}</button>
            </div>
            <template v-else>
                <XmPost v-for="post in items" :key="post.id" :post="post" :comment-form="false" @update:post="replace" @removed="onRemoved" />
                <div v-if="items.length === 0" class="card card-body text-center text-secondary small">{{ t('feed.video.mine_empty') }}</div>
            </template>
        </div>
        <p class="visually-hidden" role="status" aria-live="polite">{{ announce }}</p>
    </section>
</template>
