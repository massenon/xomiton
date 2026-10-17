// Paquet de l'espace membre et pair éducateur·rice : Vue 3 + Inertia.js + Pinia + vue-i18n (D9).
import 'bootstrap';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { i18n } from './app/i18n';
import './shared/theme.js';

const appName = import.meta.env.VITE_APP_NAME || 'Xomiton';

createInertiaApp({
    title: (title) => (title ? `${title} – ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`Page Inertia introuvable : ${name}`);
        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(i18n)
            .mount(el);
    },
    progress: { color: 'var(--xm-primary)' },
});
