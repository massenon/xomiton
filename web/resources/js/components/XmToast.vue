<script setup lang="ts">
// Toasts de l'espace Vue : même HTML que <x-toast> et XM.toast de xomiton-ui.js (.toast-container haut-droite >
// .toast.text-bg-{type}, Bootstrap Toast 4,5 s, pause au survol et au focus gérée par Bootstrap, bouton Fermer).
// Chaque toast : role="status" + aria-live="polite", ou role="alert" + aria-live="assertive" (erreurs, SOS).
// La file vient du store `ui` (useToast()) ; un toast masqué en est retiré.
import { nextTick, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Toast } from '../shared/bootstrap.js';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const { t } = useI18n();
const started = new Map<number, InstanceType<typeof Toast>>();
// Libération différée : une transition Bootstrap en cours (fade 0,15 s) relit encore son élément.
const release = (instance: InstanceType<typeof Toast>) => setTimeout(() => instance.dispose(), 600);

function bind(el: Element | null, id: number, delay: number) {
    if (!(el instanceof HTMLElement) || started.has(id)) return;
    const instance = Toast.getOrCreateInstance(el, { delay });
    started.set(id, instance);
    el.addEventListener('hidden.bs.toast', () => {
        started.delete(id);
        instance.dispose();
        ui.dismissToast(id);
    }, { once: true });
    nextTick(() => instance.show());
}

watch(() => ui.toasts.map((toast) => toast.id), (ids) => {
    [...started.keys()].filter((id) => !ids.includes(id)).forEach((id) => {
        const instance = started.get(id);
        started.delete(id);
        if (instance) release(instance);
    });
});

onBeforeUnmount(() => {
    started.forEach(release);
    started.clear();
});
</script>

<template>
    <div id="xmToastWrap" class="toast-container position-fixed top-0 end-0 p-3">
        <div v-for="toast in ui.toasts" :key="toast.id" :ref="(el) => bind(el as Element | null, toast.id, toast.delay)" :class="`toast align-items-center text-bg-${toast.type} border-0`" :role="toast.urgent ? 'alert' : 'status'" :aria-live="toast.urgent ? 'assertive' : 'polite'" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">{{ toast.message }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" :aria-label="t('common.close')"></button>
            </div>
        </div>
    </div>
</template>
