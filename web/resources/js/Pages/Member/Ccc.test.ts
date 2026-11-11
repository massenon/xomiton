// @vitest-environment jsdom
// Page /ccc (lot outils-ccc, P8-12 ; prototype ccc.php) : onglets, manuel (modules, cases « point vu » gardées sur l'appareil,
// progression par module et globale annoncée en aria-live, impression), boîtes à images et « Mode animation » (clavier ← →
// Échap, focus piégé et rendu, plein écran avec repli), lecteur de BD (alt par planche, progression), états vides, carte
// des lois, drapeau « mode présentation » du pair (P9-05). Pas de quiz.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { flushPromises } from '@vue/test-utils';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { fakeFetch, json } from '@/test/feed';
import { loadSeen, storageKey } from '@/ccc/progress';
import type { CccTools, ManualModule, PagedTool } from '@/ccc/types';
import Ccc from './Ccc.vue';

afterEach(() => {
    unmountInertia();
    localStorage.clear();
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    document.body.style.overflow = '';
});

const manual = (key: string, title: string, chapters: string[], minutes: number | null = 45): ManualModule => ({ key, family: 'manuel', title, description: null, cover: null, duration_minutes: minutes, chapters, chapters_count: chapters.length });
const paged = (key: string, family: 'boites_images' | 'bd', title: string, pages: { caption: string | null; alt: string }[], description: string | null = null): PagedTool => ({
    key, family, title, description, cover: `/img/${key}.jpg`,
    pages: pages.map((page, rank) => ({ position: rank + 1, image: `/img/${key}-${rank + 1}.jpg`, ...page })), pages_count: pages.length,
});

const tools = (overrides: Partial<CccTools> = {}): CccTools => ({
    manuel: [manual('m1', 'Module 1 – Poser le cadre', ['Rôle et limites', 'Écoute active', 'Confidentialité', 'Prendre soin de soi']), manual('m2', 'Module 2 – Orienter', ['Annuaire', 'Fiche de référence'], 60)],
    boites_images: [
        paged('bi1', 'boites_images', 'Le consentement', [{ caption: 'Que se passe-t-il ?', alt: 'Deux personnes discutent' }], 'Demandez au groupe ce que chacun·e voit.'),
        paged('bi2', 'boites_images', 'Les réseaux de soutien', [{ caption: null, alt: 'Un réseau de personnes' }, { caption: 'Qui appeler ?', alt: 'Un téléphone' }]),
    ],
    bd: [paged('bd1', 'bd', 'Léa et le « non »', [{ caption: 'Léa est invitée.', alt: 'Léa devant une porte' }, { caption: 'Elle dit non.', alt: 'Léa lève la main' }, { caption: 'Elle quitte la pièce.', alt: 'Léa dans le couloir' }])],
    ...overrides,
});

const base = (overrides: Record<string, unknown> = {}) => ({ title: 'Outils CCC', presentation: false, tools: tools(), laws: { count: 3, url: 'https://exemple.test/lois' }, ...overrides });

const mount = (overrides: Record<string, unknown> = {}, url = '/ccc') => {
    fakeFetch(() => json({}));
    return mountInertia(Ccc, { url, pageProps: base(overrides) });
};

const panel = (name: string) => screen.getByRole('tabpanel', { name, hidden: true });

describe('Page /ccc', () => {
    it('affiche les quatre onglets et l’en-tête, sans quiz', async () => {
        await mount();

        expect(screen.getByRole('heading', { level: 1, name: 'Outils CCC interactifs' })).toBeTruthy();
        const tabs = screen.getAllByRole('tab').map((tab) => tab.textContent);
        expect(tabs).toEqual(['Manuel', 'Boîtes à images', 'BD SSR', 'Lois']);
        expect(document.body.textContent).not.toMatch(/quiz/i);
        expect(document.querySelector('[data-ccc-presentation]')).toBeNull();
    });

    it('signale le mode présentation du pair (drapeau serveur)', async () => {
        await mount({ presentation: true });

        const notice = document.querySelector('[data-ccc-presentation]') as HTMLElement;
        expect(notice.textContent).toContain('Mode présentation');
    });
});

