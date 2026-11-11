// @vitest-environment jsdom
// useViewportFit : pages à saisie (assistant, messagerie) : `html.xm-dock` (bouton SOS dans sa bande réservée) tant que la
// page est montée, hauteur visible (--xm-vvh) et part du clavier qui recouvre la fenêtre (--xm-kbd) tirées de `visualViewport`,
// position réelle de la carte (--xm-chat-top), le tout remesuré au redimensionnement.
import { afterEach, describe, expect, it } from 'vitest';
import { createApp, defineComponent, h, ref } from 'vue';
import { isTextEntry, useViewportFit } from './useViewportFit';

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

afterEach(() => {
    document.body.replaceChildren();
    top = 180;
    Object.defineProperty(window, 'visualViewport', { value: undefined, configurable: true });
});

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

    it('pose html.xm-dock tant que la page est montée et le retire au démontage', () => {
        const { app } = mount();
        expect(document.documentElement.classList.contains('xm-dock')).toBe(true);
        app.unmount();
        expect(document.documentElement.classList.contains('xm-dock')).toBe(false);
        expect(document.documentElement.style.getPropertyValue('--xm-vvh')).toBe('');
    });

    it('borne la page à la hauteur visible et relève le bouton SOS de la part du clavier qui recouvre la fenêtre', () => {
        const listeners: Record<string, () => void> = {};
        const vv = { height: 400, offsetTop: 0, addEventListener: (type: string, fn: () => void) => { listeners[type] = fn; }, removeEventListener: () => undefined };
        Object.defineProperty(window, 'visualViewport', { value: vv, configurable: true });
        Object.defineProperty(window, 'innerHeight', { value: 800, configurable: true });
        const { app } = mount();
        expect(document.documentElement.style.getPropertyValue('--xm-vvh')).toBe('400px');
        expect(document.documentElement.style.getPropertyValue('--xm-kbd')).toBe('400px'); // clavier de 400 px sur la fenêtre de 800 px
        vv.height = 800; // clavier refermé
        listeners.resize();
        expect(document.documentElement.style.getPropertyValue('--xm-vvh')).toBe('800px');
        expect(document.documentElement.style.getPropertyValue('--xm-kbd')).toBe('0px');
        app.unmount();
    });

    describe('html.xm-kbd-open (clavier ouvert : place rendue à la saisie)', () => {
        const flag = () => document.documentElement.classList.contains('xm-kbd-open');
        const setViewport = (inner: number, height: number) => {
            const vv = { height, offsetTop: 0, addEventListener: () => undefined, removeEventListener: () => undefined };
            Object.defineProperty(window, 'visualViewport', { value: vv, configurable: true });
            Object.defineProperty(window, 'innerHeight', { value: inner, configurable: true });
            return vv;
        };
        function mountWithField(type = 'text') {
            const main = document.createElement('main');
            main.id = 'contenu';
            const field = document.createElement('input');
            field.type = type;
            const card = document.createElement('div');
            main.append(field, card);
            document.body.replaceChildren(main);
            const Host = defineComponent({ setup() { const el = ref<HTMLElement | null>(card); useViewportFit(el); return () => null; } });
            const app = createApp(Host);
            app.mount(document.createElement('div'));
            return { app, field };
        }

        it('reste fermé sans champ actif, même sur une fenêtre basse', () => {
            setViewport(400, 400);
            const { app } = mountWithField();
            expect(flag()).toBe(false);
            app.unmount();
        });

        it('s\'ouvre au focus d\'un champ de saisie quand la hauteur visible est ≤ 480 px, se ferme quand le champ perd le focus', async () => {
            setViewport(400, 400);
            const { app, field } = mountWithField();
            field.focus();
            expect(flag()).toBe(true);
            field.blur();
            await new Promise((r) => setTimeout(r, 5));
            expect(flag()).toBe(false);
            app.unmount();
        });

        it('s\'ouvre quand le clavier recouvre la fenêtre (--xm-kbd > 80 px, iOS), sans champ actif nécessaire', () => {
            setViewport(800, 450);
            const { app } = mountWithField();
            expect(flag()).toBe(true);
            app.unmount();
        });

        it('Android redimensionné : une perte de plus de 20 % de la hauteur vue avec un champ actif suffit, retour quand la hauteur revient', () => {
            const vv = setViewport(900, 900);
            const { app, field } = mountWithField();
            field.focus();
            expect(flag()).toBe(false); // champ actif mais hauteur intacte
            vv.height = 600; Object.defineProperty(window, 'innerHeight', { value: 600, configurable: true });
            window.dispatchEvent(new Event('resize'));
            expect(flag()).toBe(true);
            vv.height = 900; Object.defineProperty(window, 'innerHeight', { value: 900, configurable: true });
            window.dispatchEvent(new Event('resize'));
            expect(flag()).toBe(false);
            app.unmount();
        });

        it('ignore les champs qui n\'ouvrent pas le clavier (case à cocher) et retire la classe au démontage', () => {
            setViewport(400, 400);
            const { app, field } = mountWithField('checkbox');
            field.focus();
            expect(flag()).toBe(false);
            expect(isTextEntry(field)).toBe(false);
            document.documentElement.classList.add('xm-kbd-open');
            app.unmount();
            expect(flag()).toBe(false);
        });
    });
});
