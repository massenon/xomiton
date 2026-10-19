// @vitest-environment jsdom
// Champ de recherche de l'en-tête (lot recherche-membre, P8-13) : XmHeader envoie vers /recherche (visite Inertia, champ
// vidé), charge au premier focus le morceau des suggestions (combobox ARIA : listbox groupée par famille, option active
// par aria-activedescendant, ↓ ↑ Entrée Échap), anti-rebond avant `GET search/suggestions`, rien sous deux caractères ni
// hors connexion, nombre annoncé, liste refermée quand le champ perd le focus (rouverte au retour), bornes `ui.search`,
// mode discret (terme hors de l'adresse), Referer réduit sur /recherche, et aucun terme écrit dans le stockage du navigateur.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { fireEvent, waitFor } from '@testing-library/vue';
import { router } from '@inertiajs/vue3';
import { browser } from '@/app/memberNav';
import { useUiStore } from '@/stores/ui';
import { memberProps, mountInertia, unmountInertia } from '@/test/inertia';
import { takeHeldTerm } from '@/search/suggest';
import { fakeFetch, json } from '@/test/feed';
import type { SearchSection, Suggestion } from '@/search/types';
import XmHeader from '@/components/XmHeader.vue';

const $ = <T extends Element = HTMLElement>(selector: string) => document.querySelector<T>(selector);
const $$ = (selector: string) => [...document.querySelectorAll<HTMLElement>(selector)];

const suggestion = (type: Suggestion['type'], id: number, title: string, url: string, inertia: boolean): Suggestion => ({
    type, id, title, meta: null, url, inertia, icon: 'bi-folder2-open', avatar_url: null,
});

const SECTIONS: SearchSection<Suggestion>[] = [
    { type: 'ressources', label: 'Ressources', icon: 'bi-folder2-open', total: 1, more: false, items: [suggestion('ressources', 3, 'Guide Zemidjan', 'http://localhost:3000/ressources/guide-zemidjan', false)] },
    { type: 'contacts', label: 'Contacts', icon: 'bi-person-vcard', total: 1, more: false, items: [suggestion('contacts', 9, 'Centre Zemidjan', 'http://localhost:3000/annuaire?q=Centre+Zemidjan', true)] },
];

let setItem: ReturnType<typeof vi.spyOn>;

beforeEach(() => {
    setItem = vi.spyOn(Storage.prototype, 'setItem');
});