describe('Manuel', () => {
    it('liste les modules : titre, durée, nombre de points et progression à zéro', async () => {
        await mount();

        const list = document.querySelector('[data-ccc-modules]') as HTMLElement;
        const first = within(list).getByRole('article', { name: 'Module 1 – Poser le cadre' });
        expect(first.textContent).toContain('45 min');
        expect(first.textContent).toContain('4 points');
        expect(within(first).getByRole('progressbar').getAttribute('aria-valuenow')).toBe('0');
        const global = screen.getByRole('progressbar', { name: 'Progression globale' });
        expect(global.getAttribute('aria-valuenow')).toBe('0');
        expect(global.getAttribute('aria-valuetext')).toBe('0 sur 6 points vus');
    });

    it('coche des points : progression par module et globale, annonce aria-live, état gardé sur l’appareil seulement', async () => {
        const { calls } = fakeFetch(() => json({}));
        await mountInertia(Ccc, { url: '/ccc', pageProps: base() });
        calls.length = 0;

        await fireEvent.click(screen.getByRole('button', { name: /Ouvrir le module\s: Module 1/ }));
        const detail = document.querySelector('[data-ccc-detail]') as HTMLElement;
        await waitFor(() => expect(document.activeElement).toBe(within(detail).getByRole('heading', { name: 'Module 1 – Poser le cadre' })));

        await fireEvent.click(within(detail).getByRole('checkbox', { name: 'Rôle et limites' }));
        await fireEvent.click(within(detail).getByRole('checkbox', { name: 'Confidentialité' }));

        expect(within(detail).getByRole('progressbar').getAttribute('aria-valuenow')).toBe('50');
        expect(screen.getByRole('progressbar', { name: 'Progression globale' }).getAttribute('aria-valuenow')).toBe('33');
        const live = document.querySelector('.xm-ccc-manual [data-ccc-live]') as HTMLElement;
        expect(live.getAttribute('aria-live')).toBe('polite');
        expect(live.textContent).toContain('2 sur 4 points vus dans ce module.');
        expect(live.textContent).toContain('Progression globale\u00a0: 33\u00a0%.');

        // Gardé dans le navigateur, par compte (id 7), jamais envoyé au serveur.
        expect(loadSeen(7, tools().manuel).seen).toEqual({ m1: [0, 2] });
        expect(JSON.parse(localStorage.getItem(storageKey(7)) ?? '{}')).toEqual({ m1: [0, 2] });
        expect(calls).toHaveLength(0);

        await fireEvent.click(within(detail).getByRole('checkbox', { name: 'Rôle et limites' }));
        expect(loadSeen(7, tools().manuel).seen).toEqual({ m1: [2] });
    });

    it('reprend la progression gardée et revient à la liste avec le focus sur le module', async () => {
        localStorage.setItem(storageKey(7), JSON.stringify({ m2: [0, 1], m1: [1], m9: [0] }));
        await mount();

        expect(screen.getByRole('progressbar', { name: 'Progression globale' }).getAttribute('aria-valuenow')).toBe('50');
        const second = screen.getByRole('article', { name: 'Module 2 – Orienter' });
        expect(second.textContent).toContain('Module terminé');

        const opener = screen.getByRole('button', { name: /Ouvrir le module\s: Module 2/ });
        await fireEvent.click(opener);
        await fireEvent.click(screen.getByRole('button', { name: 'Tous les modules' }));
        await waitFor(() => expect(document.activeElement).toBe(screen.getByRole('button', { name: /Ouvrir le module\s: Module 2/ })));
    });

    it('fonctionne sans stockage : avertit que la progression ne sera pas gardée', async () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('plein'); });
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: /Ouvrir le module\s: Module 1/ }));
        await fireEvent.click(screen.getByRole('checkbox', { name: 'Écoute active' }));

        expect(screen.getByRole('progressbar', { name: 'Progression globale' }).getAttribute('aria-valuenow')).toBe('17');
        expect(screen.getByText(/ne permet pas de garder ta progression/)).toBeTruthy();
    });

    it('efface la progression de l’appareil', async () => {
        localStorage.setItem(storageKey(7), JSON.stringify({ m1: [0] }));
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Effacer ma progression' }));
        expect(localStorage.getItem(storageKey(7))).toBeNull();
        expect(screen.getByRole('progressbar', { name: 'Progression globale' }).getAttribute('aria-valuenow')).toBe('0');
        expect(screen.getByText('Ta progression a été effacée de cet appareil.')).toBeTruthy();
    });

    it('imprime le récapitulatif : points vus par module, feuille réservée à l’impression', async () => {
        localStorage.setItem(storageKey(7), JSON.stringify({ m1: [0] }));
        const print = vi.fn();
        vi.stubGlobal('print', print);
        await mount();

        await fireEvent.click(screen.getByRole('button', { name: 'Imprimer le récapitulatif' }));
        expect(print).toHaveBeenCalledOnce();
        const sheet = document.querySelector('[data-ccc-print]') as HTMLElement;
        expect(sheet.classList.contains('xm-ccc-print')).toBe(true);
        expect(sheet.textContent).toContain('Module 1 – Poser le cadre\u00a0: 1 sur 4 points vus');
        expect(sheet.textContent).toContain('☑ Rôle et limites');
        expect(sheet.textContent).toContain('☐ Écoute active');
        expect(sheet.textContent).toContain('Édité le');
    });

    it('affiche un état vide sans module publié', async () => {
        await mount({ tools: tools({ manuel: [] }) });

        expect(within(panel('Manuel')).getByText('Aucun module du manuel n’est publié pour le moment.')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Imprimer le récapitulatif' })).toBeNull();
    });
});

