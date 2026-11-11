<script setup lang="ts">
// Page `/ccc` (lot outils-ccc, P8-12 ; prototype ccc.php), page Inertia `Member/Ccc` dans le gabarit membre (col-lg-9 à
// côté du sidenav), **une seule implémentation pour le membre et le pair éducateur·rice** (P9-05) : le drapeau
// `presentation` (compte pair actif) ajoute le guide de l'animateur·rice au mode animation. Onglets Manuel · Boîtes à
// images · BD SSR · Lois (adresse `?onglet=` gardée) : manuel avec cases « point vu » et impression
// (<CccManual>), boîtes à images et « Mode animation » plein écran (<CccImageBoxes>), lecteur de BD (<CccComics>), carte
// vers /lois. Outils publiés seulement (jamais un outil masqué dans /admin/ccc), dans l'ordre stocké. La progression du
// manuel reste sur l'appareil. Page d'une session : le service worker ne la garde jamais (P5-10, X-Xm-Audience).
// Props : CccPageController (title, presentation, tools, laws).
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useSessionStore } from '@/stores/session';
import XmAlert from '@/components/XmAlert.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import XmTabs from '@/components/XmTabs.vue';
import CccComics from '@/components/ccc/CccComics.vue';
import CccImageBoxes from '@/components/ccc/CccImageBoxes.vue';
import CccManual from '@/components/ccc/CccManual.vue';
import type { CccLaws, CccTools } from '@/ccc/types';

const props = withDefaults(defineProps<{
    title: string;
    presentation?: boolean;
    tools?: Partial<CccTools>;
    laws?: CccLaws;
}>(), {
    presentation: false,
    tools: () => ({}),
    laws: () => ({ count: 0, url: '/lois' }),
});

const { t } = useI18n();
const session = useSessionStore();
const account = computed(() => session.user?.id ?? null);
const tabs = computed(() => ({
    manuel: t('ccc.tabs.manuel'),
    boites_images: t('ccc.tabs.boites_images'),
    bd: t('ccc.tabs.bd'),
    lois: t('ccc.tabs.lois'),
}));
</script>

<template>
    <Head :title="title" />
    <div class="col-lg-9 vstack gap-4 xm-ccc-page">
        <div class="card xm-hero-bg border-0 overflow-hidden xm-ccc-hero">
            <div class="card-body p-4 p-md-5">
                <span class="badge bg-white text-primary mb-2">{{ t('ccc.tagline') }}</span>
                <h1 class="h2 text-white">{{ t('ccc.heading') }}</h1>
                <p class="mb-0 xm-hero-sub" style="max-width: 560px">{{ t('ccc.intro') }}</p>
            </div>
        </div>

        <XmAlert v-if="presentation" type="info" icon="easel" data-ccc-presentation>
            <span class="badge bg-primary me-1">{{ t('ccc.presentation.badge') }}</span>{{ t('ccc.presentation.notice') }}
        </XmAlert>

        <XmTabs :tabs="tabs" :label="t('ccc.tabs.label')" id="ccc" param="onglet" active="manuel">
            <template #manuel><CccManual :modules="tools.manuel ?? []" :account="account" /></template>
            <template #boites_images><CccImageBoxes :boxes="tools.boites_images ?? []" :presentation="presentation" /></template>
            <template #bd><CccComics :comics="tools.bd ?? []" /></template>
            <template #lois>
                <section class="vstack gap-3" aria-labelledby="ccc-laws-title">
                    <div>
                        <h2 id="ccc-laws-title" class="h4 mb-1">{{ t('ccc.laws.heading') }}</h2>
                        <p class="text-secondary mb-0">{{ t('ccc.laws.intro') }}</p>
                    </div>
                    <XmEmptyState v-if="laws.count === 0" icon="bank2" :title="t('ccc.empty.lois')">{{ t('ccc.empty.hint') }}</XmEmptyState>
                    <div v-else class="card card-body d-flex flex-md-row align-items-md-center gap-3">
                        <span class="icon-lg rounded-circle bg-info bg-opacity-10 text-info d-inline-flex align-items-center justify-content-center flex-shrink-0" aria-hidden="true"><i class="bi bi-bank2 fs-4"></i></span>
                        <div class="flex-grow-1">
                            <p class="fw-semibold mb-0">{{ t('ccc.laws.count', { count: laws.count }, laws.count) }}</p>
                        </div>
                        <a class="btn btn-primary" :href="laws.url">{{ t('ccc.laws.open') }}</a>
                    </div>
                </section>
            </template>
        </XmTabs>
    </div>
</template>