afterEach(() => {
    unmountInertia();
    vi.useRealTimers();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

async function mountHeader(props = memberProps()) {
    await mountInertia(defineComponent({ render: () => h(XmHeader) }), { url: '/fil', props });
    const input = $('#memberSearch') as HTMLInputElement;
    await fireEvent.focus(input);
    await waitFor(() => expect($('[data-xm-search-suggest]')).not.toBeNull());
    return input;
}

async function type(input: HTMLInputElement, value: string) {
    await fireEvent.update(input, value);
    await vi.advanceTimersByTimeAsync(300);
    await nextTick();
}

describe('recherche de l’en-tête', () => {
    it('envoie vers /recherche en visite Inertia et vide le champ', async () => {
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        fakeFetch(() => json({ data: [] }));
        await mountInertia(defineComponent({ render: () => h(XmHeader) }), { url: '/fil' });
        const input = $('#memberSearch') as HTMLInputElement;

        expect(input.getAttribute('role')).toBe('combobox');
        expect(input.getAttribute('aria-expanded')).toBe('false');
        expect(input.getAttribute('autocomplete')).toBe('off');
        await fireEvent.update(input, '  violence conjugale ');
        await fireEvent.submit(input.form as HTMLFormElement);

        expect(get).toHaveBeenCalledWith('/recherche', { q: 'violence conjugale' });
        expect(input.value).toBe('');
    });

    it('propose des suggestions groupées après la pause de frappe, parcourues au clavier', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const { calls } = fakeFetch(() => json({ data: SECTIONS, meta: { too_short: false } }));
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        const input = await mountHeader();

        await type(input, 'z');
        expect(calls.filter((call) => call.url.includes('/search/suggestions'))).toHaveLength(0);

        await type(input, 'zemidjan');
        const requests = calls.filter((call) => call.url.includes('/search/suggestions'));
        expect(requests).toHaveLength(1);
        expect(requests[0].url).toBe('/api/v1/search/suggestions?q=zemidjan');
        await waitFor(() => expect(input.getAttribute('aria-expanded')).toBe('true'));

        const listbox = $('#memberSearchSuggest') as HTMLElement;
        expect(input.getAttribute('aria-controls')).toBe('memberSearchSuggest');
        expect(listbox.getAttribute('role')).toBe('listbox');
        expect($$('#memberSearchSuggest [role="group"]').map((group) => group.getAttribute('aria-labelledby'))).toEqual(['memberSearchSuggest-ressources', 'memberSearchSuggest-contacts']);
        expect($$('#memberSearchSuggest [role="option"]').map((option) => option.textContent?.trim())).toEqual(['Guide Zemidjan', 'Centre Zemidjan', 'Voir tous les résultats']);
        await waitFor(() => expect($('[data-xm-search-live]')?.textContent).toContain('2\u00a0suggestions'));

        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        expect(input.getAttribute('aria-activedescendant')).toBe('memberSearchSuggest-contacts-9');
        expect($('#memberSearchSuggest-contacts-9')?.getAttribute('aria-selected')).toBe('true');
        await fireEvent.keyDown(input, { key: 'ArrowUp' });
        await fireEvent.keyDown(input, { key: 'ArrowUp' });
        expect(input.getAttribute('aria-activedescendant')).toBe('memberSearchSuggest-all');

        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'ArrowDown' });
        await fireEvent.keyDown(input, { key: 'Enter' });
        expect(visit).toHaveBeenCalledWith('/annuaire?q=Centre+Zemidjan');
        expect(input.value).toBe('');
        await waitFor(() => expect(input.getAttribute('aria-expanded')).toBe('false'));
        expect(setItem).not.toHaveBeenCalled();
    });

    it('ouvre une page publique par un chargement complet, referme sur Échap et se tait hors connexion', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const { calls } = fakeFetch(() => json({ data: SECTIONS, meta: { too_short: false } }));
        const assign = vi.spyOn(browser, 'assign').mockImplementation(() => undefined);
        const input = await mountHeader();

        await type(input, 'zemidjan');
        await waitFor(() => expect(input.getAttribute('aria-expanded')).toBe('true'));
        await fireEvent.keyDown(input, { key: 'Escape' });
        expect(input.getAttribute('aria-expanded')).toBe('false');

        await type(input, 'zemidjan ');
        await waitFor(() => expect(input.getAttribute('aria-expanded')).toBe('true'));
        await fireEvent.click($('#memberSearchSuggest-ressources-3') as HTMLElement);
        expect(assign).toHaveBeenCalledWith('/ressources/guide-zemidjan');

        useUiStore().setOnline(false);
        const before = calls.length;
        await type(input, 'conseils');
        expect(calls.length).toBe(before);
        expect($('[data-xm-search-suggest]')?.textContent).toContain('Hors connexion');
    });

    it('referme la liste quand le champ perd le focus et la rouvre au retour (motif combobox)', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        fakeFetch(() => json({ data: SECTIONS, meta: { too_short: false } }));
        const input = await mountHeader();

        await type(input, 'zemidjan');
        await waitFor(() => expect(input.getAttribute('aria-expanded')).toBe('true'));
        await fireEvent.blur(input);
        expect(input.getAttribute('aria-expanded')).toBe('false');
        expect(($('[data-xm-search-suggest]') as HTMLElement).style.display).toBe('none');

        await fireEvent.focus(input);
        expect(input.getAttribute('aria-expanded')).toBe('true');
        // Un clic sur une option ne retire pas le focus du champ (mousedown sans effet par défaut).
        const mousedown = new MouseEvent('mousedown', { bubbles: true, cancelable: true });
        ($('#memberSearchSuggest-ressources-3') as HTMLElement).dispatchEvent(mousedown);
        expect(mousedown.defaultPrevented).toBe(true);
    });

    it('lit les bornes du terme partagées par le serveur (`ui.search`)', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const { calls } = fakeFetch(() => json({ data: SECTIONS, meta: { too_short: false } }));
        const input = await mountHeader(memberProps({ ui: { search: { min_length: 4, max_length: 12 } } }));

        expect(input.getAttribute('maxlength')).toBe('12');
        await type(input, 'zem');
        expect(calls.filter((call) => call.url.includes('/search/suggestions'))).toHaveLength(0);
        await type(input, 'zemi');
        expect(calls.filter((call) => call.url.includes('/search/suggestions'))).toHaveLength(1);
    });

    it('en mode discret, garde le terme hors de l’adresse de /recherche', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const get = vi.spyOn(router, 'get').mockImplementation(() => undefined as never);
        const visit = vi.spyOn(router, 'visit').mockImplementation(() => undefined as never);
        fakeFetch(() => json({ data: SECTIONS, meta: { too_short: false } }));
        const input = await mountHeader(memberProps({ auth: { user: { profile: { discreet_mode: true } } } }));

        await fireEvent.update(input, 'violence conjugale');
        await fireEvent.submit(input.form as HTMLFormElement);
        expect(get).toHaveBeenCalledWith('/recherche');
        expect(takeHeldTerm()).toBe('violence conjugale');

        await type(input, 'zemidjan');
        await waitFor(() => expect(input.getAttribute('aria-expanded')).toBe('true'));
        await fireEvent.click($('#memberSearchSuggest-all') as HTMLElement);
        expect(visit).toHaveBeenCalledWith('/recherche');
        expect(takeHeldTerm()).toBe('zemidjan');
        expect(setItem).not.toHaveBeenCalled();
    });

    it('réduit le Referer à l’origine tant que l’adresse est /recherche', async () => {
        fakeFetch(() => json({ data: [] }));
        await mountHeader();
        const meta = () => document.head.querySelector<HTMLMetaElement>('meta[name="referrer"]')?.content;

        expect(meta()).toBe('strict-origin-when-cross-origin');
        document.dispatchEvent(new CustomEvent('inertia:start', { detail: { visit: { url: new URL('http://localhost:3000/recherche?q=zou') } } }));
        expect(meta()).toBe('strict-origin');
        document.dispatchEvent(new CustomEvent('inertia:navigate', { detail: { page: { url: '/fil' } } }));
        expect(meta()).toBe('strict-origin-when-cross-origin');
    });
});
