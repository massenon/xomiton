<script setup lang="ts">
// Page `/pages/{ulid}` (lot groupes-pages, P8-03 ; pas d'écran dans le prototype : même carte d'en-tête que
// group-details.php), page Inertia `Community/PageShow` dans le gabarit membre (col-md-8 col-lg-6 + colonne droite) :
// couverture, nom, catégorie, badges « Page vérifiée » / « En avant », abonné·es, « Suivre la page » / « Abonné·e »,
// À propos (description, site web en lien externe `rel="noopener noreferrer"`). Page en attente d'approbation : bandeau
// pour sa propriétaire (elle seule la voit, avec la modération : P12-05), sans bouton Suivre.
// Props : PagesPageController::show (title, organization).
import { computed, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { route } from '@/app/routes';
import { usePageFollow } from '@/community/usePageFollow';
import type { OrganizationPage } from '@/community/types';
import XmRightColumn from '@/components/XmRightColumn.vue';

const props = defineProps<{ title: string; organization: OrganizationPage }>();

const { t } = useI18n();
const current = ref<OrganizationPage>({ ...props.organization });
watch(() => props.organization, (page) => { current.value = { ...page }; });

const pending = computed(() => current.value.status === 'pending');
const back = route('pages.index', undefined, '/pages');
const { busy, toggle } = usePageFollow((page) => { current.value = page; });
</script>

<template>
    <Head :title="title" />
    <div class="col-md-8 col-lg-6 vstack gap-4">
        <div v-if="pending" class="alert alert-warning mb-0" role="status"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i>{{ t('community.pages.pending_banner') }}</div>
        <div class="card overflow-hidden">
            <div class="xm-group-cover"><img class="xm-img-cover" :src="current.cover_url" :alt="t('community.pages.cover_alt', { name: current.name })" decoding="async" fetchpriority="high"></div>
            <div class="card-body">
                <div class="d-md-flex align-items-center justify-content-between gap-3">
                    <div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="xm-chip">{{ current.category }}</span>
                            <span v-if="current.is_verified" class="badge bg-success bg-opacity-10 text-success"><i class="bi bi-patch-check me-1" aria-hidden="true"></i>{{ t('community.pages.verified') }}</span>
                            <span v-if="current.is_featured" class="badge bg-warning text-dark">{{ t('community.pages.featured') }}</span>
                        </div>
                        <h1 class="h4 mb-1">{{ current.name }}</h1>
                        <p class="small text-secondary mb-0"><i class="bi bi-people me-1" aria-hidden="true"></i>{{ t('community.pages.followers', { count: current.followers_count }, current.followers_count) }}</p>
                    </div>
                    <button v-if="current.can.follow" :class="['btn mt-3 mt-md-0', current.following ? 'btn-success-soft' : 'btn-primary']" type="button" :disabled="busy !== null" :aria-busy="busy ? 'true' : undefined" @click="toggle(current)"><i :class="`bi bi-${current.following ? 'check2' : 'plus-lg'} me-1`" aria-hidden="true"></i>{{ current.following ? t('community.pages.actions.following') : t('community.pages.actions.follow') }}</button>
                </div>
            </div>
        </div>
        <div class="card card-body">
            <h2 class="h6">{{ t('community.pages.detail.about') }}</h2>
            <p class="xm-group-description-full" :class="{ 'text-secondary': !current.description }">{{ current.description || t('community.pages.detail.no_description') }}</p>
            <p v-if="current.website" class="mb-0"><a :href="current.website" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>{{ t('community.pages.actions.website') }}<span class="visually-hidden"> {{ t('microcopy.a11y.opens_new_tab') }}</span></a></p>
        </div>
        <Link class="btn btn-light" :href="back"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>{{ t('community.pages.detail.all_pages') }}</Link>
    </div>
    <XmRightColumn />
</template>
