<script setup lang="ts">
// Image : même HTML que <x-img> (COMPOSANTS §Images, PERFORMANCE.md §2) : alt obligatoire (erreur sinon ; alt="" +
// aria-hidden pour une image décorative), loading="lazy" + decoding="async", priority → image LCP (eager,
// fetchpriority="high"), width / height contre le décalage de mise en page. Sources : src (+ srcset / sizes),
// avif / webp / variants → <picture> AVIF → WebP → src, ou media (forme de App\Support\ImageSources::fromMedia :
// { src, srcset, width, height, sources } déjà résolue par l'API).
import { computed, useAttrs } from 'vue';

defineOptions({ inheritAttrs: false });

export interface ResolvedMedia {
    src: string;
    srcset?: string | null;
    width?: number | null;
    height?: number | null;
    sources?: Record<string, string>;
}

const props = withDefaults(defineProps<{
    src?: string | null;
    alt?: string;
    title?: string | null;
    width?: number | string | null;
    height?: number | string | null;
    priority?: boolean;
    srcset?: string | null;
    sizes?: string | null;
    webp?: string | null;
    avif?: string | null;
    variants?: Record<string, string>;
    media?: ResolvedMedia | null;
}>(), { src: null, alt: undefined, title: null, width: null, height: null, priority: false, srcset: null, sizes: null, webp: null, avif: null, variants: () => ({}), media: null });

/** = App\Support\ImageSources::DEFAULT_SIZES */
const DEFAULT_SIZES = '(max-width: 1600px) 100vw, 1600px';
const ORDER: Record<string, number> = { 'image/avif': 0, 'image/webp': 1 };

if (props.alt === undefined || props.alt === null) {
    throw new Error('<XmImg> exige une prop alt (utiliser alt="" pour une image décorative).');
}
if (!props.src && !props.media) {
    throw new Error('<XmImg> exige une source : src, ou media.');
}

const attrs = useAttrs();

const sources = computed(() => {
    const list: Record<string, string> = { ...props.variants };
    if (props.avif) list['image/avif'] = props.avif;
    if (props.webp) list['image/webp'] = props.webp;
    const merged = props.media?.sources ? { ...list, ...Object.fromEntries(Object.entries(props.media.sources).filter(([type]) => !(type in list))) } : list;
    return Object.entries(merged).filter(([, value]) => Boolean(value)).sort(([a], [b]) => (ORDER[a] ?? 2) - (ORDER[b] ?? 2));
});

const img = computed(() => {
    const src = props.src ?? props.media?.src ?? '';
    const srcset = props.srcset ?? props.media?.srcset ?? null;
    const width = props.width ?? props.media?.width ?? null;
    const height = props.height ?? props.media?.height ?? null;
    const hasCandidates = srcset !== null || sources.value.length > 0;
    const sizes = hasCandidates ? (props.sizes ?? DEFAULT_SIZES) : null;
    const decorative = props.alt === '';
    const out: Record<string, unknown> = {
        src,
        alt: props.alt,
        title: decorative ? undefined : (props.title ?? props.alt),
        width: width ?? undefined,
        height: height ?? undefined,
        srcset: srcset ?? undefined,
        sizes: srcset ? sizes : undefined,
        loading: props.priority ? 'eager' : 'lazy',
        decoding: 'async',
        fetchpriority: props.priority ? 'high' : undefined,
        'aria-hidden': decorative ? 'true' : undefined,
    };
    return { attrs: { ...out, ...attrs }, sizes };
});
</script>

<template>
    <picture v-if="sources.length > 0">
        <source v-for="[type, candidates] in sources" :key="type" :type="type" :srcset="candidates" :sizes="img.sizes ?? undefined">
        <img v-bind="img.attrs">
    </picture>
    <img v-else v-bind="img.attrs">
</template>
