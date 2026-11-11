<script setup lang="ts">
// Fil d'Ariane : même HTML que <x-breadcrumb> (nav[aria-label] > ol.breadcrumb ; dernier élément actif,
// aria-current="page"). items : [{ label, url? }] ; inertia → liens <Link>. Pas de JSON-LD : l'espace connecté n'est
// jamais indexé (ADR-30).
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = withDefaults(defineProps<{
    items: Array<{ label: string; url?: string | null }>;
    label?: string | null;
    inertia?: boolean;
}>(), { label: null, inertia: false });

const { t } = useI18n();
const linkTag = computed(() => (props.inertia ? Link : 'a'));
</script>

<template>
    <nav :aria-label="label ?? t('components.breadcrumb.label')">
        <ol class="breadcrumb mb-0 small">
            <template v-for="(item, i) in items" :key="i">
                <li v-if="i === items.length - 1 || !item.url" class="breadcrumb-item active" aria-current="page">{{ item.label }}</li>
                <li v-else class="breadcrumb-item"><component :is="linkTag" :href="item.url">{{ item.label }}</component></li>
            </template>
        </ol>
    </nav>
</template>
