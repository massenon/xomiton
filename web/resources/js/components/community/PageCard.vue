<script setup lang="ts">
// Carte d'une page d'organisation (lot groupes-pages, P8-03 ; prototype pages.php l.51-67) : couverture 16/9
// (décorative), catégorie (.xm-chip), badges « Page vérifiée » et « En avant », nom (lien vers la fiche), description,
// abonné·es, bouton « Suivre la page » / « Abonné·e » (émet `toggle` ; l'état est porté par le libellé, sans
// aria-pressed qui l'annoncerait deux fois) ; page en attente (« Mes pages ») : statut à la place du bouton.
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { OrganizationPage } from '@/community/types';

withDefaults(defineProps<{ page: OrganizationPage; busy?: boolean }>(), { busy: false });
const emit = defineEmits<{ toggle: [page: OrganizationPage] }>();

const { t } = useI18n();
</script>

<template>
    <div class="col-sm-6 col-xl-4">
        <article class="card h-100 overflow-hidden xm-card-hover" :aria-labelledby="`page-${page.id}`">
            <div class="xm-ratio-16x9"><img class="xm-img-cover" :src="page.cover_url" alt="" loading="lazy" decoding="async" width="640" height="360"></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-1 mb-2">
                    <span class="xm-chip">{{ page.category }}</span>
                    <span v-if="page.is_verified" class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-patch-check me-1" aria-hidden="true"></i>{{ t('community.pages.verified') }}</span>
                    <span v-if="page.is_featured" class="badge bg-warning text-dark">{{ t('community.pages.featured') }}</span>
                </div>
                <h2 :id="`page-${page.id}`" class="h6 mb-1"><Link :href="page.url">{{ page.name }}</Link></h2>
                <p v-if="page.description" class="small text-secondary mb-2 xm-group-description">{{ page.description }}</p>
                <p class="small mb-0"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ t('community.pages.followers', { count: page.followers_count }, page.followers_count) }}</p>
            </div>
            <div class="card-footer bg-transparent border-0 pt-0">
                <p v-if="page.status === 'pending'" class="small mb-0"><span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ page.status_label }}</span></p>
                <button v-else-if="page.can.follow" :class="['btn btn-sm w-100', page.following ? 'btn-success-soft' : 'btn-primary-soft']" type="button" :disabled="busy" :aria-busy="busy ? 'true' : undefined" @click="emit('toggle', page)"><i :class="`bi bi-${page.following ? 'check2' : 'plus-lg'} me-1`" aria-hidden="true"></i>{{ page.following ? t('community.pages.actions.following') : t('community.pages.actions.follow') }}<span class="visually-hidden"> – {{ page.name }}</span></button>
            </div>
        </article>
    </div>
</template>
