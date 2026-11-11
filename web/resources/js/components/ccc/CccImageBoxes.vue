<script setup lang="ts">
// Boîtes à images (P8-12 ; prototype boites-a-images.php) : grille des boîtes publiées (image, titre, nombre d'images,
// guide de l'animateur·rice) puis « Mode animation » plein écran (<CccSlideshow>, chargé à la demande) sur toutes les
// images ou sur une seule boîte. Les questions types du prototype restent sous la grille.
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import XmButton from '@/components/XmButton.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import CccImage from './CccImage.vue';
import CccSlideshow from './CccSlideshow.vue';
import { route } from '@/app/routes';
import type { PagedTool, Slide } from '@/ccc/types';

const props = withDefaults(defineProps<{
    boxes: PagedTool[];
    presentation?: boolean;
}>(), { presentation: false });

const { t } = useI18n();
const playing = ref<{ slides: Slide[]; start: number } | null>(null);
const directory = route('annuaire', undefined, '/annuaire');
const tips = computed(() => [0, 1, 2, 3, 4].map((rank) => t(`ccc.images.tips.${rank}`)));

function slidesOf(boxes: PagedTool[]): Slide[] {
    return boxes.flatMap((box) => box.pages.map((page) => ({
        key: `${box.key}-${page.position}`,
        title: box.title,
        guide: box.description,
        image: page.image,
        caption: page.caption,
        alt: page.alt,
    })));
}

const allSlides = computed(() => slidesOf(props.boxes));

function play(boxes: PagedTool[]): void {
    const slides = slidesOf(boxes);
    if (slides.length > 0) playing.value = { slides, start: 0 };
}
</script>

<template>
    <section class="vstack gap-4" aria-labelledby="ccc-images-title">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 id="ccc-images-title" class="h4 mb-1">{{ t('ccc.images.heading') }}</h2>
                <p class="text-secondary mb-0">{{ t('ccc.images.intro') }}</p>
            </div>
            <XmButton v-if="allSlides.length > 0" variant="primary" icon="fullscreen" data-ccc-start @click="play(boxes)">{{ t('ccc.images.start') }}</XmButton>
        </div>

        <XmEmptyState v-if="boxes.length === 0" icon="images" :title="t('ccc.empty.boites_images')">{{ t('ccc.empty.hint') }}</XmEmptyState>

        <template v-else>
            <ul class="list-unstyled row g-3 mb-0">
                <li v-for="(box, rank) in boxes" :key="box.key" class="col-12 col-sm-6 col-xl-4">
                    <article class="card h-100 overflow-hidden" :aria-labelledby="`ccc-box-${box.key}`">
                        <div class="position-relative">
                            <CccImage :src="box.cover" :alt="box.pages[0]?.alt ?? box.title" ratio="16x9" />
                            <span class="position-absolute top-0 start-0 m-2 badge bg-primary">{{ t('ccc.images.badge', { number: rank + 1 }) }}</span>
                            <span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75">{{ t('ccc.images.count', { count: box.pages.length }, box.pages.length) }}</span>
                        </div>
                        <div class="card-body vstack gap-2">
                            <h3 :id="`ccc-box-${box.key}`" class="h6 mb-0">{{ box.title }}</h3>
                            <p v-if="box.description" class="small mb-0"><span class="visually-hidden">{{ t('ccc.images.guide') }}. </span>{{ box.description }}</p>
                        </div>
                        <div v-if="box.pages.length > 0" class="card-footer bg-transparent">
                            <button type="button" class="btn btn-primary-soft btn-sm" :aria-label="t('ccc.images.start_one', { title: box.title })" @click="play([box])"><i class="bi bi-fullscreen me-1" aria-hidden="true"></i>{{ t('ccc.images.start') }}</button>
                        </div>
                    </article>
                </li>
            </ul>

            <div class="card card-body">
                <h3 class="h5"><i class="bi bi-lightbulb me-1 text-warning" aria-hidden="true"></i>{{ t('ccc.images.tips_heading') }}</h3>
                <ol class="mb-0 small vstack gap-1"><li v-for="tip in tips" :key="tip">{{ tip }}</li></ol>
                <p class="small text-secondary mt-3 mb-0">{{ t('ccc.images.tips_reminder') }} <a :href="directory">{{ t('ccc.images.directory') }}</a></p>
            </div>
        </template>

        <CccSlideshow v-if="playing" :slides="playing.slides" :start="playing.start" :presentation="presentation" @close="playing = null" />
    </section>
</template>
