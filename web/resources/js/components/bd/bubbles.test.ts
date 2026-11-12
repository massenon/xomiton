// @vitest-environment jsdom
// Bulles des conversations en BD (P20-04) : jumeau TypeScript du rendu serveur — arrondi identique à PHP, coupure au
// mot (espace insécable respectée), taille d'après le texte, formes des 9 types, chaîne associée (queue sur la dernière
// bulle seulement), texte échappé, transcription lue (« Fifamè pense : … »). La parité HTML exacte avec Blade est
// vérifiée par parity.test.ts (cas bd-panel*) ; ici, le composant XmBdPanel rendu dans Vue.
import { afterEach, describe, expect, it } from 'vitest';
import { cleanup } from '@testing-library/vue';
import { renderXm, useFrench } from '@/test/helpers';
import { i18n } from '@/app/i18n';
import XmBdPanel from './XmBdPanel.vue';
import { boxPercent, chains, fmt, layout, lines, renderSvg, round1, transcript, words, type Bubble } from './bubbles';

afterEach(cleanup);

const bubble = (over: Partial<Bubble> = {}): Bubble => ({
    type: 'parole', personnage: 'afi', texte: 'Bonjour à toi.', position: { x: 10, y: 10 }, queue: { vers: 'personnage', x: 30, y: 80 }, ...over,
});

describe('géométrie', () => {
    it('arrondit au dixième comme PHP (demi vers l’extérieur, jamais « -0 »)', () => {
        expect(round1(1.25)).toBe(1.3);
        expect(round1(-1.25)).toBe(-1.3);
        expect(fmt(-0.04)).toBe('0');
        expect(fmt(12)).toBe('12');
        expect(fmt(12.34)).toBe('12.3');
    });

    it('coupe au mot, jamais à une espace insécable, et dimensionne d’après le texte', () => {
        expect(words('Pourquoi ? Non.')).toEqual(['Pourquoi ?', 'Non.']);
        const short = layout(bubble({ texte: 'Oui.' }));
        const long = layout(bubble({ texte: 'Je n’en ai pas envie… mais je ne veux pas le vexer, alors je dis peut-être.' }));
        expect(long.w).toBeGreaterThan(short.w);
        expect(long.w).toBeGreaterThan(long.h);
        expect(lines('parole', 'Oui.')).toHaveLength(1);
        expect(layout(bubble({ type: 'cri' })).font).toBeCloseTo(22 * 1.3);
        expect(layout(bubble({ type: 'chuchote' })).font).toBeCloseTo(22 * 0.85);
        expect(boxPercent(bubble()).x).toBeCloseTo(10);
    });

    it('regroupe les bulles associées en chaînes', () => {
        expect(chains([bubble(), bubble({ associee_a: 0 }), bubble(), bubble({ associee_a: 5 })])).toEqual([[0, 1], [2], [3]]);
    });
});

describe('rendu SVG', () => {
    it.each([
        ['pensee', /<circle /],
        ['cri', /stroke-width="9"/],
        ['chuchote', /stroke-dasharray="12 8"/],
        ['message', /A16 16 0 0 1/],
        ['voix_appareil', /xm-bdc-waves/],
        ['tremblee', / Q/],
    ])('dessine la forme du type %s', (type, pattern) => {
        expect(renderSvg([bubble({ type })])).toMatch(pattern);
    });

    it('ne met la queue que sur la dernière bulle d’une chaîne et échappe le texte', () => {
        const svg = renderSvg([bubble({ queue: null, texte: 'Non <b>merci</b>' }), bubble({ position: { x: 45, y: 10 }, associee_a: 0 })]);
        expect((svg.match(/ L/g) ?? []).length).toBe(4);
        expect(svg).toContain('&lt;b&gt;merci&lt;/b&gt;');
        expect(svg).not.toContain('<b>');
    });

    it('n’écrit aucun texte dans une bulle masquée', () => {
        expect(renderSvg([bubble({ type: 'message', texte: '', masque: true })])).not.toContain('<text');
    });
});

describe('transcription', () => {
    it('annonce le personnage et la nature de chaque bulle, la chaîne en une réplique', () => {
        useFrench();
        const t = (key: string, params?: Record<string, string>) => (params ? i18n.global.t(key, params) : i18n.global.t(key));
        const result = transcript([
            bubble({ type: 'cartouche', personnage: null, texte: 'Le soir.', queue: null }),
            bubble({ type: 'pensee', personnage: 'fifame', texte: 'Je dois lui dire…', queue: null }),
            bubble({ type: 'pensee', personnage: 'fifame', texte: 'mais elle va se fâcher.', associee_a: 1 }),
            bubble({ type: 'message', personnage: 'rodrigue', texte: 'Tu viens ?', queue: { vers: 'ecran', x: 50, y: 90 } }),
            bubble({ texte: 'Qui est là ?', queue: { vers: 'bord-droit' } }),
        ], { fifame: 'Fifamè', rodrigue: 'Rodrigue', afi: 'Afi' }, t).map((line) => line.text.replace(/ /g, ' '));
        expect(result).toEqual([
            'Narration : Le soir.',
            'Fifamè pense : Je dois lui dire… mais elle va se fâcher.',
            'Message reçu de Rodrigue : Tu viens ?',
            'Afi dit, hors de l’image : Qui est là ?',
        ]);
    });
});

describe('XmBdPanel', () => {
    it('rend une figure : repli sans image, SVG caché des lecteurs d’écran, transcription', () => {
        const { container } = renderXm(XmBdPanel, { props: { bubbles: [bubble()], names: { afi: 'Afi' }, alt: 'Afi dans la cour.', n: 2, count: 4, title: 'Test' } });
        expect(container.querySelector('figure.xm-bdc-panel')).not.toBeNull();
        expect(container.querySelector('.xm-bdc-fallback-text')?.textContent).toBe('Afi dans la cour.');
        expect(container.querySelector('svg')?.getAttribute('aria-hidden')).toBe('true');
        expect(container.querySelector('.xm-bdc-transcript li')?.textContent?.replace(/ /g, ' ')).toBe('Afi dit : Bonjour à toi.');
        expect(container.querySelector('.xm-bdc-counter')?.textContent).toBe('Case 2 sur 4');
    });

    it('affiche l’image quand elle existe', () => {
        const { container } = renderXm(XmBdPanel, { props: { image: '/assets/images/conversations-bd/x/case-1.webp', alt: 'Scène.', priority: true } });
        const img = container.querySelector('img.xm-bdc-img');
        expect(img?.getAttribute('alt')).toBe('Scène.');
        expect(img?.getAttribute('loading')).toBe('eager');
        expect(container.querySelector('.xm-bdc-fallback')).toBeNull();
    });
});
