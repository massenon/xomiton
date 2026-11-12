// @vitest-environment jsdom
// Médias d'une publication (P7-10, Q96) : galerie de photos (1 pleine largeur, 2 à 4 en grille, `alt` de chaque photo,
// chargement paresseux, vrais boutons), visionneuse modale (ouverture au clavier, flèches ← / →, Échap, focus piégé et
// rendu, compteur, annonce), lecteur vidéo natif (controls, preload metadata, playsinline, aucune lecture automatique,
// description en aria-label et légende, repli texte si illisible).
import axe from 'axe-core';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen, waitFor } from '@testing-library/vue';
import { renderXm } from '@/test/helpers';
import { makeImage, makeVideo } from '@/test/feed';
import XmMediaGallery from './XmMediaGallery.vue';
import XmVideoPlayer from './XmVideoPlayer.vue';

afterEach(() => {
    cleanup();
    document.body.style.overflow = '';
    vi.restoreAllMocks();
});

const gallery = (count: number, extra: Record<string, unknown>[] = []) => renderXm(XmMediaGallery, {
    props: { author: 'Awa_S', media: [...Array.from({ length: count }, (_, i) => makeImage(i + 1)), ...extra] as never },
});
const items = () => [...document.querySelectorAll<HTMLButtonElement>('[data-xm-gallery-item]')];
const viewer = () => document.querySelector('[data-xm-viewer]') as HTMLElement | null;
const counter = () => document.querySelector('.xm-viewer-count')?.textContent;
const status = () => document.querySelector('[data-xm-viewer-live]')?.textContent ?? '';
const openAt = async (index: number) => {
    items()[index].focus();
    await fireEvent.click(items()[index]);
    await waitFor(() => expect(viewer()).not.toBeNull());
    await waitFor(() => expect(document.activeElement?.getAttribute('aria-label')).toBe('Fermer la visionneuse'));
};

