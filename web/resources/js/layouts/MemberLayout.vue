<script setup lang="ts">
// Gabarit de l'espace membre et pair (P6-03, FRONT_END_LAYOUT_INSTRUCTION §2-3, prototype fil.php) : gabarit Inertia
// persistant, appliqué par app.ts à toute page qui ne déclare pas le sien (une page peut s'en passer avec
// `defineOptions({ layout: null })`). Lien d'évitement, en-tête <XmHeader>, statut flashé <XmFlashStatus> sous l'en-tête,
// <main id="contenu"> avec le bandeau « Confirme ton e-mail » puis la rangée `row g-4` : sidenav <XmSidenav> (col-lg-3)
// et les colonnes de la page (col-lg-9, ou col-md-8 col-lg-6 + <XmRightColumn>), bouton SOS flottant <XmSosFab>.
// Bandeau hors connexion (<XmOfflineBar>) et toasts (<XmToast>) : racine d'app.ts, communs à toutes les pages.
// Focus : le lien d'évitement et une visite Inertia vers une autre adresse mènent au début du contenu de la page, la
// première colonne après le sidenav (tabindex -1 le temps du focus ; repli : <main>) : clavier et lecteur d'écran ne
// repassent pas le menu (WCAG 2.4.1) ; jamais au chargement initial, ni au retour arrière, ni après un envoi qui reste
// sur place. La disposition visuelle ne change pas (la page reste une suite de colonnes de la rangée).
// P13-08 : bandeau d'annonce préalable d'une maintenance planifiée (prop partagée `maintenance_notice`, textes neutres
// composés par le serveur), refermable sans bloquer, fermé pour la session de l'onglet (même clé sessionStorage que les
// gabarits Blade, shared/dismissible.js).
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { pathOf } from '@/app/memberNav';
import type { MaintenanceNotice } from '@/app/types';
import XmAlert from '@/components/XmAlert.vue';
import XmFlashStatus from '@/components/XmFlashStatus.vue';
import XmHeader from '@/components/XmHeader.vue';
import XmSidenav from '@/components/XmSidenav.vue';
import XmSosFab from '@/components/XmSosFab.vue';
import XmVerifyBanner from '@/components/XmVerifyBanner.vue';

const { t } = useI18n();
const page = usePage();
const main = ref<HTMLElement | null>(null);
const row = ref<HTMLElement | null>(null);

/** Début du contenu de la page : première colonne de la rangée après #memberSidenav, sinon <main>. */
function contentStart(): HTMLElement | null {
    const children = [...(row.value?.children ?? [])] as HTMLElement[];
    const index = children.findIndex((el) => el.id === 'memberSidenav');
    return children[index + 1] ?? main.value;
}

/** Focus programmatique : tabindex -1 posé le temps du focus sur une colonne qui n'en a pas. */
function focusContent({ scroll = false } = {}) {
    const target = contentStart();
    if (!target) return;
    if (target !== main.value && !target.hasAttribute('tabindex')) {
        target.setAttribute('tabindex', '-1');
        target.addEventListener('blur', () => target.removeAttribute('tabindex'), { once: true });
    }
    target.focus({ preventScroll: true });
    if (scroll) target.scrollIntoView?.({ block: 'start' });
}

const skip = () => focusContent({ scroll: true });

const DISMISS_PREFIX = 'xm-dismissed:';
const readDismissed = (key: string): boolean => {
    try {
        return window.sessionStorage.getItem(DISMISS_PREFIX + key) === '1';
    } catch {
        return false;
    }
};
const dismissedNotices = ref<string[]>([]);
const notice = computed(() => {
    const value = (page.props as { maintenance_notice?: MaintenanceNotice | null }).maintenance_notice ?? null;
    return value && !dismissedNotices.value.includes(value.key) && !readDismissed(value.key) ? value : null;
});
function dismissNotice(key: string) {
    dismissedNotices.value = [...dismissedNotices.value, key];
    try {
        window.sessionStorage.setItem(DISMISS_PREFIX + key, '1');
    } catch {
        // Stockage refusé : le bandeau reste fermé pour cette page seulement.
    }
}

let visiting = false;
let lastPath = pathOf(page.url ?? '/');
const offs: Array<() => void> = [];

onMounted(() => {
    offs.push(router.on('start', () => { visiting = true; }));
    offs.push(router.on('navigate', (event) => {
        const path = pathOf(event.detail.page.url);
        const moved = visiting && path !== lastPath;
        visiting = false;
        lastPath = path;
        // La nouvelle page est rendue au tick suivant.
        if (moved) nextTick(() => focusContent());
    }));
});
onBeforeUnmount(() => offs.splice(0).forEach((off) => off()));
</script>

<template>
    <a class="xm-skip-link" href="#contenu" @click.prevent="skip">{{ t('menus.skip_to_content') }}</a>
    <XmHeader />
    <XmFlashStatus />
    <main id="contenu" ref="main" tabindex="-1" class="py-4">
        <div class="container">
            <XmAlert v-if="notice" :key="notice.key" type="warning" role="status" icon="megaphone" :title="notice.title" dismissible class="mb-4" data-xm-maintenance-notice @dismiss="notice && dismissNotice(notice.key)">
                <p class="mb-0">{{ notice.text }}</p>
                <p v-if="notice.message" class="mb-0 mt-1 small">{{ notice.message }}</p>
            </XmAlert>
            <XmVerifyBanner class="mb-4" />
            <div ref="row" class="row g-4">
                <XmSidenav />
                <slot />
            </div>
        </div>
    </main>
    <XmSosFab />
</template>
