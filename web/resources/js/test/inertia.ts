// Outils des tests Vitest du gabarit membre Vue (P6-03) : props partagées d'un·e membre type (contrat de
// HandleInertiaRequests) et montage d'un composant dans une vraie application Inertia (usePage, <Link>, router.on)
// avec Pinia neuf, stores hydratés comme par app.ts et vue-i18n chargé du catalogue français.
import { createApp, h, nextTick, type App, type Component } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { i18n } from '@/app/i18n';
import type { SharedProps } from '@/app/types';
import { useNotificationsStore } from '@/stores/notifications';
import { useSessionStore } from '@/stores/session';
import { freshPinia, useFrench } from './helpers';

type DeepPartial<T> = { [K in keyof T]?: NonNullable<T[K]> extends unknown[] ? T[K] : NonNullable<T[K]> extends object ? DeepPartial<NonNullable<T[K]>> | null : T[K] };

export function memberProps(overrides: DeepPartial<SharedProps> = {}): SharedProps {
    const base: SharedProps = {
        auth: {
            user: {
                id: 7,
                pseudo: 'Awa_S',
                avatar_url: null,
                role: 'membre',
                roles: ['membre'],
                email_verified: true,
                pending_validation: false,
                profile: { completion: 40, member_type: null, discreet_mode: false },
                settings: { theme: null, language: 'fr', neutral_tab_title: false, mask_sensitive_notifications: false, sos_share_gps: false },
            },
        },
        can: { 'peer_space.access': false, 'admin.access': false },
        counters: { notifications: 0, messages: 0 },
        shell: {
            zone: 'Littoral',
            stats: { posts: 24, followers: 182, following: 96 },
            sos: { endpoint: '/api/v1/sos', zone_id: 4, commune_id: 12, number: '117' },
            admin_url: null,
            csrf_token: 'jeton-csrf',
        },
        flash: { toast: null, status: null },
        lang: { locale: 'fr', fallback: 'fr' },
        ui: {
            neutral_title: false,
            neutral_label: 'Notes',
            bell_poll_seconds: 30,
            outbox: { account: 'compte', ttl_days: 7 },
            sos: { hold_ms: 2000, countdown_seconds: 5 },
        },
    };
    const merge = (target: Record<string, unknown>, source: Record<string, unknown>): Record<string, unknown> => {
        for (const [key, value] of Object.entries(source)) {
            const current = target[key];
            target[key] = value && typeof value === 'object' && !Array.isArray(value) && current && typeof current === 'object'
                ? merge({ ...(current as Record<string, unknown>) }, value as Record<string, unknown>)
                : value;
        }
        return target;
    };
    return merge(base as unknown as Record<string, unknown>, overrides as Record<string, unknown>) as unknown as SharedProps;
}

const mounted: App[] = [];

/** Démonte les applications montées par mountInertia (minuteries et écouteurs des composants arrêtés entre deux tests). */
export function unmountInertia(): void {
    mounted.splice(0).forEach((app) => app.unmount());
}

/** Monte `component` comme page Inertia à l'adresse `url` ; `pageProps` = props propres à la page. */
export async function mountInertia(component: Component, { url = '/fil', props = memberProps(), pageProps = {} as Record<string, unknown> } = {}) {
    const pinia = freshPinia();
    useFrench();
    const page = { component: 'Test', props: { errors: {}, ...props, ...pageProps }, url, version: null, encryptHistory: false, clearHistory: false };
    const el = document.createElement('div');
    el.id = 'app';
    el.dataset.page = JSON.stringify(page);
    document.body.replaceChildren(el);
    await createInertiaApp({
        resolve: () => component as never,
        setup({ el: target, App, props: appProps, plugin }) {
            const initial = (appProps.initialPage as unknown as { props: SharedProps }).props;
            useSessionStore(pinia).hydrate(initial);
            useNotificationsStore(pinia).hydrate(initial);
            const app = createApp({ render: () => h(App, appProps) }).use(plugin).use(pinia).use(i18n);
            mounted.push(app);
            app.mount(target);
        },
    });
    await nextTick();
    return { pinia };
}

/** Évènement du routeur Inertia (router.on écoute `inertia:<type>` sur document). */
export function inertiaEvent(type: string, detail: Record<string, unknown> = {}): void {
    document.dispatchEvent(new CustomEvent(`inertia:${type}`, { detail, cancelable: true }));
}
