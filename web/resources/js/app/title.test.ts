// @vitest-environment jsdom
// Titre d'onglet de l'espace Vue (P6-02) : app.blade.php écrit `<title inertia>` pour que le gestionnaire de tête
// d'Inertia le remplace à chaque visite (titre de la page + « – Xomiton », ou « Notes » en mode neutre). Sans
// l'attribut, Inertia ajoute un second <title> et le navigateur garde le premier : onglet figé.
import { afterEach, describe, expect, it } from 'vitest';
import { createApp, defineComponent, h, type DefineComponent } from 'vue';
import { createInertiaApp, Head } from '@inertiajs/vue3';
import { freshPinia } from '@/test/helpers';
import { useSessionStore } from '@/stores/session';

const wait = (ms = 30) => new Promise((resolve) => setTimeout(resolve, ms));

async function mount(rootTitle: string, pageTitle: string) {
    freshPinia();
    document.head.innerHTML = rootTitle;
    const page = { component: 'Demo', props: { errors: {} }, url: '/demo', version: null, encryptHistory: false, clearHistory: false };
    const el = document.createElement('div');
    el.id = 'app';
    el.dataset.page = JSON.stringify(page);
    document.body.replaceChildren(el);
    const Demo = defineComponent({ render: () => h('div', [h(Head, { title: pageTitle }), 'Contenu']) });
    await createInertiaApp({
        title: (title) => useSessionStore().documentTitle(title, 'Xomiton'),
        resolve: () => Demo as unknown as DefineComponent,
        setup({ el: target, App, props, plugin }) {
            createApp({ render: () => h(App, props) }).use(plugin).mount(target);
        },
    });
    await wait();
}

afterEach(() => {
    document.head.innerHTML = '';
    document.body.innerHTML = '';
});

describe('titre d’onglet', () => {
    it('le titre de la page Vue remplace le <title inertia> de la racine, sans second <title>', async () => {
        await mount('<title inertia>Mon espace – Xomiton</title>', 'Fil');
        expect(document.title).toBe('Fil – Xomiton');
        expect(document.head.querySelectorAll('title')).toHaveLength(1);
    });

    it('sans l’attribut inertia, le premier <title> l’emporterait (régression de la racine)', async () => {
        await mount('<title>Mon espace – Xomiton</title>', 'Fil');
        expect(document.head.querySelectorAll('title')).toHaveLength(2);
        expect(document.title).toBe('Mon espace – Xomiton');
    });
});
