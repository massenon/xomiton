<script setup lang="ts">
// Accordéon : même HTML que <x-accordion> (.accordion.accordion-flush > .accordion-item > .accordion-header >
// button.accordion-button ; un seul panneau ouvert, aria-expanded / aria-controls). L'état est tenu par Vue (pas de
// Bootstrap Collapse sur un DOM que Vue rend). items : [{ q, a }] ; `a` est du texte brut par défaut ; html (opt-in) :
// `a` rendu en HTML (v-html), réservé au HTML de confiance (lang, contenu éditorial assaini côté serveur avant d'être
// passé en prop) — jamais un contenu saisi par un membre ; id ; level (h2 | h3 | h4) ;
// open (index ouvert, null = tout fermé) ; flush.
import { computed, ref, useId } from 'vue';

const props = withDefaults(defineProps<{
    items: Array<{ q: string; a: string }>;
    id?: string | null;
    level?: 'h2' | 'h3' | 'h4';
    open?: number | null;
    flush?: boolean;
    html?: boolean;
}>(), { id: null, level: 'h3', open: 0, flush: true, html: false });

const uid = `acc${useId().replace(/[^A-Za-z0-9]/g, '')}`;
const base = computed(() => props.id ?? uid);
const opened = ref<number | null>(props.open);

function toggle(index: number) {
    opened.value = opened.value === index ? null : index;
}
</script>

<template>
    <div :id="base" :class="['accordion xm-accordion', { 'accordion-flush': flush }]">
        <div v-for="(item, i) in items" :key="i" class="accordion-item">
            <component :is="level" :id="`${base}-h${i}`" class="accordion-header">
                <button :class="['accordion-button fw-semibold', { collapsed: opened !== i }]" type="button" :aria-expanded="opened === i ? 'true' : 'false'" :aria-controls="`${base}-p${i}`" @click="toggle(i)">{{ item.q }}</button>
            </component>
            <div :id="`${base}-p${i}`" :class="['accordion-collapse collapse', { show: opened === i }]" :aria-labelledby="`${base}-h${i}`">
                <div v-if="html" class="accordion-body xm-prose" v-html="item.a"></div>
                <div v-else class="accordion-body xm-prose">{{ item.a }}</div>
            </div>
        </div>
    </div>
</template>
