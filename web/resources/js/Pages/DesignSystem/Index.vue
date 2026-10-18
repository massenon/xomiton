<script setup lang="ts">
// Catalogue des composants Vue Xm* (P6-04) servi par Inertia à /design-system/vue (local et recette) : page de
// démonstration de la coquille (P6-02), à comparer au catalogue Blade /design-system. Textes : lang/fr/design_system.php
// (`design_system.vue.*`). Pleine largeur, hors du gabarit membre (P6-03 : `layout: null`, son propre <main>).
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useToast } from '@/app/useToast';
import XmAccordion from '@/components/XmAccordion.vue';
import XmAlert from '@/components/XmAlert.vue';
import XmAvatar from '@/components/XmAvatar.vue';
import XmBadge from '@/components/XmBadge.vue';
import XmBell from '@/components/XmBell.vue';
import XmBreadcrumb from '@/components/XmBreadcrumb.vue';
import XmButton from '@/components/XmButton.vue';
import XmCard from '@/components/XmCard.vue';
import XmChart from '@/components/XmChart.vue';
import XmEmptyState from '@/components/XmEmptyState.vue';
import XmErrorSummary from '@/components/XmErrorSummary.vue';
import XmField from '@/components/XmField.vue';
import XmLogo from '@/components/XmLogo.vue';
import XmModal from '@/components/XmModal.vue';
import XmSkeleton from '@/components/XmSkeleton.vue';
import XmSosButton from '@/components/XmSosButton.vue';
import XmStat from '@/components/XmStat.vue';
import XmStatus from '@/components/XmStatus.vue';
import XmStepper from '@/components/XmStepper.vue';
import XmTabs from '@/components/XmTabs.vue';
import XmUrgency from '@/components/XmUrgency.vue';

defineOptions({ layout: null });

const { t } = useI18n();
const toast = useToast();
const s = (key: string) => t(`design_system.vue.samples.${key}`);
const pseudo = ref('');
const pseudoError = ref<string | null>(null);
const modalOpen = ref(false);
const checkPseudo = () => { pseudoError.value = pseudo.value.trim().length < 3 ? s('field_error') : null; };
</script>

