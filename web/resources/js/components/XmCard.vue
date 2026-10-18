<script setup lang="ts">
// Carte du thème Social : même HTML que <x-card> (.card.xm-card--{variant} > [en-tête] .card-body [.card-footer]).
// variant : content | resource | news | event | contact | stat ; title (h3.h5.card-title, h2.h6 dans l'en-tête si
// header) ; titleTag ; href → carte entièrement cliquable (stretched-link + xm-card-hover + h-100 ; inertia → <Link>) ;
// badge (.xm-chip) ; image / imageAlt (16/9 via <XmImg>) ; meta ; header (carte « liste » : en-tête + actions).
// Slots : défaut (contenu), actions (en-tête si header, sinon pied).
import { computed, useSlots } from 'vue';
import { Link } from '@inertiajs/vue3';
import XmImg from './XmImg.vue';

const props = withDefaults(defineProps<{
    variant?: string;
    title?: string | null;
    titleTag?: string | null;
    href?: string | null;
    inertia?: boolean;
    badge?: string | null;
    image?: string | null;
    imageAlt?: string;
    meta?: string | null;
    header?: boolean;
}>(), { variant: 'content', title: null, titleTag: null, href: null, inertia: false, badge: null, image: null, imageAlt: '', meta: null, header: false });

const slots = useSlots();
const tag = computed(() => props.titleTag ?? (props.header ? 'h2' : 'h3'));
const linkTag = computed(() => (props.inertia ? Link : 'a'));
</script>

<template>
    <article :class="['card', `xm-card--${variant}`, { 'xm-card-hover h-100': Boolean(href) }]">
        <div v-if="image" class="xm-ratio-16x9"><XmImg :src="image" :alt="imageAlt" :title="imageAlt || null" class="card-img-top xm-img-cover" /></div>
        <div v-if="header" class="card-header d-flex justify-content-between align-items-center gap-2">
            <component :is="tag" v-if="title" class="h6 mb-0">{{ title }}</component>
            <div v-if="slots.actions" class="d-flex flex-wrap gap-2"><slot name="actions" /></div>
        </div>
        <div class="card-body">
            <span v-if="badge" class="xm-chip mb-2">{{ badge }}</span>
            <component :is="tag" v-if="title && !header" class="h5 card-title"><component :is="linkTag" v-if="href" :href="href" class="stretched-link text-reset text-decoration-none">{{ title }}</component><template v-else>{{ title }}</template></component>
            <p v-if="meta" class="small text-secondary mb-2">{{ meta }}</p>
            <slot />
        </div>
        <div v-if="!header && slots.actions" class="card-footer d-flex flex-wrap gap-2 position-relative"><slot name="actions" /></div>
    </article>
</template>
