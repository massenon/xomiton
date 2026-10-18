<script setup lang="ts">
// Écran provisoire d'une route membre (P6-03, Member\PlaceholderPageController) dans le gabarit membre Vue : remplacé
// par l'écran réel de sa phase (P7 à P9). `aside` : colonnes du prototype fil.php (contenu col-md-8 col-lg-6 + colonne
// droite) ; sinon contenu col-lg-9 (profil, paramètres…). Textes : common.coming_soon_text, titre venu de la route.
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import XmRightColumn from '@/components/XmRightColumn.vue';

defineOptions({ inheritAttrs: false });

withDefaults(defineProps<{ title: string; prototype: string; aside?: boolean }>(), { aside: false });

const { t } = useI18n();
</script>

<template>
    <Head :title="title" />
    <div :class="aside ? 'col-md-8 col-lg-6 vstack gap-4' : 'col-lg-9'" data-xm-placeholder>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-tools display-5 text-primary" aria-hidden="true"></i>
                <h1 class="h3 mt-3">{{ title }}</h1>
                <p class="text-body-secondary mb-0">{{ t('common.coming_soon_text', { page: prototype }) }}</p>
            </div>
        </div>
    </div>
    <XmRightColumn v-if="aside" />
</template>
