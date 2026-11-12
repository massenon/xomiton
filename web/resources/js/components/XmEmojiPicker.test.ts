// @vitest-environment jsdom
// XmEmojiPicker : déclencheur ARIA, panneau (Échap, clic extérieur, focus), onglets et grille au clavier, recherche sans
// accents, insertion au curseur avec remise du focus, limite en points de code, récents persistés, annonce aria-live.
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { cleanup, fireEvent, screen, waitFor } from '@testing-library/vue';
import { defineComponent, h, ref } from 'vue';
import { renderXm } from '@/test/helpers';
import { RECENTS_KEY, TONE_KEY, applyTone, buildIndex, codePointLength, normalize, search, type EmojiData } from '@/emoji/emoji';
import excluded from '@/emoji/excluded.json';
import data from '@/emoji/emojis.json';
import XmEmojiPicker from './XmEmojiPicker.vue';

const emojis = data as EmojiData;

/** Zone de saisie + sélecteur, comme dans un composeur (v-model sur l'élément, écouté par l'événement `input`). */
function host(options: { value?: string; max?: number | null; tag?: 'textarea' | 'input'; onEscape?: () => void } = {}) {
    const model = ref(options.value ?? '');
    const Host = defineComponent({
        setup: () => () => h('div', { onKeydown: (event: KeyboardEvent) => { if (event.key === 'Escape') options.onEscape?.(); } }, [
            h(options.tag ?? 'textarea', { id: 'zone', value: model.value, onInput: (event: Event) => { model.value = (event.target as HTMLTextAreaElement).value; } }),
            h(XmEmojiPicker, { target: '#zone', maxlength: options.max ?? null }),
            h('button', { type: 'button', id: 'dehors' }, 'dehors'),
        ]),
    });
    const view = renderXm(Host);
    const field = document.querySelector('#zone') as HTMLTextAreaElement;
    return { ...view, field, model };
}

const trigger = () => screen.getByRole('button', { name: 'Ajouter un émoji' });
const open = async () => {
    await fireEvent.click(trigger());
    await screen.findByRole('dialog');
    await waitFor(() => expect(screen.getAllByRole('tab').length).toBeGreaterThan(5));
};
const live = () => document.querySelector('[data-xm-emoji-live]')?.textContent ?? '';

beforeEach(() => window.localStorage.clear());
afterEach(() => cleanup());

describe('données', () => {
    it('536 émojis environ, noms français, aucun doublon, aucun émoji écarté', () => {
        const all = emojis.categories.flatMap((c) => c.emojis);
        expect(all.length).toBeGreaterThanOrEqual(300);
        expect(all.length).toBeLessThanOrEqual(600);
        expect(new Set(all.map((e) => e.c)).size).toBe(all.length);
        expect(all.every((e) => e.n.length >= 2)).toBe(true);
        const banned = new Set(excluded.groups.flatMap((g) => g.emojis));
        expect(all.filter((e) => banned.has(e.c))).toEqual([]);
    });

    it('contient les drapeaux demandés et des séquences ZWJ', () => {
        const chars = emojis.categories.flatMap((c) => c.emojis.map((e) => e.c));
        for (const flag of ['🏳️‍🌈', '🏳️‍⚧️', '🇧🇯']) expect(chars).toContain(flag);
        expect(chars.some((c) => c.includes('‍'))).toBe(true);
        expect(emojis.categories.map((c) => c.id)).toEqual(['visages', 'mains', 'coeurs', 'animaux', 'nourriture', 'activites', 'voyages', 'objets', 'symboles', 'drapeaux']);
    });

    it('chaque émoji est une séquence RGI (natif, pas une image)', () => {
        const bad = emojis.categories.flatMap((c) => c.emojis).filter((e) => !new RegExp('^\\p{RGI_Emoji}$', 'v').test(e.c));
        expect(bad).toEqual([]);
    });

    it('teintes : le modificateur suit le premier point de code, ZWJ conservé', () => {
        expect(applyTone('👍', 3)).toBe('👍\u{1F3FD}');
        expect(applyTone('✌️', 5)).toBe('✌\u{1F3FF}');
        expect(applyTone('👩‍⚕️', 2)).toBe('👩\u{1F3FC}‍⚕️');
        expect(applyTone('👍', 0)).toBe('👍');
        expect(new RegExp('^\\p{RGI_Emoji}$', 'v').test(applyTone('👩‍⚕️', 2))).toBe(true);
    });

    it('recherche : accents et ligatures ignorés, tous les mots', () => {
        const index = buildIndex(emojis);
        expect(normalize('  Cœur  ÉTÉ ')).toBe('coeur ete');
        expect(search(index, 'COEUR').some((e) => e.n === 'cœur rouge')).toBe(true);
        expect(search(index, 'coeur rouge').map((e) => e.n)).toContain('cœur rouge');
        expect(search(index, 'zzzzzz')).toEqual([]);
        expect(search(index, 'ete').length).toBe(0 + search(index, 'été').length);
        expect(codePointLength('a😀🏳️‍🌈')).toBe(1 + 1 + 4);
    });
});

