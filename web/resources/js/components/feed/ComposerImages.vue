<script setup lang="ts">
// Éditeur de photos des modales « Créer une publication » et « Créer un sondage » (P7-10, Q96 ; docs/API.md §8.3) :
// sélection multiple (jusqu'à `max`, compteur « 2 / 4 »), une vignette par photo avec son aperçu, **un texte
// alternatif par photo** (≤ 255 caractères, facultatif, comme le serveur), retrait, réordonnancement par boutons
// Monter / Descendre au clavier (l'ordre de la liste est l'ordre de publication ; annoncé par une région polie), erreurs
// par photo (`images.N` : fichier refusé ; `images_alt.N` : texte) rattachées au champ de la photo (aria-invalid,
// aria-describedby) et ancrées pour <XmErrorSummary> (#f-images-N). Chargé avec les modales (jamais dans app.ts).
// Props : images, max, accept, maxMb, errors (clés du composeur), locked (une vidéo est choisie : champ désactivé et
// expliqué), announcement (texte annoncé), prefix (identifiants). Émet add(files), remove(id), move(id, ±1), alt(id, texte).
import { computed, nextTick, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { ImageItem } from '@/feed/composer';

const props = withDefaults(defineProps<{
    images: ImageItem[];
    max: number;
    accept: string;
    maxMb: number;
    errors?: Record<string, string>;
    locked?: boolean;
    announcement?: string;
    prefix?: string;
}>(), { errors: () => ({}), locked: false, announcement: '', prefix: 'f-images' });

const emit = defineEmits<{ add: [files: File[]]; remove: [id: number]; move: [id: number, delta: -1 | 1]; alt: [id: number, value: string] }>();

const { t } = useI18n();
const input = ref<HTMLInputElement | null>(null);
const full = computed(() => props.images.length >= props.max);
const disabled = computed(() => props.locked || full.value);
const listId = computed(() => `${props.prefix}-list`);
const hintId = computed(() => `${props.prefix}-hint`);

const itemId = (index: number) => `${props.prefix}-${index}`;
const altId = (index: number) => `f-images_alt-${index}`;
const describedBy = (index: number) => [`${altId(index)}-hint`, props.errors[`images.${index}`] ? `${itemId(index)}-error` : null, props.errors[`images_alt.${index}`] ? `${altId(index)}-error` : null].filter(Boolean).join(' ');
const invalid = (index: number) => Boolean(props.errors[`images.${index}`] || props.errors[`images_alt.${index}`]);

function onChange(event: Event) {
    const field = event.target as HTMLInputElement;
    const files = Array.from(field.files ?? []);
    // Même fichier rechoisi plus tard : le champ est vidé (les fichiers sont gardés par le composeur).
    field.value = '';
    if (files.length > 0) emit('add', files);
}

const focusById = (id: string) => nextTick(() => document.getElementById(id)?.focus());

function move(item: ImageItem, index: number, delta: -1 | 1) {
    emit('move', item.id, delta);
    // Le focus suit la photo déplacée (le bouton de sa nouvelle place ; s'il est désactivé en bout de liste, l'autre).
    const target = index + delta;
    const last = props.images.length - 1;
    const direction = delta < 0 ? (target === 0 ? 'down' : 'up') : (target === last ? 'up' : 'down');
    focusById(`${props.prefix}-${target}-${direction}`);
}

function remove(item: ImageItem, index: number) {
    emit('remove', item.id);
    // Le champ de la photo qui prend la place, sinon la précédente, sinon le choix de fichiers.
    const remaining = props.images.length - 1;
    focusById(remaining === 0 ? `${props.prefix}-field` : altId(Math.min(index, remaining - 1)));
}

defineExpose({ focus: () => input.value?.focus() });
</script>

<template>
    <div :id="prefix" class="mb-2" tabindex="-1" data-xm-images>
        <label class="form-label small mb-1" :for="`${prefix}-field`"><i class="bi bi-image-fill text-success me-1" aria-hidden="true"></i>{{ t('feed.media.images_legend') }}</label>
        <input :id="`${prefix}-field`" ref="input" class="form-control form-control-sm" type="file" name="images[]" multiple :accept="accept" :disabled="disabled" :aria-describedby="`${hintId} ${prefix}-counter`" :aria-invalid="errors.images ? 'true' : undefined" @change="onChange">
        <div :id="hintId" class="form-text">{{ locked ? t('feed.media.images_locked') : full ? t('feed.media.images_full', { max }) : t('feed.media.images_help', { size: maxMb, max }) }}</div>
        <div :id="`${prefix}-counter`" class="form-text">
            <span aria-hidden="true">{{ t('feed.media.images_counter', { count: images.length, max }) }}</span>
            <span class="visually-hidden">{{ t('feed.media.images_counter_sr', { count: images.length, max }, images.length) }}</span>
        </div>
        <div v-if="errors.images" :id="`${prefix}-error`" class="invalid-feedback d-block" role="alert"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors.images }}</div>
        <p class="visually-hidden" role="status" aria-live="polite" data-xm-images-live>{{ announcement }}</p>

        <ol v-if="images.length > 0" :id="listId" class="list-unstyled vstack gap-2 mt-2 mb-0" :aria-label="t('feed.media.images_list')">
            <li v-for="(item, index) in images" :id="itemId(index)" :key="item.id" class="card card-body p-2 xm-media-item" tabindex="-1" :data-xm-image="index">
                <div class="d-flex gap-2">
                    <img v-if="item.preview" :src="item.preview" class="rounded xm-media-thumb" :alt="t('feed.media.image_preview_alt', { n: index + 1 })" width="96" height="96">
                    <span v-else class="xm-media-thumb rounded bg-light d-inline-flex align-items-center justify-content-center" aria-hidden="true"><i class="bi bi-image fs-3"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <p class="small fw-bold mb-1">{{ t('feed.media.image_n', { n: index + 1 }) }}</p>
                        <label class="form-label small mb-1" :for="altId(index)">{{ t('feed.media.image_alt_label', { n: index + 1 }) }}</label>
                        <input :id="altId(index)" class="form-control form-control-sm" type="text" :name="`images_alt[${index}]`" maxlength="255" :value="item.alt" :aria-invalid="invalid(index) ? 'true' : undefined" :aria-describedby="describedBy(index)" @input="emit('alt', item.id, ($event.target as HTMLInputElement).value)">
                        <div :id="`${altId(index)}-hint`" class="form-text">{{ t('feed.media.image_alt_help') }}</div>
                        <div v-if="errors[`images.${index}`]" :id="`${itemId(index)}-error`" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors[`images.${index}`] }}</div>
                        <div v-if="errors[`images_alt.${index}`]" :id="`${altId(index)}-error`" class="invalid-feedback d-block"><i class="bi bi-exclamation-circle me-1" aria-hidden="true"></i>{{ errors[`images_alt.${index}`] }}</div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <button :id="`${itemId(index)}-up`" type="button" class="btn btn-light btn-sm xm-media-btn" :disabled="index === 0" :aria-label="t('feed.media.image_up', { n: index + 1 })" @click="move(item, index, -1)"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
                    <button :id="`${itemId(index)}-down`" type="button" class="btn btn-light btn-sm xm-media-btn" :disabled="index === images.length - 1" :aria-label="t('feed.media.image_down', { n: index + 1 })" @click="move(item, index, 1)"><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
                    <button :id="`${itemId(index)}-remove`" type="button" class="btn btn-light btn-sm text-danger xm-media-btn" :aria-label="t('feed.media.image_remove', { n: index + 1 })" @click="remove(item, index)"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
            </li>
        </ol>
    </div>
</template>
