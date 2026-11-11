<script setup lang="ts">
// Bandes dessinées SSR (P8-12 ; prototype bd-ssr.php) : liste des histoires publiées puis lecteur de planches (image avec
// son texte alternatif, légende, compteur, barre de progression `role="progressbar"`, boutons Précédent / Suivant,
// clavier ← →). À la dernière planche, « Terminer » ouvre les questions de discussion. Chaque changement de planche
// est annoncé (`aria-live`). Aucune progression de lecture n'est gardée.
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import XmButton from '@/components/XmButton.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import CccImage from './CccImage.vue';
import { percentOf } from '@/ccc/progress';
import type { PagedTool } from '@/ccc/types';

const props = defineProps<{ comics: PagedTool[] }>();

const { t } = useI18n();
const openKey = ref<string | null>(null);
const index = ref(0);
const finished = ref(false);
const announce = ref('');
const readerTitle = ref<HTMLElement | null>(null);
const discussion = ref<HTMLElement | null>(null);
const openers = ref<Record<string, HTMLElement | null>>({});

const comic = computed(() => props.comics.find((item) => item.key === openKey.value) ?? null);
const total = computed(() => comic.value?.pages.length ?? 0);
const page = computed(() => comic.value?.pages[index.value] ?? null);
const percent = computed(() => percentOf(index.value + 1, total.value));
const last = computed(() => total.value > 0 && index.value === total.value - 1);
const discuss = computed(() => [0, 1, 2].map((rank) => t(`ccc.comic.discuss_items.${rank}`)));

watch(() => props.comics, (comics) => {
    if (openKey.value !== null && !comics.some((item) => item.key === openKey.value)) openKey.value = null;
});

function say(): void {
    const current = page.value;
    announce.value = current === null ? '' : t('ccc.comic.page_announce', { current: index.value + 1, total: total.value, caption: current.caption ?? current.alt });
}

async function open(item: PagedTool): Promise<void> {
    openKey.value = item.key;
    index.value = 0;
    finished.value = false;
    announce.value = '';
    await nextTick();
    readerTitle.value?.focus();
}

async function close(): Promise<void> {
    const key = openKey.value;
    openKey.value = null;
    await nextTick();
    if (key) openers.value[key]?.focus();
}

function go(delta: number): void {
    const next = Math.min(Math.max(index.value + delta, 0), Math.max(total.value - 1, 0));
    if (next === index.value) return;
    index.value = next;
    finished.value = false;
    say();
}

async function advance(): Promise<void> {
    if (!last.value) {
        go(1);
        return;
    }
    finished.value = true;
    announce.value = `${t('ccc.comic.done_title')}. ${t('ccc.comic.done')}`;
    await nextTick();
    discussion.value?.focus();
}

function restart(): void {
    index.value = 0;
    finished.value = false;
    say();
}

function onKeydown(event: KeyboardEvent): void {
    if (comic.value === null || event.altKey || event.ctrlKey || event.metaKey) return;
    const target = event.target;
    if (target instanceof HTMLElement && /^(input|textarea|select)$/i.test(target.tagName)) return;
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        if (!last.value) go(1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        go(-1);
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <section class="vstack gap-4" aria-labelledby="ccc-bd-title">
        <div>
            <h2 id="ccc-bd-title" class="h4 mb-1">{{ t('ccc.comic.heading') }}</h2>
            <p class="text-secondary mb-0">{{ t('ccc.comic.intro') }}</p>
        </div>

        <XmEmptyState v-if="comics.length === 0" icon="chat-square-quote" :title="t('ccc.empty.bd')">{{ t('ccc.empty.hint') }}</XmEmptyState>

        <ul v-else-if="comic === null" class="list-unstyled row g-3 mb-0" data-ccc-comics>
            <li v-for="item in comics" :key="item.key" class="col-12 col-sm-6 col-xl-4">
                <article class="card h-100 overflow-hidden" :aria-labelledby="`ccc-bd-${item.key}`">
                    <div class="position-relative">
                        <CccImage :src="item.cover" :alt="item.pages[0]?.alt ?? item.title" ratio="16x9" />
                        <span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75">{{ t('ccc.comic.pages_count', { count: item.pages.length }, item.pages.length) }}</span>
                    </div>
                    <div class="card-body">
                        <h3 :id="`ccc-bd-${item.key}`" class="h6 mb-1">{{ item.title }}</h3>
                        <p v-if="item.description" class="small text-secondary mb-0">{{ item.description }}</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <button :ref="(el) => { openers[item.key] = el as HTMLElement | null; }" type="button" class="btn btn-primary-soft btn-sm" :aria-label="`${t('ccc.comic.read')} : ${item.title}`" :disabled="item.pages.length === 0" @click="open(item)">{{ t('ccc.comic.read') }}</button>
                    </div>
                </article>
            </li>
        </ul>

        <div v-else class="vstack gap-3" role="region" :aria-label="t('ccc.comic.reader_label', { title: comic.title })" data-ccc-reader>
            <div><XmButton variant="ghost" size="sm" icon="arrow-left" class="p-0" @click="close">{{ t('ccc.comic.back') }}</XmButton></div>
            <h3 ref="readerTitle" class="h5 mb-0" tabindex="-1">{{ comic.title }}</h3>
            <div v-if="page" class="xm-comic-panel">
                <div class="position-relative">
                    <CccImage :key="page.image + index" :src="page.image" :alt="page.alt" ratio="4x3" />
                    <span class="position-absolute top-0 start-0 m-2 badge bg-dark" data-ccc-counter>{{ t('ccc.comic.page_counter', { current: index + 1, total }) }}</span>
                </div>
                <div v-if="page.caption" class="cap" data-ccc-caption>{{ page.caption }}</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <XmButton variant="primary-soft" icon="chevron-left" :disabled="index === 0" @click="go(-1)">{{ t('ccc.comic.previous') }}</XmButton>
                <div class="progress flex-grow-1 xm-ccc-bar" role="progressbar" :aria-label="t('ccc.comic.progress')" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="percent" :aria-valuetext="t('ccc.comic.page_counter', { current: index + 1, total })">
                    <div class="progress-bar" :style="{ width: `${percent}%` }"></div>
                </div>
                <XmButton variant="primary" @click="advance">{{ last ? t('ccc.comic.finish') : t('ccc.comic.next') }}</XmButton>
            </div>
            <p class="small text-secondary mb-0 d-none d-md-block">{{ t('ccc.comic.keys') }}</p>

            <div v-if="finished" ref="discussion" class="card card-body" tabindex="-1" data-ccc-discussion>
                <h4 class="h6"><i class="bi bi-chat-heart me-1 text-primary" aria-hidden="true"></i>{{ t('ccc.comic.discuss') }}</h4>
                <ul class="small"><li v-for="item in discuss" :key="item">{{ item }}</li></ul>
                <div><XmButton variant="light" size="sm" @click="restart">{{ t('ccc.comic.restart') }}</XmButton></div>
            </div>
        </div>

        <div class="visually-hidden" role="status" aria-live="polite" aria-atomic="true" data-ccc-live>{{ announce }}</div>
    </section>
</template>
