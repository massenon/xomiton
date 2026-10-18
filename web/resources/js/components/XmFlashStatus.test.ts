// @vitest-environment jsdom
// XmFlashStatus (P6-02) : le statut flashé (session('status')) reste à l'écran en XmAlert, en plus du toast ; fermable.
import { afterEach, describe, expect, it } from 'vitest';
import { createApp, defineComponent, h, nextTick } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { fireEvent } from '@testing-library/vue';
import { i18n } from '@/app/i18n';
import { freshPinia, useFrench } from '@/test/helpers';
import XmFlashStatus from './XmFlashStatus.vue';

async function mount(status: unknown) {
    const pinia = freshPinia();
    useFrench();
    const page = { component: 'Demo', props: { errors: {}, flash: { toast: null, status } }, url: '/demo', version: null, encryptHistory: false, clearHistory: false };
    const el = document.createElement('div');
    el.id = 'app';
    el.dataset.page = JSON.stringify(page);
    document.body.replaceChildren(el);
    await createInertiaApp({
        resolve: () => defineComponent({ render: () => h('main', 'Page') }),
        setup({ el: target, App, props, plugin }) {
            createApp({ render: () => [h(XmFlashStatus), h(App, props)] }).use(plugin).use(pinia).use(i18n).mount(target);
        },
    });
    await nextTick();
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('XmFlashStatus', () => {
    it('affiche le statut en alerte (type filtré), fermable', async () => {
        await mount({ id: 's1', message: 'Ton profil est enregistré.', type: 'success' });
        const alert = document.querySelector('[data-xm-flash-status] .alert') as HTMLElement;
        expect(alert.className).toContain('alert-success');
        expect(alert.getAttribute('role')).toBe('status');
        expect(alert.textContent).toContain('Ton profil est enregistré.');
        await fireEvent.click(alert.querySelector('.btn-close') as HTMLElement);
        expect(document.querySelector('[data-xm-flash-status]')).toBeNull();
    });

    it('rien sans statut ; type inconnu affiché en info', async () => {
        await mount(null);
        expect(document.querySelector('[data-xm-flash-status]')).toBeNull();
        await mount({ id: 's2', message: 'Note.', type: 'secondary' });
        expect(document.querySelector('[data-xm-flash-status] .alert')?.className).toContain('alert-info');
    });
});
