// Export PNG des conversations en BD (P20-09, PRD P0-8), **dans le navigateur** : aucun Chrome sans tête ni Imagick sur
// l'hébergement mutualisé, aucun appel au serveur. Source = les cases déjà affichées dans la page (`x-bd.panel` côté
// Blade, `XmBdPanel` côté Vue : même HTML) : illustration (<img class="xm-bdc-img">, même origine) puis bulles (le SVG
// de la case, sérialisé en image SVG autonome) dessinées dans un <canvas> → PNG.
//  - une image par case, 1080 px de large (3:2, 1080 × 720) ; si le PNG dépasse 400 Ko (illustration très détaillée),
//    la case est redessinée un peu plus petite (pas de 10 %, 720 px au moins) jusqu'à passer sous la limite ;
//  - une image « bande » de toute la conversation, 1080 px de large : titre, cases les unes sous les autres, source.
// Polices : celles du système, comme le SVG de la page (Arial / Liberation Sans ; Montserrat si installée, sinon Arial
// gras) ; on attend `document.fonts.ready` avant de dessiner. Une case sans illustration garde le repli rose pâle.
// Échec (navigateur ancien, image refusée) : la promesse est rejetée, la page propose alors le PDF.

export const PNG_WIDTH = 1080;
export const PNG_MAX_BYTES = 400 * 1024;
const MIN_WIDTH = 720;
const RATIO = 2 / 3;
const FALLBACK_BG = '#FBEFF5';
const INK = '#1B1630';

export interface PanelSource {
    image: string | null;
    svg: string;
    alt: string;
}

/** Cases d'une zone de la page (ordre de lecture). */
export function panelsIn(root: ParentNode): PanelSource[] {
    return Array.from(root.querySelectorAll<HTMLElement>('[data-xm-bdc-panel]')).map((figure) => {
        const img = figure.querySelector<HTMLImageElement>('img.xm-bdc-img');
        const svg = figure.querySelector('svg.xm-bdc-svg');
        const fallback = figure.querySelector('.xm-bdc-fallback-text');
        return {
            image: img ? img.currentSrc || img.src : null,
            svg: svg ? svg.outerHTML : '',
            alt: (img?.alt || fallback?.textContent || '').trim(),
        };
    });
}

/** SVG des bulles rendu autonome (espace de noms, taille), prêt pour une image. */
export function standaloneSvg(svg: string): string {
    let out = svg.replace(/^<svg\b/, '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800"');
    // Les pensées masquées par une feuille de style (mode séance) restent visibles dans l'export.
    out = out.replace(/\sstyle="[^"]*"/g, '');
    return out;
}

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error('image'));
        img.src = src;
    });
}

async function svgImage(svg: string): Promise<HTMLImageElement | null> {
    if (!svg) return null;
    const url = URL.createObjectURL(new Blob([standaloneSvg(svg)], { type: 'image/svg+xml;charset=utf-8' }));
    try {
        return await loadImage(url);
    } finally {
        // L'image décodée reste utilisable après la révocation.
        setTimeout(() => URL.revokeObjectURL(url), 0);
    }
}