/** axe-core sous jsdom (le contraste et la mise en page ne se mesurent pas ici : parcours Playwright, tests/e2e/a11y.spec.js). */
const violations = async (node: Element = document.body) => (await axe.run(node, { rules: { 'color-contrast': { enabled: false }, region: { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`);

describe('Galerie', () => {
    it('n’a aucune violation axe (galerie, visionneuse ouverte, lecteur vidéo)', async () => {
        renderXm(XmMediaGallery, { props: { author: 'Awa_S', media: [makeImage(1), makeImage(2), makeImage(3), makeVideo()] as never } });
        await waitFor(() => expect(document.querySelector('video')).not.toBeNull());
        expect(await violations()).toEqual([]);
        await openAt(1);
        expect(await violations()).toEqual([]);
    });

    it('affiche une seule photo pleine largeur avec son texte alternatif et un chargement paresseux', () => {
        gallery(1);
        const root = document.querySelector('[data-xm-gallery]') as HTMLElement;
        expect(root.className).toContain('xm-gallery-1');
        expect(root.getAttribute('role')).toBe('group');
        expect(root.getAttribute('aria-label')).toBe('Photos de la publication');
        const image = root.querySelector('img') as HTMLImageElement;
        expect(image.alt).toBe('Photo 1');
        expect(image.getAttribute('loading')).toBe('lazy');
        expect(image.getAttribute('width')).toBe('1600');
        expect(image.getAttribute('height')).toBe('1200');
    });

    it.each([2, 3, 4])('met %i photos en grille, chacune dans un vrai bouton avec son alt', (count) => {
        gallery(count);
        expect((document.querySelector('[data-xm-gallery]') as HTMLElement).className).toContain(`xm-gallery-${count}`);
        expect(items()).toHaveLength(count);
        items().forEach((button, index) => {
            expect(button.tagName).toBe('BUTTON');
            expect(button.getAttribute('type')).toBe('button');
            expect(button.querySelector('img')?.alt).toBe(`Photo ${index + 1}`);
            expect(button.textContent).toBe(`Agrandir la photo ${index + 1} sur ${count}`);
        });
    });

    it('remplace un texte alternatif absent par un repli qui dit la place de la photo', () => {
        renderXm(XmMediaGallery, { props: { author: 'Awa_S', media: [makeImage(1, { alt: null }), makeImage(2, { alt: '' })] as never } });
        expect(items().map((button) => button.querySelector('img')?.alt)).toEqual([
            'Photo 1 sur 2 de la publication de Awa_S', 'Photo 2 sur 2 de la publication de Awa_S',
        ]);
    });

    it('ignore un média sans lien et ne rend rien sans photo ni vidéo', () => {
        renderXm(XmMediaGallery, { props: { author: 'Awa_S', media: [makeImage(1, { url: '' })] as never } });
        expect(document.querySelector('[data-xm-gallery]')).toBeNull();
        expect(document.querySelector('video')).toBeNull();
    });
});

describe('Visionneuse', () => {
    it('s’ouvre à la souris, au toucher ou au clavier (Entrée) sur la photo choisie, avec compteur et annonce', async () => {
        gallery(3);
        await openAt(1);
        const dialog = viewer() as HTMLElement;
        expect(dialog.getAttribute('role')).toBe('dialog');
        expect(dialog.getAttribute('aria-modal')).toBe('true');
        expect(dialog.getAttribute('aria-label')).toBe('Visionneuse de photos');
        expect(counter()).toBe('2 / 3');
        expect((dialog.querySelector('figure img') as HTMLImageElement).alt).toBe('Photo 2');
        expect(status()).toBe('Photo 2 sur 3. Photo 2');
        expect(document.body.style.overflow).toBe('hidden');
        expect(document.querySelector('[data-xm-gallery-item="1"]')?.getAttribute('aria-haspopup')).toBe('dialog');
    });

    it('change de photo avec ← et →, en boucle, et annonce la photo courante', async () => {
        gallery(3);
        await openAt(0);
        const dialog = viewer() as HTMLElement;
        await fireEvent.keyDown(dialog, { key: 'ArrowRight' });
        expect(counter()).toBe('2 / 3');
        expect(status()).toBe('Photo 2 sur 3. Photo 2');
        await fireEvent.keyDown(dialog, { key: 'ArrowRight' });
        await fireEvent.keyDown(dialog, { key: 'ArrowRight' });
        expect(counter()).toBe('1 / 3');
        await fireEvent.keyDown(dialog, { key: 'ArrowLeft' });
        expect(counter()).toBe('3 / 3');
        await fireEvent.keyDown(dialog, { key: 'Home' });
        expect(counter()).toBe('1 / 3');
        await fireEvent.keyDown(dialog, { key: 'End' });
        expect(counter()).toBe('3 / 3');
        await fireEvent.click(screen.getByRole('button', { name: 'Photo suivante' }));
        expect(counter()).toBe('1 / 3');
        await fireEvent.click(screen.getByRole('button', { name: 'Photo précédente' }));
        expect(counter()).toBe('3 / 3');
    });

    it('piège le focus (Tab et Maj+Tab bouclent dans la fenêtre)', async () => {
        gallery(3);
        await openAt(0);
        const dialog = viewer() as HTMLElement;
        const close = screen.getByRole('button', { name: 'Fermer la visionneuse' });
        const previous = screen.getByRole('button', { name: 'Photo précédente' });
        const next = screen.getByRole('button', { name: 'Photo suivante' });
        // Ordre du DOM : Fermer, Précédente, Suivante.
        expect(document.activeElement).toBe(close);
        next.focus();
        await fireEvent.keyDown(dialog, { key: 'Tab' });
        expect(document.activeElement).toBe(close);
        await fireEvent.keyDown(dialog, { key: 'Tab', shiftKey: true });
        expect(document.activeElement).toBe(next);
        // Au milieu, le navigateur gère Tab : le piège n'intervient qu'aux extrémités.
        previous.focus();
        await fireEvent.keyDown(dialog, { key: 'Tab' });
        expect(document.activeElement).toBe(previous);
    });

    it('se ferme avec Échap, avec le bouton Fermer ou en cliquant le fond, rend le focus à la photo ouverte et le défilement', async () => {
        gallery(2);
        await openAt(1);
        await fireEvent.keyDown(viewer() as HTMLElement, { key: 'Escape' });
        await waitFor(() => expect(viewer()).toBeNull());
        expect(document.activeElement).toBe(items()[1]);
        expect(document.body.style.overflow).toBe('');

        await openAt(0);
        await fireEvent.click(screen.getByRole('button', { name: 'Fermer la visionneuse' }));
        await waitFor(() => expect(viewer()).toBeNull());
        expect(document.activeElement).toBe(items()[0]);

        await openAt(0);
        await fireEvent.click(document.querySelector('.xm-viewer-stage') as HTMLElement);
        await waitFor(() => expect(viewer()).toBeNull());
    });

    it('n’offre ni flèches ni boucle pour une seule photo', async () => {
        gallery(1);
        await openAt(0);
        expect(screen.queryByRole('button', { name: 'Photo suivante' })).toBeNull();
        expect(counter()).toBe('1 / 1');
        await fireEvent.keyDown(viewer() as HTMLElement, { key: 'ArrowRight' });
        expect(counter()).toBe('1 / 1');
    });
});

describe('Lecteur vidéo', () => {
    it('rend un lecteur natif sans lecture automatique, avec la description en aria-label et en légende', async () => {
        renderXm(XmMediaGallery, { props: { author: 'Awa_S', media: [makeVideo()] as never } });
        await waitFor(() => expect(document.querySelector('video')).not.toBeNull());
        const video = document.querySelector('video') as HTMLVideoElement;
        expect(video.hasAttribute('controls')).toBe(true);
        expect(video.getAttribute('preload')).toBe('metadata');
        expect(video.hasAttribute('playsinline')).toBe(true);
        expect(video.hasAttribute('autoplay')).toBe(false);
        expect(video.hasAttribute('muted')).toBe(false);
        expect(video.hasAttribute('loop')).toBe(false);
        expect(video.getAttribute('src')).toBe(makeVideo().url);
        expect(video.getAttribute('aria-label')).toBe('Des femmes assises en cercle qui discutent d’un projet.');
        expect(document.querySelector('figcaption')?.textContent).toBe('Des femmes assises en cercle qui discutent d’un projet.');
        expect(video.textContent).toBe('Ton navigateur ne peut pas lire cette vidéo.');
    });

    it('garde la description lisible en attendant le lecteur', () => {
        renderXm(XmMediaGallery, { props: { author: 'Awa_S', media: [makeVideo()] as never } });
        expect(document.querySelector('[data-xm-video-pending] figcaption')?.textContent).toContain('Des femmes assises');
    });

    it('remplace le lecteur par un texte si le fichier n’est pas lisible, en gardant la description', async () => {
        renderXm(XmVideoPlayer, { props: { media: makeVideo() } });
        await fireEvent.error(document.querySelector('video') as HTMLVideoElement);
        expect(document.querySelector('video')).toBeNull();
        expect(screen.getByRole('status').textContent).toContain('Cette vidéo ne peut pas être lue pour le moment. Recharge la page, puis réessaie.');
        expect(document.querySelector('figcaption')?.textContent).toContain('Des femmes assises');
        expect(document.querySelector('figcaption')?.getAttribute('aria-hidden')).toBeNull();
    });

    it('une vidéo sans description garde un nom accessible', () => {
        renderXm(XmVideoPlayer, { props: { media: makeVideo({ alt: null }) } });
        expect(document.querySelector('video')?.getAttribute('aria-label')).toBe('Vidéo de la publication');
    });
});
