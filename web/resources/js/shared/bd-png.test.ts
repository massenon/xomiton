// @vitest-environment jsdom
// P20-09 : export PNG des conversations en BD dans le navigateur — lecture des cases de la page (même HTML Blade et Vue),
// SVG des bulles rendu autonome, une image par case de 1080 px réduite tant qu'elle dépasse 400 Ko, image « bande »,
// noms de fichiers lisibles. Le canvas de jsdom est simulé.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { exportPng, panelPng, panelsIn, PNG_MAX_BYTES, slug, standaloneSvg } from './bd-png';

const FIGURE = (n: number, image = true) => `<figure class="xm-bdc-panel" data-xm-bdc-panel><div class="xm-bdc-frame"><div class="xm-bdc-art">${image
    ? `<img class="xm-bdc-img" src="/assets/case-${n}.webp" alt="Scène ${n}">`
    : `<div class="xm-bdc-fallback"><p class="xm-bdc-fallback-text">Scène ${n} sans image</p></div>`}</div><div class="xm-bdc-overlay"><svg class="xm-bdc-svg" viewBox="0 0 1200 800" aria-hidden="true"><g class="xm-bdc-chain"><text>Bonjour ${n}</text></g></svg></div></div></figure>`;

/** Canvas simulé : la taille du PNG dépend de la largeur (assez grande à 1080 px pour forcer une réduction). */
function stubCanvas(sizeFor: (width: number) => number) {
    const widths: number[] = [];
    const ctx = { save: vi.fn(), restore: vi.fn(), fillRect: vi.fn(), drawImage: vi.fn(), strokeRect: vi.fn(), fillText: vi.fn(), measureText: (text: string) => ({ width: text.length * 20 }), set fillStyle(_v: string) {}, set font(_v: string) {}, get font() { return '10px Arial'; }, set strokeStyle(_v: string) {}, set lineWidth(_v: number) {} };
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(() => ctx as unknown as CanvasRenderingContext2D);
    vi.spyOn(HTMLCanvasElement.prototype, 'toBlob').mockImplementation(function (this: HTMLCanvasElement, callback: BlobCallback) {
        widths.push(this.width);
        callback(new Blob([new Uint8Array(sizeFor(this.width))], { type: 'image/png' }));
    });
    // Images : chargées aussitôt.
    vi.stubGlobal('Image', class {
        onload: (() => void) | null = null;
        onerror: (() => void) | null = null;
        decoding = 'auto';
        set src(_value: string) { setTimeout(() => this.onload?.(), 0); }
    });
    URL.createObjectURL = vi.fn(() => 'blob:x');
    URL.revokeObjectURL = vi.fn();
    return { widths, ctx };
}

afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('Export PNG', () => {
    it('lit les cases de la page dans l’ordre, avec illustration ou repli', () => {
        document.body.innerHTML = `<section id="conversation">${FIGURE(1)}${FIGURE(2, false)}</section>`;
        const panels = panelsIn(document.getElementById('conversation') as HTMLElement);
        expect(panels).toHaveLength(2);
        expect(panels[0].image).toContain('/assets/case-1.webp');
        expect(panels[1].image).toBeNull();
        expect(panels[1].alt).toBe('Scène 2 sans image');
        expect(panels[0].svg).toContain('Bonjour 1');
    });

    it('rend le SVG des bulles autonome (espace de noms, taille)', () => {
        const svg = standaloneSvg('<svg class="xm-bdc-svg" viewBox="0 0 1200 800" style="display:none"><g></g></svg>');
        expect(svg).toMatch(/^<svg xmlns="http:\/\/www\.w3\.org\/2000\/svg" width="1200" height="800"/);
        expect(svg).not.toContain('display:none');
    });

    it('produit une case de 1080 px, réduite par pas tant qu’elle dépasse 400 Ko', async () => {
        const { widths } = stubCanvas((width) => (width >= 1000 ? PNG_MAX_BYTES + 1 : 1000));
        const blob = await panelPng({ image: '/a.webp', svg: '<svg></svg>', alt: '' });
        expect(widths[0]).toBe(1080);
        expect(widths.at(-1)).toBeLessThan(1000);
        expect(blob.size).toBeLessThanOrEqual(PNG_MAX_BYTES);
    });

    it('télécharge une image par case puis une bande, avec des noms lisibles', async () => {
        stubCanvas(() => 1000);
        document.body.innerHTML = `<section id="conversation">${FIGURE(1)}${FIGURE(2)}</section>`;
        const names: string[] = [];
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) { names.push(this.download); });

        expect(await exportPng(document, 'panels', 'Non, c’est non', 'Source')).toBe(2);
        expect(await exportPng(document, 'strip', 'Non, c’est non', 'Source')).toBe(1);
        expect(names).toEqual(['conversation-bd-non-c-est-non-case-1.png', 'conversation-bd-non-c-est-non-case-2.png', 'conversation-bd-non-c-est-non-bande.png']);
        expect(slug('Élan à Porto-Novo')).toBe('elan-a-porto-novo');
    });

    it('échoue proprement sans case', async () => {
        await expect(exportPng(document, 'panels', 'Vide', 'Source')).rejects.toThrow();
    });
});
