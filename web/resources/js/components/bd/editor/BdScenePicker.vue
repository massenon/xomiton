<script setup lang="ts">
// Bibliothèque de scènes de l'éditeur des conversations en BD (P20-07) : seules les scènes **actives** validées par
// l'équipe (illustrations sans texte), jamais d'import d'image. Recherche dans le titre, la description et le casting ;
// chaque scène : vignette (ou repli rose pâle), titre, personnages présents, description ; bouton « Choisir cette scène »
// (44 px). Au clavier : la recherche puis les boutons, dans l'ordre.
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Scene } from '../editor';

const props = defineProps<{ scenes: Scene[]; heading: string }>();
const emit = defineEmits<{ choose: [key: string]; cancel: [] }>();

const { t } = useI18n();
const query = ref('');
const search = ref<HTMLInputElement | null>(null);
const LIMIT = 24;

const normalize = (text: string): string => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const filtered = computed(() => {
    const needle = normalize(query.value.trim());
    const list = needle === '' ? props.scenes : props.scenes.filter((scene) => normalize(`${scene.title} ${scene.description} ${scene.characters.map((c) => c.name).join(' ')}`).includes(needle));
    return list;
});
const shown = ref(LIMIT);
const visible = computed(() => filtered.value.slice(0, shown.value));

onMounted(() => search.value?.focus());
</script>

<template>
    <section class="xm-bde-picker border rounded p-3" aria-labelledby="bde-picker-title">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <h3 id="bde-picker-title" class="h6 mb-0 me-auto">{{ heading }}</h3>
            <button type="button" class="btn btn-sm btn-outline-secondary xm-bde-touch" @click="emit('cancel')">{{ t('conversations_bd.editor.scenes.cancel') }}</button>
        </div>
        <label class="form-label" for="bde-scene-search">{{ t('conversations_bd.editor.scenes.search') }}</label>
        <input id="bde-scene-search" ref="search" v-model="query" class="form-control mb-1" type="search" aria-describedby="bde-scene-count">
        <p id="bde-scene-count" class="small text-secondary" aria-live="polite">{{ t('conversations_bd.editor.scenes.count', { count: String(filtered.length) }) }}</p>
        <ul class="list-unstyled row g-3 mb-0">
            <li v-for="scene in visible" :key="scene.key" class="col-sm-6 col-xl-4">
                <article class="card h-100">
                    <img v-if="scene.image" :src="scene.image" alt="" class="card-img-top" width="1200" height="800" loading="lazy" decoding="async">
                    <div v-else class="xm-bdc-card-fallback" aria-hidden="true"><i class="bi bi-image"></i></div>
                    <div class="card-body vstack gap-1">
                        <h4 class="h6 mb-0">{{ scene.title }}</h4>
                        <p v-if="scene.characters.length > 0" class="small mb-0"><span class="fw-semibold">{{ t('conversations_bd.editor.scenes.cast') }}</span> {{ scene.characters.map((c) => c.name).join(', ') }}</p>
                        <p class="small text-secondary mb-2">{{ scene.description }}</p>
                        <button type="button" class="btn btn-primary btn-sm xm-bde-touch mt-auto" @click="emit('choose', scene.key)">
                            {{ t('conversations_bd.editor.scenes.choose') }}<span class="visually-hidden"> : {{ scene.title }}</span>
                        </button>
                    </div>
                </article>
            </li>
        </ul>
        <p v-if="filtered.length > shown" class="mt-3 mb-0">
            <button type="button" class="btn btn-outline-primary xm-bde-touch" @click="shown += LIMIT">{{ t('conversations_bd.editor.scenes.more') }}</button>
        </p>
    </section>
</template>