describe('Boîtes à images et mode animation', () => {
    const start = async () => {
        const opener = within(panel('Boîtes à images')).getByRole('button', { name: 'Mode animation' });
        opener.focus();
        await fireEvent.click(opener);
        return opener;
    };

    it('affiche la grille : image, titre, nombre d’images et consigne', async () => {
        await mount();

        const card = within(panel('Boîtes à images')).getByRole('article', { name: 'Le consentement' });
        expect(card.textContent).toContain('1 image');
        expect(card.textContent).toContain('Demandez au groupe ce que chacun·e voit.');
        expect(within(card).getByRole('img', { name: 'Deux personnes discutent' })).toBeTruthy();
        expect(within(panel('Boîtes à images')).getByRole('article', { name: 'Les réseaux de soutien' }).textContent).toContain('2 images');
        expect(screen.getByText('Questions types pour animer')).toBeTruthy();
    });

    it('ouvre le mode animation : plein écran demandé, focus sur Fermer, navigation clavier, fermeture et retour du focus', async () => {
        const request = vi.fn(() => Promise.resolve());
        (HTMLElement.prototype as unknown as { requestFullscreen: () => Promise<void> }).requestFullscreen = request;
        await mount();

        const opener = await start();
        const dialog = await screen.findByRole('dialog', { name: 'Mode animation' });
        expect(request).toHaveBeenCalled();
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(document.body.style.overflow).toBe('hidden');
        expect(document.activeElement).toBe(within(dialog).getByRole('button', { name: /Fermer/ }));
        expect(within(dialog).getByText('Image 1 sur 3')).toBeTruthy();
        expect(within(dialog).getByRole('img', { name: 'Deux personnes discutent' })).toBeTruthy();
        // Mode membre : pas de guide de l'animateur·rice dans la fenêtre.
        expect(dialog.querySelector('[data-ccc-show-guide]')).toBeNull();

        await fireEvent.keyDown(document, { key: 'ArrowRight' });
        expect(within(dialog).getByText('Image 2 sur 3')).toBeTruthy();
        expect(dialog.querySelector('[data-ccc-show-title]')?.textContent).toBe('Les réseaux de soutien');
        const live = dialog.querySelector('[data-ccc-show-live]') as HTMLElement;
        expect(live.getAttribute('aria-live')).toBe('polite');
        expect(live.textContent).toBe('Image 2 sur 3\u00a0: Les réseaux de soutien');

        await fireEvent.keyDown(document, { key: 'ArrowRight' });
        await fireEvent.keyDown(document, { key: 'ArrowRight' });
        expect(within(dialog).getByText('Image 1 sur 3')).toBeTruthy();
        await fireEvent.keyDown(document, { key: 'ArrowLeft' });
        expect(within(dialog).getByText('Image 3 sur 3')).toBeTruthy();
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Image précédente' }));
        expect(within(dialog).getByText('Image 2 sur 3')).toBeTruthy();
        await fireEvent.click(within(dialog).getByRole('button', { name: 'Image suivante' }));
        expect(within(dialog).getByText('Image 3 sur 3')).toBeTruthy();

        await fireEvent.keyDown(document, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Mode animation' })).toBeNull());
        expect(document.body.style.overflow).toBe('');
        expect(document.activeElement).toBe(opener);
        delete (HTMLElement.prototype as unknown as { requestFullscreen?: unknown }).requestFullscreen;
    });

    it('garde le focus dans la fenêtre (Tab et Maj+Tab bouclent) et ferme avec le bouton', async () => {
        await mount();
        await start();
        const dialog = await screen.findByRole('dialog', { name: 'Mode animation' });
        const close = within(dialog).getByRole('button', { name: /Fermer/ });
        const next = within(dialog).getByRole('button', { name: 'Image suivante' });

        next.focus();
        await fireEvent.keyDown(document, { key: 'Tab' });
        expect(document.activeElement).toBe(close);
        await fireEvent.keyDown(document, { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(next);
        // Focus sorti de la fenêtre : ramené dedans.
        (document.querySelector('[role="tab"]') as HTMLElement).focus();
        await fireEvent.keyDown(document, { key: 'Tab' });
        expect(dialog.contains(document.activeElement)).toBe(true);

        await fireEvent.click(close);
        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Mode animation' })).toBeNull());
    });

    it('se replie sur la fenêtre plein cadre quand le plein écran est refusé, et se ferme avec la sortie du plein écran', async () => {
        const request = vi.fn(() => Promise.reject(new Error('refusé')));
        (HTMLElement.prototype as unknown as { requestFullscreen: () => Promise<void> }).requestFullscreen = request;
        await mount();
        await start();
        const dialog = await screen.findByRole('dialog', { name: 'Mode animation' });
        await flushPromises();
        expect(request).toHaveBeenCalled();
        expect(dialog.isConnected).toBe(true);

        // Plein écran obtenu puis quitté par le navigateur (Échap) : la fenêtre se ferme avec lui.
        Object.defineProperty(document, 'fullscreenElement', { configurable: true, get: () => dialog });
        document.dispatchEvent(new Event('fullscreenchange'));
        Object.defineProperty(document, 'fullscreenElement', { configurable: true, get: () => null });
        document.dispatchEvent(new Event('fullscreenchange'));
        await waitFor(() => expect(screen.queryByRole('dialog', { name: 'Mode animation' })).toBeNull());
        delete (HTMLElement.prototype as unknown as { requestFullscreen?: unknown }).requestFullscreen;
        delete (document as unknown as { fullscreenElement?: unknown }).fullscreenElement;
    });

    it('anime une seule boîte et montre le guide en mode présentation (pair)', async () => {
        await mount({ presentation: true });

        await fireEvent.click(within(panel('Boîtes à images')).getByRole('button', { name: /Mode animation\s: Le consentement/ }));
        const dialog = await screen.findByRole('dialog', { name: 'Mode animation' });
        expect(within(dialog).getByText('Image 1 sur 1')).toBeTruthy();
        expect(dialog.classList.contains('xm-ccc-show--presentation')).toBe(true);
        expect(dialog.querySelector('[data-ccc-show-guide]')?.textContent).toContain('Demandez au groupe ce que chacun·e voit.');
        expect(dialog.querySelector('[data-ccc-show-caption]')?.textContent).toBe('Que se passe-t-il ?');
    });

    it('affiche un état vide sans boîte publiée', async () => {
        await mount({ tools: tools({ boites_images: [] }) });

        expect(within(panel('Boîtes à images')).getByText('Aucune boîte à images n’est publiée pour le moment.')).toBeTruthy();
        expect(within(panel('Boîtes à images')).queryByRole('button', { name: 'Mode animation' })).toBeNull();
    });
});

describe('BD SSR', () => {
    it('lit les planches : alt par planche, légende, compteur, progression, clavier, fin et discussion', async () => {
        await mount();

        const card = within(panel('BD SSR')).getByRole('article', { name: 'Léa et le « non »' });
        expect(card.textContent).toContain('3 planches');
        await fireEvent.click(within(card).getByRole('button', { name: /Lire la BD/ }));
        const reader = document.querySelector('[data-ccc-reader]') as HTMLElement;
        await waitFor(() => expect(document.activeElement).toBe(within(reader).getByRole('heading', { name: 'Léa et le « non »' })));

        expect(within(reader).getByRole('img', { name: 'Léa devant une porte' })).toBeTruthy();
        expect(within(reader).getByText('Planche 1 sur 3')).toBeTruthy();
        expect(within(reader).getByRole('progressbar').getAttribute('aria-valuenow')).toBe('33');
        expect((within(reader).getByRole('button', { name: 'Planche précédente' }) as HTMLButtonElement).disabled).toBe(true);

        await fireEvent.keyDown(document, { key: 'ArrowRight' });
        expect(within(reader).getByRole('img', { name: 'Léa lève la main' })).toBeTruthy();
        expect(reader.querySelector('[data-ccc-caption]')?.textContent).toBe('Elle dit non.');
        expect(within(reader).getByRole('progressbar').getAttribute('aria-valuenow')).toBe('67');
        const live = document.querySelector('[data-ccc-comics], [data-ccc-reader]')?.closest('section')?.querySelector('[data-ccc-live]') as HTMLElement;
        expect(live.textContent).toBe('Planche 2 sur 3\u00a0: Elle dit non.');

        await fireEvent.keyDown(document, { key: 'ArrowLeft' });
        expect(within(reader).getByText('Planche 1 sur 3')).toBeTruthy();
        await fireEvent.click(within(reader).getByRole('button', { name: 'Planche suivante' }));
        await fireEvent.click(within(reader).getByRole('button', { name: 'Planche suivante' }));
        expect(within(reader).getByRole('progressbar').getAttribute('aria-valuenow')).toBe('100');
        expect(reader.querySelector('[data-ccc-discussion]')).toBeNull();

        await fireEvent.click(within(reader).getByRole('button', { name: 'Terminer' }));
        const discussion = reader.querySelector('[data-ccc-discussion]') as HTMLElement;
        expect(discussion.textContent).toContain('Discutons de l’histoire');
        await waitFor(() => expect(document.activeElement).toBe(discussion));

        await fireEvent.click(within(discussion).getByRole('button', { name: 'Relire depuis le début' }));
        expect(within(reader).getByText('Planche 1 sur 3')).toBeTruthy();
        expect(reader.querySelector('[data-ccc-discussion]')).toBeNull();

        await fireEvent.click(within(reader).getByRole('button', { name: 'Toutes les histoires' }));
        await waitFor(() => expect(document.activeElement).toBe(within(panel('BD SSR')).getByRole('button', { name: /Lire la BD/ })));
    });

    it('affiche un état vide sans BD publiée', async () => {
        await mount({ tools: tools({ bd: [] }) });

        expect(within(panel('BD SSR')).getByText('Aucune bande dessinée n’est publiée pour le moment.')).toBeTruthy();
    });
});

describe('Lois', () => {
    it('mène à /lois avec le nombre de textes publiés', async () => {
        await mount();

        const laws = panel('Lois');
        expect(laws.textContent).toContain('3 textes publiés');
        expect(within(laws).getByRole('link', { name: 'Ouvrir les lois et codes' }).getAttribute('href')).toBe('https://exemple.test/lois');
    });

    it('affiche un état vide sans texte publié', async () => {
        await mount({ laws: { count: 0, url: '/lois' } });

        expect(within(panel('Lois')).getByText('Aucun texte de loi n’est publié pour le moment.')).toBeTruthy();
        expect(within(panel('Lois')).queryByRole('link')).toBeNull();
    });
});