function wrap(ctx: CanvasRenderingContext2D, text: string, maxWidth: number): string[] {
    const words = text.split(/\s+/).filter(Boolean);
    const lines: string[] = [];
    let line = '';
    words.forEach((word) => {
        const candidate = line ? `${line} ${word}` : word;
        if (line && ctx.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    });
    if (line) lines.push(line);
    return lines;
}

/** Dessine une case (illustration ou repli, puis bulles) dans le contexte, à (0, y), largeur `width`. */
export async function drawPanel(ctx: CanvasRenderingContext2D, panel: PanelSource, y: number, width: number): Promise<void> {
    const height = Math.round(width * RATIO);
    ctx.save();
    ctx.fillStyle = FALLBACK_BG;
    ctx.fillRect(0, y, width, height);
    let drawn = false;
    if (panel.image) {
        try {
            const img = await loadImage(panel.image);
            ctx.drawImage(img, 0, y, width, height);
            drawn = true;
        } catch {
            drawn = false;
        }
    }
    if (!drawn && panel.alt) {
        ctx.fillStyle = '#55556A';
        ctx.font = `italic ${Math.round(width / 45)}px Arial, "Liberation Sans", sans-serif`;
        const lines = wrap(ctx, panel.alt, width * 0.8);
        lines.slice(0, 4).forEach((line, i) => ctx.fillText(line, width * 0.1, y + height * 0.72 + i * width / 34));
    }
    const bubbles = await svgImage(panel.svg);
    if (bubbles) ctx.drawImage(bubbles, 0, y, width, height);
    ctx.strokeStyle = INK;
    ctx.lineWidth = 2;
    ctx.strokeRect(1, y + 1, width - 2, height - 2);
    ctx.restore();
}

export function canvasToBlob(canvas: HTMLCanvasElement): Promise<Blob> {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('png'))), 'image/png');
    });
}

async function fontsReady(): Promise<void> {
    try {
        await (document as Document & { fonts?: { ready: Promise<unknown> } }).fonts?.ready;
    } catch {
        // Polices du système : rien à attendre.
    }
}

/** PNG d'une case : 1080 px de large, réduit par pas de 10 % tant qu'il dépasse 400 Ko (720 px au moins). */
export async function panelPng(panel: PanelSource, maxBytes = PNG_MAX_BYTES): Promise<Blob> {
    await fontsReady();
    let width = PNG_WIDTH;
    let blob: Blob | null = null;
    for (;;) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = Math.round(width * RATIO);
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error('canvas');
        await drawPanel(ctx, panel, 0, width);
        blob = await canvasToBlob(canvas);
        if (blob.size <= maxBytes || width <= MIN_WIDTH) return blob;
        width = Math.max(MIN_WIDTH, Math.round(width * 0.9));
    }
}

/** PNG « bande » : titre, cases les unes sous les autres, mention de la source ; 1080 px de large. */
export async function stripPng(panels: PanelSource[], title: string, source: string): Promise<Blob> {
    await fontsReady();
    const width = PNG_WIDTH;
    const gap = 24;
    const pad = 40;
    const panelH = Math.round(width * RATIO);
    const measure = document.createElement('canvas').getContext('2d');
    if (!measure) throw new Error('canvas');
    measure.font = 'bold 44px Montserrat, Arial, "Liberation Sans", sans-serif';
    const titleLines = wrap(measure, title, width - 2 * pad);
    const head = pad + titleLines.length * 54 + 16;
    const foot = 72;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = head + panels.length * (panelH + gap) + foot;
    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('canvas');
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#0433C3';
    ctx.font = measure.font;
    titleLines.forEach((line, i) => ctx.fillText(line, pad, pad + 44 + i * 54));
    for (let i = 0; i < panels.length; i++) {
        await drawPanel(ctx, panels[i], head + i * (panelH + gap), width);
    }
    ctx.fillStyle = '#55556A';
    ctx.font = '26px Arial, "Liberation Sans", sans-serif';
    ctx.fillText(source, pad, canvas.height - 30);
    return canvasToBlob(canvas);
}

/** Téléchargement d'un fichier produit dans la page. */
export function download(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export function slug(text: string): string {
    return text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'conversation';
}

/** Exporte toutes les cases (une image chacune) ou la bande, avec des noms de fichiers lisibles. */
export async function exportPng(root: ParentNode, mode: 'panels' | 'strip', title: string, source: string): Promise<number> {
    const panels = panelsIn(root);
    if (panels.length === 0) throw new Error('empty');
    const base = `conversation-bd-${slug(title)}`;
    if (mode === 'strip') {
        download(await stripPng(panels, title, source), `${base}-bande.png`);
        return 1;
    }
    for (let i = 0; i < panels.length; i++) {
        download(await panelPng(panels[i]), `${base}-case-${i + 1}.png`);
    }
    return panels.length;
}
