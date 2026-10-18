<script setup lang="ts">
// Bouton SOS flottant de l'espace membre Vue (P6-03) : équivalent de <x-sos-fab> (P5-08). <XmSosButton> fixe managé
// (.xm-sos-fab, 64 px, z-index 1060 au-dessus des modales et des toasts, D8) ; au terme du maintien 2 s + décompte 5 s,
// la fabrique xmSosFab de resources/js/public/sos.js (la même que le gabarit Blade, réutilisée telle quelle) envoie
// aussitôt POST /api/v1/sos (Idempotency-Key, jamais de doublon), **sans GPS** (géolocalisation réservée à /sos et
// /signaler) avec le département et la commune du profil (props partagées `shell.sos`), puis ouvre le suivi
// /sos?alerte={id}. Hors ligne : panneau « Tu es hors ligne », appel du premier numéro d'urgence, nouvel essai au retour
// du réseau et remise à la boîte d'envoi commune (window.XM.outbox, branchée par app.ts sur le store `outbox`).
// Monté une fois par le gabarit persistant : un envoi en cours survit aux visites Inertia. Avant le montage de Vue,
// app.blade.php affiche un lien vers /sos à la même place.
// Budget app.ts (< 150 Ko gzip) : sos.js est un module à la demande, mais demandé dès le montage (jamais à l'appui :
// maintien + décompte laissent 7 s au moins) et précaché par le service worker avec la coquille ; s'il ne peut pas se
// charger, l'appui aboutit à « Ton alerte n'est pas partie » avec l'appel du premier numéro et la page /sos (RG-01 :
// jamais « envoyée » sans envoi), et le chargement est retenté à l'appui suivant.
import { onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import XmSosButton, { type SosResult } from './XmSosButton.vue';
import { route } from '@/app/routes';
import { useSessionStore } from '@/stores/session';

interface Fab {
    onSend: (detail: { resolve: (result: SosResult) => void }) => unknown;
    destroy: () => void;
}

const { t } = useI18n();
const session = useSessionStore();
const sos = () => session.shell?.sos ?? null;
const number = sos()?.number ?? null;
const pageUrl = route('sos', undefined, '/sos');

const options = {
    endpoint: sos()?.endpoint || '/api/v1/sos',
    csrfUrl: '/sanctum/csrf-cookie',
    followUrl: route('sos', { alerte: '__ID__' }, '/sos?alerte=__ID__'),
    pageUrl,
    // Lus à chaque envoi : un changement de département dans le profil vaut dès la visite suivante.
    get zoneId() { return sos()?.zone_id ?? null; },
    get communeId() { return sos()?.commune_id ?? null; },
    number,
    texts: {
        sent: t('components.sos.sent'),
        sentText: t('alerts.sos.fab.sent_text'),
        detached: t('alerts.sos.fab.detached'),
        offlineTitle: t('alerts.sos.fab.offline_title'),
        offline: t('alerts.sos.fab.offline'),
        failedTitle: t('alerts.sos.fab.failed_title'),
        failed: t('alerts.sos.fab.failed'),
        // sos.js remplace « :number » par le numéro (même texte que Blade).
        call: t('alerts.sos.fab.call', { number: ':number' }),
        openPage: t('alerts.sos.fab.open_page'),
    },
};

let fab: Fab | null = null;
let loading: Promise<Fab | null> | null = null;
let destroyed = false;

function load(): Promise<Fab | null> {
    loading ??= import('../public/sos.js')
        .then(({ xmSosFab }) => {
            if (!destroyed) fab ??= xmSosFab(options) as Fab;
            return fab;
        })
        .catch(() => {
            loading = null;
            return null;
        });
    return loading;
}
load();

const unavailable = (): SosResult => ({
    state: 'failed',
    title: t('alerts.sos.fab.failed_title'),
    text: t('alerts.sos.fab.failed'),
    call: number ? { href: `tel:${number.replace(/[^\d+]/g, '')}`, label: t('alerts.sos.fab.call', { number }) } : null,
    link: { href: pageUrl, label: t('alerts.sos.fab.open_page') },
});

async function onAction(detail?: { resolve: (result: SosResult) => void }) {
    if (!detail) return;
    const ready = fab ?? await load();
    if (ready) ready.onSend(detail);
    else detail.resolve(unavailable());
}

onBeforeUnmount(() => {
    destroyed = true;
    fab?.destroy();
});
</script>

<template>
    <div class="no-print" data-outbox="sos" data-xm-sos-fab>
        <XmSosButton id="xmSosFab" class="xm-sos-fab" :confirm="t('alerts.sos.fab.confirm')" @action="onAction" />
    </div>
</template>
