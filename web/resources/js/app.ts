// Paquet de l'espace membre et pair éducateur·rice (D9, ADR-18, ADR-30) : Vue 3 + Inertia 2 (sans SSR) + Pinia + vue-i18n.
// Budget : app.ts + imports statiques < 150 Ko gzip (scripts/check-budgets.mjs) — chaque page Vue, le catalogue de
// messages (resources/js/lang/<locale>.json), la boîte d'envoi (shared/outbox.js) et Chart.js sont des import() à la
// demande. Bootstrap : import sélectif partagé avec public.js (shared/bootstrap.js), jamais le paquet complet, chargé
// après le montage ou au premier geste sur un déclencheur data-bs-* (app/bootstrapOnDemand.ts, P4-12).
// Au démarrage : stores hydratés depuis les props partagées (HandleInertiaRequests), catalogue de la locale chargé avant
// le montage (le squelette x-skeleton de app.blade.php reste affiché jusque-là), titre d'onglet neutre si demandé,
// toast flashé affiché une seule fois (app/flash.ts, jamais au retour dans l'historique) et statut gardé à l'écran
// (XmFlashStatus, sous l'en-tête du gabarit), `window.XM.toast` et l'évènement `toast` (contrat de xomiton-ui.js)
// relayés vers useToast(), réseau suivi pour le bandeau hors connexion, boîte d'envoi et brouillons configurés pour le
// compte de la page, `window.XM.outbox` (contrat de shared/outbox-boot.js : remise d'un SOS hors ligne par sos.js)
// adossé au store `outbox`. Gabarit membre persistant (layouts/MemberLayout.vue, P6-03) par défaut pour chaque page.
// Ces deux câblages sont dans app/shellBoot.ts (testés en Vitest).
import './shared/theme.js';
import { createApp, h, Fragment, type DefineComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createPinia, setActivePinia } from 'pinia';
import { i18n, loadLocale, t } from './app/i18n';
import { route } from './app/routes';
import { useToast } from './app/useToast';
import { createFlashPresenter } from './app/flash';
import { bindOutbox, withDefaultLayout } from './app/shellBoot';
import { bootstrapOnDemand } from './app/bootstrapOnDemand';
import type { SharedProps } from './app/types';
import { useSessionStore } from './stores/session';
import { useNotificationsStore } from './stores/notifications';
import { useOutboxStore } from './stores/outbox';
import { useDraftsStore } from './stores/drafts';
import { useUiStore } from './stores/ui';
import XmToast from './components/XmToast.vue';
import XmOfflineBar from './components/XmOfflineBar.vue';
import MemberLayout from './layouts/MemberLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'Xomiton';
const pinia = createPinia();
setActivePinia(pinia);

const pages = import.meta.glob<{ default: DefineComponent }>('./Pages/**/*.vue');

/** Textes neutres des avis de la boîte d'envoi : mêmes clés que partials/offline.blade.php (lang/fr/offline.php). */
const OUTBOX_TEXTS = ['queued', 'report_sent_title', 'report_sent', 'report_keep', 'report_failed_title', 'report_failed', 'report_resume',
    'sos_sent', 'sos_failed_title', 'sos_failed', 'sos_contacts', 'post_sent', 'post_failed_title', 'post_failed', 'post_resume',
    'copy', 'copied', 'print', 'close'];

const outboxTexts = (): Record<string, string> => ({
    ...Object.fromEntries(OUTBOX_TEXTS.map((key) => [key, t(`offline.outbox.${key}`)])),
    report_url: route('signaler', undefined, '/signaler'),
    urgences_url: route('urgences', undefined, '/urgences'),
    feed_url: route('fil', undefined, '/fil'),
});

const hydrate = (props: Partial<SharedProps>) => {
    useSessionStore().hydrate(props);
    useNotificationsStore().hydrate(props);
};

const showFlash = createFlashPresenter((message, type) => useToast().show(message, type));

createInertiaApp({
    title: (title) => useSessionStore().documentTitle(title, appName),
    resolve: async (name) => {
        const loader = pages[`./Pages/${name}.vue`];
        if (!loader) throw new Error(`Page Inertia introuvable : ${name}`);
        // Gabarit membre par défaut ; `layout: null` (defineOptions) le retire, un autre gabarit le remplace.
        return withDefaultLayout((await loader()).default, MemberLayout);
    },
    async setup({ el, App, props, plugin }) {
        const initial = props.initialPage as unknown as { props: SharedProps };
        hydrate(initial.props);

        const session = useSessionStore();
        const ui = useUiStore();
        const toast = useToast();

        window.XM = window.XM || {};
        window.XM.toast = (message: string, type = 'primary') => toast.show(message, type as never);
        window.addEventListener('toast', (event) => {
            const detail = (event as CustomEvent<{ message?: string; type?: string }>).detail || {};
            if (detail.message) toast.show(detail.message, (detail.type || 'primary') as never);
        });
        window.addEventListener('online', () => ui.setOnline(true));
        window.addEventListener('offline', () => ui.setOnline(false));

        router.on('navigate', (event) => {
            hydrate(event.detail.page.props);
            showFlash(event.detail.page.props as Partial<SharedProps>);
        });
        // Rechargements partiels (compteurs de la cloche, P6-03) : pas d'évènement navigate.
        router.on('success', (event) => hydrate(event.detail.page.props));

        await loadLocale(initial.props.lang?.locale || 'fr', initial.props.lang?.fallback || 'fr');
        document.documentElement.lang = i18n.global.locale.value;

        const drafts = useDraftsStore();
        drafts.configure(session.outboxAccount, session.ttlDays);
        drafts.purge().catch(() => undefined);
        const outbox = useOutboxStore();
        outbox.start({ account: session.outboxAccount, ttlDays: session.ttlDays, texts: outboxTexts() }).catch(() => undefined);
        bindOutbox(window, outbox);

        createApp({ render: () => h(Fragment, [h(XmOfflineBar), h(App, props), h(XmToast)]) })
            .use(plugin)
            .use(pinia)
            .use(i18n)
            .mount(el);
        bootstrapOnDemand();
    },
    progress: { color: 'var(--xm-primary)', showSpinner: false },
});
