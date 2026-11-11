<script setup lang="ts">
// Bouton SOS : même HTML que <x-sos-button> et même interaction, celle de la fabrique xmSos de
// resources/js/public/sos-button.js (P5-05, Q21 b), réutilisée telle quelle : maintien 2 s (anneau, data-holding) →
// compte à rebours 5 s annulable → envoi ; double appui, Entrée ou activation sans pointeur puis confirmation ; retour
// par vibration et annonce aria-live="assertive". Position fixe z-index 1060 (au-dessus des modales, D8).
// Panneau : focus piégé (Tab circule dans le panneau), focus sur le titre à l'ouverture (jamais sur « Annuler »), Échap.
// Props : fixed (bouton flottant) ; id ; size (md 64 px | lg 96 px) ; managed (défaut : l'hôte envoie, évènement
// `action` avec { resolve } ; sans écouteur `action`, le panneau annonce l'échec et renvoie vers /sos, jamais « alerte
// envoyée » sans envoi, RG-01 ; false = démonstration du catalogue : `action` sans charge, « envoyée » affiché sans rien
// transmettre) ; le bouton flottant des espaces connectés (envoi API, zone du profil, suivi) sera XmSosFab (P6-03,
// équivalent de x-sos-fab) ; panel (float | inline) ; confirm (texte de la confirmation :
// ce que l'envoi transmet vraiment) ; hold / countdown (défaut : props partagées ui.sos, xomiton.alerts.sos_hold_ms et sos_countdown_seconds).
import { computed, getCurrentInstance, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { xmSos } from '../public/sos-button.js';
import { useSessionStore } from '@/stores/session';
import { route } from '@/app/routes';

const props = withDefaults(defineProps<{
    fixed?: boolean;
    id?: string;
    size?: 'md' | 'lg';
    managed?: boolean;
    panel?: 'float' | 'inline';
    confirm?: string | (() => string) | null;
    hold?: number | null;
    countdown?: number | null;
}>(), { fixed: true, id: 'xmSos', size: 'md', managed: true, panel: 'float', confirm: null, hold: null, countdown: null });

export interface SosResult {
    state: 'idle' | 'sent' | 'failed' | string;
    title?: string;
    text?: string;
    message?: string;
    call?: { href: string; label: string } | null;
    link?: { href: string; label: string } | null;
}

const emit = defineEmits<{ action: [detail?: { resolve: (result: SosResult) => void }] }>();

const { t } = useI18n();
const session = useSessionStore();
const root = ref<HTMLElement | null>(null);
const panelEl = ref<HTMLElement | null>(null);
const inline = computed(() => props.panel === 'inline');

const sos = reactive(xmSos({
    action: 'sos',
    hold: props.hold ?? session.sosHoldMs,
    countdown: props.countdown ?? session.sosCountdown,
    managed: props.managed,
    floating: props.fixed,
    confirm: () => (typeof props.confirm === 'function' ? props.confirm() : (props.confirm ?? '')),
    texts: {
        sent: t('components.sos.sent'),
        sentText: t('components.sos.sent_text'),
        // xmSos remplace « :s » par le nombre de secondes restantes (même texte que Blade).
        countdown: t('components.sos.countdown', { s: ':s' }),
        countdownTitle: t('components.sos.countdown_title'),
        confirmTitle: t('components.sos.confirm_title'),
        confirmText: t('components.sos.confirm_text'),
        sending: t('components.sos.sending'),
        cancelled: t('components.sos.cancelled'),
    },
})) as ReturnType<typeof xmSos> & Record<string, unknown>;

const instance = getCurrentInstance();
/** Un hôte écoute-t-il `action` ? Sinon l'alerte ne partirait nulle part. */
const hasHost = () => Boolean(instance?.vnode.props?.onAction);
const unhandled = (): SosResult => ({
    state: 'failed',
    title: t('alerts.sos.fab.failed_title'),
    text: t('alerts.sos.fab.failed'),
    link: { href: route('sos', undefined, '/sos'), label: t('alerts.sos.fab.open_page') },
});

Object.assign(sos, {
    $dispatch: (_name: string, detail?: { resolve: (result: SosResult) => void }) => {
        if (!detail) return emit('action');
        if (!hasHost()) return detail.resolve(unhandled());
        return emit('action', detail);
    },
    $nextTick: (fn: () => void) => nextTick(fn),
});

const result = computed(() => sos.result as SosResult | null);

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])';
function trap(event: KeyboardEvent) {
    if (event.key !== 'Tab' || !panelEl.value) return;
    const items = [...panelEl.value.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((el) => el.offsetParent !== null || el === document.activeElement);
    if (items.length === 0) {
        event.preventDefault();
        return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && (document.activeElement === first || !panelEl.value.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
const onEscape = (event: KeyboardEvent) => { if (event.key === 'Escape' && sos.open) sos.escape(); };

onMounted(() => {
    Object.assign(sos, { $root: root.value });
    window.addEventListener('keydown', onEscape);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onEscape);
    sos.destroy();
});
</script>

<template>
    <div ref="root" :class="['xm-sos-wrap no-print', { 'xm-sos-wrap--fixed': fixed, 'xm-sos-wrap--inline': inline, 'position-relative d-inline-block': !fixed && !inline }]">
        <button :id="id" type="button" :class="['xm-sos', { 'xm-sos--lg': size === 'lg' }]" :data-holding="sos.holding ? 'true' : 'false'"
                :aria-describedby="`${id}Help`" aria-haspopup="dialog" :aria-expanded="sos.open ? 'true' : 'false'"
                @pointerdown.prevent="sos.start()" @pointerup="sos.release()" @pointerleave="sos.cancelHold()" @pointercancel="sos.cancelHold()" @click="sos.activate($event)"
                @keydown.space.prevent="sos.startKey()" @keyup.space.prevent="sos.cancelHold()" @keydown.enter.prevent="sos.ask()" @contextmenu.prevent>{{ t('components.sos.label') }}</button>
        <span :id="`${id}Help`" class="visually-hidden">{{ t('components.sos.help') }}</span>

        <div v-show="sos.open" ref="panelEl" :class="['card shadow-lg xm-sos-panel', { 'xm-sos-panel--inline': inline }]" role="alertdialog" aria-modal="true" :aria-labelledby="`${id}Title`" :aria-describedby="`${id}Desc`" @keydown="trap">
            <div class="card-body">
                <h2 :id="`${id}Title`" class="h6 mb-1" tabindex="-1" data-sos-title>{{ sos.panelTitle() }}</h2>
                <p :id="`${id}Desc`" class="small text-secondary mb-2">{{ sos.panelText() }}</p>
                <p v-show="sos.counting" class="xm-sos-count fw-bold text-center text-danger my-2" aria-hidden="true">{{ sos.left }}</p>
                <p v-show="sos.sending" class="text-center my-2"><span class="spinner-border text-danger" aria-hidden="true"></span></p>
                <div class="d-flex flex-wrap gap-2">
                    <button v-show="sos.confirming || sos.counting" type="button" class="btn btn-dark btn-lg flex-fill" @click="sos.abort()">{{ t('components.sos.cancel') }}</button>
                    <button v-show="sos.confirming" type="button" class="btn btn-secondary btn-lg flex-fill" @click="sos.begin()"><i class="bi bi-broadcast me-1" aria-hidden="true"></i>{{ t('components.sos.send') }}</button>
                    <a v-show="sos.done && result?.call" class="btn btn-primary flex-fill" :href="result?.call?.href ?? undefined"><i class="bi bi-telephone-fill me-1" aria-hidden="true"></i><span>{{ result?.call?.label ?? '' }}</span></a>
                    <a v-show="sos.done && result?.link" class="btn btn-light flex-fill" :href="result?.link?.href ?? undefined">{{ result?.link?.label ?? '' }}</a>
                    <button v-show="sos.done && !managed" type="button" class="btn btn-primary flex-fill" @click="sos.reset()">{{ t('components.sos.safe') }}</button>
                    <button v-show="sos.done && managed" type="button" class="btn btn-light flex-fill" @click="sos.reset()">{{ t('common.close') }}</button>
                </div>
            </div>
        </div>
        <p class="visually-hidden" aria-live="assertive" aria-atomic="true">{{ sos.message }}</p>
    </div>
</template>