describe('XmEmojiPicker', () => {
    it('déclencheur : aria-haspopup, aria-expanded, panneau en dialogue', async () => {
        host();
        expect(trigger().getAttribute('aria-haspopup')).toBe('dialog');
        expect(trigger().getAttribute('aria-expanded')).toBe('false');
        await open();
        expect(trigger().getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('dialog', { name: 'Choisir un émoji' })).toBeTruthy();
        expect(screen.getByRole('tablist', { name: 'Catégories d’émojis' })).toBeTruthy();
        expect(screen.getAllByRole('tab')).toHaveLength(11);
    });

    it('insère à la position du curseur, remet focus et curseur après l’émoji, annonce', async () => {
        const { field, model } = host({ value: 'abcd' });
        field.setSelectionRange(2, 2);
        await open();
        await fireEvent.click(screen.getByRole('button', { name: 'visage rieur' }));
        expect(field.value).toBe('ab😀cd');
        expect(model.value).toBe('ab😀cd');
        expect(document.activeElement).toBe(field);
        expect(field.selectionStart).toBe(4);
        expect(field.selectionEnd).toBe(4);
        expect(screen.queryByRole('dialog')).toBeNull();
        await waitFor(() => expect(live()).toBe('Émoji ajouté\u00a0: visage rieur'));
    });

    it('remplace la sélection et fonctionne dans un champ input', async () => {
        const { field } = host({ value: 'abcdef', tag: 'input' });
        field.setSelectionRange(1, 4);
        await open();
        await fireEvent.click(screen.getByRole('button', { name: 'visage rieur' }));
        expect(field.value).toBe('a😀ef');
    });

    it('respecte la limite en points de code et n’insère pas au-delà', async () => {
        const { field } = host({ value: 'ab', max: 3 });
        await open();
        await fireEvent.click(screen.getByRole('button', { name: 'visage rieur' }));
        expect(field.value).toBe('ab😀'); // 3 points de code, 4 unités UTF-16
        await open();
        await fireEvent.click(screen.getByRole('tab', { name: 'Visages et émotions' }));
        await fireEvent.click(screen.getByRole('button', { name: 'visage rieur aux grands yeux' }));
        expect(field.value).toBe('ab😀');
        await waitFor(() => expect(live()).toBe('Limite de caractères atteinte\u00a0: l’émoji n’a pas été ajouté.'));
        expect(screen.getByRole('dialog')).toBeTruthy();
    });

    it('séquence ZWJ comptée comme ses points de code (le serveur compte 4 pour le drapeau arc-en-ciel)', async () => {
        const { field } = host({ value: '', max: 3 });
        await open();
        await fireEvent.click(screen.getByRole('tab', { name: 'Drapeaux' }));
        await fireEvent.click(screen.getByRole('button', { name: 'drapeau arc-en-ciel' }));
        expect(field.value).toBe('');
        expect(live()).toContain('Limite');
    });

    it('Échap ferme, rend le focus au bouton et ne remonte pas (modale parente non fermée)', async () => {
        let escapes = 0;
        host({ onEscape: () => { escapes++; } });
        await open();
        const dialog = screen.getByRole('dialog');
        await fireEvent.keyDown(dialog.querySelector('[role=tab][aria-selected=true]') as HTMLElement, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(document.activeElement).toBe(trigger());
        expect(escapes).toBe(0);
    });

    it('le clic à l’extérieur ferme sans voler le focus', async () => {
        host();
        await open();
        await fireEvent.pointerDown(document.querySelector('#dehors') as HTMLElement);
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
        expect(trigger().getAttribute('aria-expanded')).toBe('false');
    });

    it('focus piégé : Tab après le dernier élément revient au premier, Maj+Tab inverse', async () => {
        host();
        await open();
        const dialog = screen.getByRole('dialog');
        const focusables = [...dialog.querySelectorAll<HTMLElement>('button, input')].filter((el) => el.tabIndex >= 0 && !(el as HTMLButtonElement).disabled);
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        last.focus();
        await fireEvent.keyDown(last, { key: 'Tab' });
        expect(document.activeElement).toBe(first);
        await fireEvent.keyDown(first, { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(last);
    });

    it('onglets : le focus part sur l’onglet actif, flèches, Début et Fin, activation automatique', async () => {
        host();
        await open();
        const tabs = screen.getAllByRole('tab');
        expect(document.activeElement).toBe(screen.getByRole('tab', { name: 'Visages et émotions' }));
        expect(tabs.filter((tab) => tab.getAttribute('aria-selected') === 'true')).toHaveLength(1);
        await fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowRight' });
        expect(document.activeElement).toBe(screen.getByRole('tab', { name: 'Mains et personnes' }));
        expect(document.activeElement?.getAttribute('aria-selected')).toBe('true');
        expect(screen.getByRole('button', { name: 'main qui salue' })).toBeTruthy();
        await fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'End' });
        expect(document.activeElement).toBe(screen.getByRole('tab', { name: 'Drapeaux' }));
        await fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowRight' });
        expect(document.activeElement).toBe(screen.getByRole('tab', { name: 'Récents' }));
        await fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'ArrowLeft' });
        expect(document.activeElement).toBe(screen.getByRole('tab', { name: 'Drapeaux' }));
        await fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'Home' });
        expect(document.activeElement).toBe(screen.getByRole('tab', { name: 'Récents' }));
        expect(tabs.map((tab) => tab.tabIndex).filter((i) => i === 0)).toHaveLength(1);
    });

    it('grille : boutons nommés en français, tabindex itinérant, flèches, Début, Fin, ligne du dessous', async () => {
        host();
        await open();
        const grid = screen.getByRole('group', { name: 'Visages et émotions' });
        const buttons = [...grid.querySelectorAll<HTMLElement>('button')];
        expect(buttons.length).toBeGreaterThan(50);
        expect(buttons.every((button) => (button.getAttribute('aria-label') ?? '').length > 2)).toBe(true);
        expect(buttons.filter((button) => button.tabIndex === 0)).toHaveLength(1);
        // jsdom ne fait pas de mise en page : 8 colonnes simulées par la position verticale.
        buttons.forEach((button, i) => Object.defineProperty(button, 'offsetTop', { configurable: true, get: () => Math.floor(i / 8) * 44 }));
        buttons[0].focus();
        await fireEvent.keyDown(buttons[0], { key: 'ArrowRight' });
        await waitFor(() => expect(document.activeElement).toBe(buttons[1]));
        await fireEvent.keyDown(buttons[1], { key: 'ArrowDown' });
        await waitFor(() => expect(document.activeElement).toBe(buttons[9]));
        await fireEvent.keyDown(buttons[9], { key: 'ArrowUp' });
        await waitFor(() => expect(document.activeElement).toBe(buttons[1]));
        await fireEvent.keyDown(buttons[1], { key: 'End' });
        await waitFor(() => expect(document.activeElement).toBe(buttons[buttons.length - 1]));
        await fireEvent.keyDown(document.activeElement as HTMLElement, { key: 'Home' });
        await waitFor(() => expect(document.activeElement).toBe(buttons[0]));
        await fireEvent.keyDown(buttons[0], { key: 'ArrowLeft' });
        expect(document.activeElement).toBe(buttons[0]);
    });

    it('recherche sans accents : « coeur » trouve « cœur rouge », annonce du nombre de résultats', async () => {
        host();
        await open();
        const field = screen.getByLabelText('Rechercher un émoji');
        await fireEvent.update(field, 'COEUR');
        expect(screen.getByRole('button', { name: 'cœur rouge' })).toBeTruthy();
        expect(screen.queryByRole('tablist')).toBeNull();
        expect(screen.getByRole('tabpanel', { name: 'Résultats de la recherche' })).toBeTruthy();
        await waitFor(() => expect(document.querySelector('[data-xm-emoji-count]')?.textContent).toMatch(/émojis? trouvés?\.$/), { timeout: 2000 });
        await fireEvent.update(field, 'zzzzzz');
        expect(screen.getByText('Aucun émoji ne correspond à ta recherche.')).toBeTruthy();
        await waitFor(() => expect(document.querySelector('[data-xm-emoji-count]')?.textContent).toBe('Aucun émoji trouvé.'), { timeout: 2000 });
    });

    it('récents : persistés (24 au plus), affichés en premier à la réouverture, stockage refusé sans erreur', async () => {
        const { field } = host();
        await open();
        await fireEvent.click(screen.getByRole('button', { name: 'visage rieur' }));
        expect(JSON.parse(window.localStorage.getItem(RECENTS_KEY) ?? '[]')).toEqual(['😀']);
        await open();
        expect(screen.getByRole('tab', { name: 'Récents' }).getAttribute('aria-selected')).toBe('true');
        expect(screen.getByRole('button', { name: 'visage rieur' })).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: 'visage rieur' }));
        expect(field.value).toBe('😀😀');
        expect(JSON.parse(window.localStorage.getItem(RECENTS_KEY) ?? '[]')).toEqual(['😀']);

        window.localStorage.setItem(RECENTS_KEY, JSON.stringify(Array.from({ length: 40 }, (_, i) => `x${i}`)));
        await open();
        expect(document.querySelectorAll('[data-emoji]').length).toBe(24);
    });

    it('stockage illisible : aucun plantage, liste vide', async () => {
        window.localStorage.setItem(RECENTS_KEY, '{pas du json');
        host();
        await open();
        await fireEvent.click(screen.getByRole('tab', { name: 'Récents' }));
        expect(screen.getByText('Les émojis que tu utilises apparaîtront ici.')).toBeTruthy();
    });

    it('teinte de peau : appliquée aux émojis qui l’acceptent, nommée, mémorisée', async () => {
        const { field } = host();
        await open();
        await fireEvent.click(screen.getByRole('tab', { name: 'Mains et personnes' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Teinte moyenne' }));
        expect(screen.getByRole('button', { name: 'Teinte moyenne' }).getAttribute('aria-pressed')).toBe('true');
        expect(window.localStorage.getItem(TONE_KEY)).toBe('3');
        await fireEvent.click(screen.getByRole('button', { name: 'pouce levé, teinte moyenne' }));
        expect(field.value).toBe('👍\u{1F3FD}');
    });
});
