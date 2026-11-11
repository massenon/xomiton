// @vitest-environment jsdom
// XmCard, XmAvatar, XmImg, XmLogo, XmEmptyState, XmSkeleton (P6-04).
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup, screen } from '@testing-library/vue';
import { h } from 'vue';
import { renderXm } from '@/test/helpers';
import XmAvatar from './XmAvatar.vue';
import XmCard from './XmCard.vue';
import XmEmptyState from './XmEmptyState.vue';
import XmImg from './XmImg.vue';
import XmLogo from './XmLogo.vue';
import XmSkeleton from './XmSkeleton.vue';

afterEach(() => cleanup());

describe('XmCard', () => {
    it('carte entièrement cliquable par un seul lien étiré', () => {
        renderXm({ render: () => h(XmCard, { title: 'Consentement', href: '/thematiques/consentement' }, () => 'Texte') });
        const link = screen.getByRole('link', { name: 'Consentement' });
        expect(link.className).toBe('stretched-link text-reset text-decoration-none');
        expect(link.closest('article')?.className).toBe('card xm-card--content xm-card-hover h-100');
        expect(screen.getAllByRole('link')).toHaveLength(1);
    });

    it('actions dans le pied, ou dans l’en-tête pour une carte liste', () => {
        renderXm({ render: () => [
            h(XmCard, { title: 'A' }, { default: () => 'Corps', actions: () => h('button', 'Agir') }),
            h(XmCard, { title: 'B', header: true }, { actions: () => h('button', 'Exporter') }),
        ] });
        expect(screen.getByRole('button', { name: 'Agir' }).parentElement?.className).toContain('card-footer');
        expect(screen.getByRole('heading', { name: 'B' }).tagName).toBe('H2');
        expect(screen.getByRole('button', { name: 'Exporter' }).closest('.card-header')).not.toBeNull();
    });
});

describe('XmAvatar', () => {
    it('initiales (deux mots au plus) avec nom accessible, ou image', () => {
        renderXm({ render: () => [h(XmAvatar, { name: 'élodie awa dossou' }), h(XmAvatar, { name: 'Koffi', src: '/a.png', size: 'xs' })] });
        const initials = screen.getByRole('img', { name: 'Avatar de élodie awa dossou' });
        expect(initials.textContent).toBe('ÉA');
        const img = screen.getByRole('img', { name: 'Avatar de Koffi' });
        expect(img.getAttribute('width')).toBe('35');
        expect(img.getAttribute('loading')).toBe('lazy');
    });
});

describe('XmImg', () => {
    it('refuse une image sans alt ni source', () => {
        expect(() => renderXm({ render: () => h(XmImg, { src: '/a.jpg' }) })).toThrow(/alt/);
        expect(() => renderXm({ render: () => h(XmImg, { alt: 'x' }) })).toThrow(/source/);
    });

    it('image décorative : alt vide, aria-hidden, sans title', () => {
        const { container } = renderXm({ render: () => h(XmImg, { src: '/d.svg', alt: '' }) });
        const img = container.querySelector('img') as HTMLImageElement;
        expect(img.getAttribute('aria-hidden')).toBe('true');
        expect(img.hasAttribute('title')).toBe(false);
    });

    it('media résolu par l’API : <picture> WebP puis repli, sizes par défaut', () => {
        const { container } = renderXm({ render: () => h(XmImg, { alt: 'Atelier', media: { src: '/m-1024.jpg', srcset: '/m-320.jpg 320w, /m-1024.jpg 1024w', width: 1024, height: 576, sources: { 'image/webp': '/m-320.webp 320w, /m-1024.webp 1024w' } } }) });
        const source = container.querySelector('picture source') as HTMLSourceElement;
        expect(source.getAttribute('type')).toBe('image/webp');
        expect(source.getAttribute('sizes')).toBe('(max-width: 1600px) 100vw, 1600px');
        const img = container.querySelector('picture img') as HTMLImageElement;
        expect(img.getAttribute('width')).toBe('1024');
        expect(img.getAttribute('srcset')).toContain('/m-320.jpg 320w');
    });
});

describe('XmLogo', () => {
    it('lien d’accueil nommé, hauteur déduite du viewBox ; variante inconnue refusée', () => {
        renderXm({ render: () => h(XmLogo, { variant: 'vertical-couleur', width: 100 }) });
        const link = screen.getByRole('link', { name: 'Xomiton, accueil' });
        const img = link.querySelector('img') as HTMLImageElement;
        expect(img.getAttribute('src')).toBe('/assets/images/xomiton/brand/logo-vertical-couleur.svg');
        expect(img.getAttribute('height')).toBe('57');
        expect(() => renderXm({ render: () => h(XmLogo, { variant: 'arc-en-ciel' }) })).toThrow(/inconnue/);
    });
});

describe('XmEmptyState', () => {
    it('une phrase, une explication, une action', () => {
        renderXm({ render: () => h(XmEmptyState, { title: 'Aucun groupe' }, { default: () => 'Rejoins un groupe.', action: () => h('a', { href: '/groupes' }, 'Découvrir') }) });
        expect(screen.getByText('Aucun groupe').className).toBe('h5 text-body mt-2 mb-2');
        expect(screen.getByText('Rejoins un groupe.').className).toBe('mb-2 mx-auto xm-prose');
        expect(screen.getByRole('link', { name: 'Découvrir' }).parentElement?.className).toBe('mt-3');
    });
});

describe('XmSkeleton', () => {
    it('annonce le chargement, blocs décoratifs', () => {
        renderXm({ render: () => h(XmSkeleton, { type: 'table', rows: 4 }) });
        const status = screen.getByRole('status');
        expect(status.getAttribute('aria-busy')).toBe('true');
        expect(status.textContent).toContain('Chargement…');
        expect(status.querySelectorAll('[aria-hidden="true"] .border-bottom')).toHaveLength(4);
    });
});
