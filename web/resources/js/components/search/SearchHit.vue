<script setup lang="ts">
// Un résultat de la page /recherche (lot recherche-membre, P8-13) dans une liste `list-group` de section : même ligne
// pour les quatre familles — pastille (icône du type de ressource, initiales du contact, avatar du membre), titre en
// lien (h3 visuel h6 : la section porte le h2), ligne secondaire, extrait. Ressource et thématique : pages publiques
// Blade (lien simple) ; contact : Appeler (`tel:`), WhatsApp et « Voir dans l'annuaire » (visite Inertia, carte complète
// et demande de rappel) ; membre : profil public `/membres/{pseudo}` (visite Inertia). Aucune donnée privée n'arrive
// jusqu'ici (Resources de chaque famille). Props : hit.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { SearchHit } from '@/search/types';
import XmAvatar from '@/components/XmAvatar.vue';
import XmBadge from '@/components/XmBadge.vue';

const props = defineProps<{ hit: SearchHit }>();

const { t } = useI18n();

const headingId = computed(() => `xm-search-hit-${props.hit.type}-${props.hit.id}`);
const meta = computed(() => {
    const hit = props.hit;
    const parts: (string | null | undefined)[] = [];
    if (hit.type === 'ressources') parts.push(hit.kind_label, hit.category?.label, hit.file_size_label);
    if (hit.type === 'thematiques') parts.push(hit.category?.label, hit.reading_minutes > 0 ? t('search.hit.reading', { count: hit.reading_minutes }) : null);
    if (hit.type === 'contacts') parts.push(hit.org, [hit.commune?.name, hit.zone?.name].filter(Boolean).join(', ') || t('directory.card.national'));
    if (hit.type === 'membres') parts.push(hit.role_label, hit.zone?.name);
    return parts.filter((part): part is string => Boolean(part)).join(' · ');
});
const icon = computed(() => ('icon' in props.hit && props.hit.icon ? props.hit.icon : 'bi-folder2-open'));
</script>

<template>
    <li class="list-group-item px-0 py-3" :data-xm-hit="`${hit.type}-${hit.id}`">
        <article class="d-flex gap-3" :aria-labelledby="headingId">
            <XmAvatar v-if="hit.type === 'contacts'" class="flex-shrink-0" size="sm" :name="hit.name" aria-hidden="true" />
            <XmAvatar v-else-if="hit.type === 'membres'" class="flex-shrink-0" size="sm" :name="hit.pseudo" :src="hit.avatar_url" />
            <span v-else class="icon-md bg-primary-soft text-primary rounded-circle flex-shrink-0 d-inline-flex align-items-center justify-content-center" aria-hidden="true"><i :class="`bi ${icon}`"></i></span>

            <div class="xm-search-hit__body">
                <h3 :id="headingId" class="h6 mb-1 text-break">
                    <a v-if="hit.type === 'ressources' || hit.type === 'thematiques'" :href="hit.url">{{ hit.title }}</a>
                    <Link v-else-if="hit.type === 'contacts'" :href="hit.url">{{ hit.name }}</Link>
                    <Link v-else-if="hit.profile_url" :href="hit.profile_url">{{ hit.pseudo }}</Link>
                    <template v-else>{{ hit.pseudo }}</template>
                </h3>
                <div v-if="hit.type === 'contacts'" class="mb-1">
                    <XmBadge soft :color="hit.role_color">{{ hit.role_label }}</XmBadge>
                    <XmBadge v-if="!hit.is_public" soft color="neutral" icon="shield-lock" class="ms-1">{{ t('directory.card.reserved') }}</XmBadge>
                </div>
                <p v-if="meta" class="small text-secondary mb-1">{{ meta }}</p>
                <p v-if="(hit.type === 'ressources' || hit.type === 'thematiques') && hit.summary" class="small mb-0 xm-search-hit__excerpt">{{ hit.summary }}</p>
                <div v-if="hit.type === 'contacts'" class="d-flex flex-wrap gap-2 mt-2">
                    <a v-if="hit.tel" class="btn btn-sm btn-success" :href="hit.tel">
                        <i class="bi bi-telephone-fill me-1" aria-hidden="true"></i>{{ t('directory.card.call') }}<span class="visually-hidden"> {{ hit.name }} ({{ hit.phone }})</span>
                    </a>
                    <a v-if="hit.whatsapp_url" class="btn btn-sm btn-success-soft" :href="hit.whatsapp_url" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>{{ t('directory.card.whatsapp') }}<span class="visually-hidden"> {{ hit.name }} {{ t('microcopy.a11y.opens_new_tab') }}</span>
                    </a>
                    <Link class="btn btn-sm btn-light" :href="hit.url">{{ t('search.hit.directory') }}<span class="visually-hidden"> — {{ hit.name }}</span></Link>
                </div>
            </div>
        </article>
    </li>
</template>
