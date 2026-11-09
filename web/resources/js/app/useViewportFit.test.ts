// @vitest-environment jsdom
// useViewportFit : la carte plein écran (assistant, messagerie) reçoit sa position réelle dans la page (--xm-chat-top),
// remesurée au redimensionnement de la fenêtre, pour s'arrêter au-dessus de la zone du bouton SOS flottant.
import { afterEach, describe, expect, it } from 'vitest';
import { createApp, defineComponent, h, ref } from 'vue';
import { useViewportFit } from './useViewportFit';

let top = 180;
const Demo = defineComponent({
    setup() {
        const card = ref<HTMLElement | null>(null);
        useViewportFit(card);
        return () => h('div', { ref: card, class: 'card' });
    },
});

function mount() {
    const root = document.createElement('div');
    document.body.replaceChildren(root);
    const app = createApp(Demo);
    app.mount(root);
    const card = root.querySelector('.card') as HTMLElement;
    card.getBoundingClientRect = () => ({ top, bottom: top + 500, left: 0, right: 0, width: 0, height: 500, x: 0, y: top, toJSON: () => ({}) });
    return { app, card };
}

afterEach(() => { document.body.replaceChildren(); top = 180; });

describe('useViewportFit', () => {
    it('pose --xm-chat-top à la position de la carte dans la page, remesurée au redimensionnement', () => {
        const { app, card } = mount();
        window.dispatchEvent(new Event('resize'));
        expect(card.style.getPropertyValue('--xm-chat-top')).toBe('180px');
        top = 240; // bandeau apparu au-dessus
        window.dispatchEvent(new Event('resize'));
        expect(card.style.getPropertyValue('--xm-chat-top')).toBe('240px');
        app.unmount();
        top = 10;
        window.dispatchEvent(new Event('resize'));
        expect(card.style.getPropertyValue('--xm-chat-top')).toBe('240px'); // plus d'écoute après le démontage
    });
});
