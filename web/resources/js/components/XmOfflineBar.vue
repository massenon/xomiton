<script setup lang="ts">
// Bandeau hors connexion (P6-05, ADR-26) : même HTML que celui des gabarits Blade (#xmOfflineBar.xm-offline-bar,
// role="status", affiché par la classe body.xm-is-offline, critical.css). Suit l'état réseau du store `ui` (évènements
// online / offline relayés par app.ts). text : registre de la page (défaut : espace connecté, tu).
import { onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useUiStore } from '@/stores/ui';

const props = withDefaults(defineProps<{ text?: string | null }>(), { text: null });

const ui = useUiStore();
const { t } = useI18n();

watch(() => ui.online, (online) => {
    if (typeof document !== 'undefined') document.body.classList.toggle('xm-is-offline', !online);
}, { immediate: true });
onBeforeUnmount(() => document.body.classList.remove('xm-is-offline'));
</script>

<template>
    <div id="xmOfflineBar" class="xm-offline-bar" role="status"><i class="bi bi-wifi-off me-1" aria-hidden="true"></i> {{ props.text ?? t('menus.member_more.offline_banner') }}</div>
</template>
