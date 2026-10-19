<script setup lang="ts">
// Toasts de l'espace Vue : même HTML que <x-toast> et XM.toast de xomiton-ui.js (.toast-container haut-droite >
// .toast.text-bg-{type}, Bootstrap Toast 4,5 s, pause au survol et au focus gérée par Bootstrap, bouton Fermer).
// Chaque toast : role="status" + aria-live="polite", ou role="alert" + aria-live="assertive" (erreurs, SOS).
// La file vient du store `ui` (useToast()) ; un toast masqué en est retiré. Bootstrap est chargé à la demande (P4-12) :
// le premier toast attend son arrivée. S'il ne vient pas (hors ligne avant l'installation du service worker, morceau
// disparu après un déploiement), repli sans Bootstrap : le toast est affiché (.show), retiré au bout de son délai, et
// son bouton Fermer le retire de la file — erreurs réseau de useApi et avis de la boîte d'envoi restent visibles.
import { nextTick, onBeforeUnmount, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import type { Toast } from '../shared/bootstrap.js';
import { loadBootstrap } from '../shared/bootstrap-lazy.js';
import { useUiStore } from '@/stores/ui';

const ui = useUiStore();
const { t } = useI18n();
const started = new Map<number, InstanceType<typeof Toast>>();
// Libération différée : une transition Bootstrap en cours (fade 0,15 s) relit encore son élément.
const release = (instance: InstanceType<typeof Toast>) => setTimeout(() => instance.dispose(), 600);

/** Toasts en attente de Bootstrap (chargé à la demande, shared/bootstrap-lazy.js). */
const binding = new Set<number>();
/** Toasts affichés sans Bootstrap (chargement en échec) : minuterie de retrait. */
const bare = new Map<number, ReturnType<typeof setTimeout>>();

function showBare(el: HTMLElement, id: number, delay: number) {
    el.classList.add('show');
    bare.set(id, setTimeout(() => closeBare(id), delay));
}
/** Bouton Fermer d'un toast sans Bootstrap (avec Bootstrap, data-bs-dismiss s'en charge). */
function closeBare(id: number) {
    const timer = bare.get(id);
    if (timer === undefined) return;
    clearTimeout(timer);
    bare.delete(id);
    ui.dismissToast(id);
}

function bind(el: Element | null, id: number, delay: number) {
    if (!(el instanceof HTMLElement) || started.has(id) || binding.has(id)) return;
    binding.add(id);
    loadBootstrap().then(({ Toast: Component }) => {
        // Retiré de la file (ou démonté) pendant le chargement : rien à montrer.
        if (!binding.delete(id) || !el.isConnected) return;
        const instance = Component.getOrCreateInstance(el, { delay }) as InstanceType<typeof Toast>;
        started.set(id, instance);
        el.addEventListener('hidden.bs.toast', () => {
            started.delete(id);
            instance.dispose();
            ui.dismissToast(id);
        }, { once: true });
        nextTick(() => instance.show());
    }, () => {
        if (!binding.delete(id) || !el.isConnected) return;
        showBare(el, id, delay);
    });
}

watch(() => ui.toasts.map((toast) => toast.id), (ids) => {
    [...binding].filter((id) => !ids.includes(id)).forEach((id) => binding.delete(id));
    [...bare.keys()].filter((id) => !ids.includes(id)).forEach((id) => {
        clearTimeout(bare.get(id));
        bare.delete(id);
    });
    [...started.keys()].filter((id) => !ids.includes(id)).forEach((id) => {
        const instance = started.get(id);
        started.delete(id);
        if (instance) release(instance);
    });
});

onBeforeUnmount(() => {
    binding.clear();
    bare.forEach((timer) => clearTimeout(timer));
    bare.clear();
    started.forEach(release);
    started.clear();
});
</script>

<template>
    <div id="xmToastWrap" class="toast-container position-fixed top-0 end-0 p-3">
        <div v-for="toast in ui.toasts" :key="toast.id" :ref="(el) => bind(el as Element | null, toast.id, toast.delay)" :class="`toast align-items-center text-bg-${toast.type} border-0`" :role="toast.urgent ? 'alert' : 'status'" :aria-live="toast.urgent ? 'assertive' : 'polite'" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">{{ toast.message }}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" :aria-label="t('common.close')" @click="closeBare(toast.id)"></button>
            </div>
        </div>
    </div>
</template>