<template>
    <Head :title="t('design_system.vue.title')" />
    <main id="contenu" tabindex="-1" class="container py-4">
        <XmBreadcrumb :items="[{ label: s('crumb_home'), url: '/' }, { label: s('crumb_current') }]" class="mb-3" />
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h1 class="h3 mb-0">{{ t('design_system.vue.heading') }}</h1>
            <XmLogo :width="120" />
        </div>
        <p class="text-secondary xm-prose">{{ t('design_system.vue.intro') }} <a href="/design-system">{{ t('design_system.vue.blade_link') }}</a></p>

        <section class="mb-5" aria-labelledby="dsButtons">
            <h2 id="dsButtons" class="h5">{{ t('design_system.sections.buttons') }}</h2>
            <div class="d-flex flex-wrap gap-2">
                <XmButton>{{ s('primary') }}</XmButton>
                <XmButton variant="secondary" icon="megaphone">{{ s('report') }}</XmButton>
                <XmButton variant="soft" icon="plus-lg">{{ s('soft') }}</XmButton>
                <XmButton variant="outline">{{ s('outline') }}</XmButton>
                <XmButton loading>{{ s('loading') }}</XmButton>
                <XmButton variant="icon" icon="gear" :label="s('settings')" />
            </div>
        </section>

        <section class="mb-5" aria-labelledby="dsBadges">
            <h2 id="dsBadges" class="h5">{{ t('design_system.sections.badges') }}</h2>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <XmBadge>{{ s('new') }}</XmBadge>
                <XmBadge color="success" soft icon="check-circle">{{ s('new') }}</XmBadge>
                <XmBadge scope="alert_type" status="sos" />
                <XmStatus status="in_progress" />
                <XmUrgency level="high" />
            </div>
        </section>

        <section class="mb-5" aria-labelledby="dsFields">
            <h2 id="dsFields" class="h5">{{ t('design_system.sections.fields') }}</h2>
            <XmErrorSummary :messages="pseudoError ? { pseudo: pseudoError } : {}" />
            <XmField v-model="pseudo" name="pseudo" :label="s('field_label')" :hint="s('field_hint')" :error="pseudoError" autocomplete="nickname" @blur="checkPseudo" />
        </section>

        <section class="mb-5" aria-labelledby="dsCards">
            <h2 id="dsCards" class="h5">{{ t('design_system.sections.cards') }}</h2>
            <div class="row g-3">
                <div class="col-md-6 col-xl-4">
                    <XmCard :title="s('card_title')" :badge="s('card_badge')" :meta="s('card_meta')" href="/thematiques">{{ s('card_text') }}</XmCard>
                </div>
                <div class="col-md-6 col-xl-4"><XmStat :label="s('stat_label')" value="128" icon="chat-dots" :trend="s('stat_trend')" direction="up" :sub="s('stat_sub')" /></div>
                <div class="col-md-6 col-xl-4"><XmSkeleton type="kpi" /></div>
            </div>
        </section>

        <section class="mb-5" aria-labelledby="dsFeedback">
            <h2 id="dsFeedback" class="h5">{{ t('design_system.sections.feedback') }}</h2>
            <XmAlert type="success" :title="s('alert_title')" dismissible>{{ s('alert_text') }}</XmAlert>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <XmButton variant="light" @click="toast.success(s('toast'))">{{ s('toast_show') }}</XmButton>
                <XmButton variant="light" @click="toast.error(s('toast_error'))">{{ s('toast_show_error') }}</XmButton>
                <XmButton variant="outline" @click="modalOpen = true">{{ s('modal_open') }}</XmButton>
            </div>
            <XmEmptyState :title="s('empty_title')" icon="people">
                {{ s('empty_text') }}
                <template #action><XmButton href="/groupes" variant="primary-soft">{{ s('empty_action') }}</XmButton></template>
            </XmEmptyState>
            <XmModal id="dsModal" v-model:open="modalOpen" type="delete" :title="s('modal_title')" @action="modalOpen = false">{{ s('modal_text') }}</XmModal>
        </section>

        <section class="mb-5" aria-labelledby="dsNav">
            <h2 id="dsNav" class="h5">{{ t('design_system.sections.navigation') }}</h2>
            <XmStepper :steps="[s('step_1'), s('step_2'), s('step_3')]" :current="2" />
            <XmTabs id="dsTabs" :tabs="{ infos: s('tab_infos'), notes: s('tab_notes') }" :param="null">
                <template #infos><p>{{ s('tab_infos_text') }}</p></template>
                <template #notes><p>{{ s('tab_notes_text') }}</p></template>
            </XmTabs>
            <XmAccordion id="dsFaq" html :items="[{ q: s('faq_q'), a: `<p>${s('faq_a')}</p>` }]" />
        </section>

        <section class="mb-5" aria-labelledby="dsMedia">
            <h2 id="dsMedia" class="h5">{{ t('design_system.sections.media') }}</h2>
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <XmAvatar :name="s('member')" />
                <XmAvatar :name="s('member')" size="lg" />
                <XmBell :items="[{ id: 'n1', text: s('notif'), time: s('notif_time'), href: '#', read: false }]" />
            </div>
            <div class="mt-3"><XmChart :label="s('chart_label')" :labels="['S1', 'S2', 'S3', 'S4']" :datasets="[{ label: s('stat_label'), data: [12, 3, 18, 24] }]" :height="220" /></div>
        </section>

        <section class="mb-5" aria-labelledby="dsSos">
            <h2 id="dsSos" class="h5">{{ t('design_system.sections.sos') }}</h2>
            <XmSosButton id="dsSos" :fixed="false" :managed="false" />
        </section>
    </main>
</template>
