<script setup lang="ts">
// Statut flashé par Laravel (`session('status')`, props partagées flash.status) gardé à l'écran en XmAlert, en plus du
// toast (app/flash.ts) : un toast n'est jamais la seule source d'information (NOTIFICATIONS_INSTRUCTION §4). Monté par
// app.ts en tête de la racine ; le gabarit membre Vue (P6-03) le placera sous l'en-tête. Fermable.
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import XmAlert from './XmAlert.vue';
import type { FlashMessage } from '@/app/types';

const ALERT_TYPES = new Set(['primary', 'info', 'success', 'warning', 'danger']);

const page = usePage();
const dismissed = ref<string | null>(null);

const status = computed<FlashMessage | null>(() => {
    const flash = (page.props as { flash?: { status?: FlashMessage | null } } | undefined)?.flash;
    const item = flash?.status ?? null;
    return item && item.message && item.id !== dismissed.value ? item : null;
});
const alertType = computed(() => (status.value && ALERT_TYPES.has(status.value.type) ? status.value.type : 'info'));

function dismiss() {
    dismissed.value = status.value?.id ?? null;
}
</script>

<template>
    <div v-if="status" :key="status.id" class="container pt-3" data-xm-flash-status>
        <XmAlert :type="alertType" dismissible class="mb-0" @dismiss="dismiss">{{ status.message }}</XmAlert>
    </div>
</template>
