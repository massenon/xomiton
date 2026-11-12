// Bulles des conversations en BD (P20-04) — jumeau TypeScript de App\Application\Content\ConversationsBd\BubbleGeometry,
// BubbleSvg et BubbleTranscript : mêmes constantes, mêmes opérations dans le même ordre, table trigonométrique figée et
// arrondi au dixième « demi vers l'extérieur », pour que XmBdPanel (éditeur des membres, lot B) dessine exactement le même
// SVG que le rendu serveur (test de parité `bd-panel`). Toute modification se fait des deux côtés.
// GRAMMAIRE_DES_BULLES.md : 9 types, queue vers le personnage / l'écran / un bord, bulles associées et superposées,
// positions en % d'une case de 1200 × 800 unités.

export type BubbleType = 'parole' | 'pensee' | 'cri' | 'tremblee' | 'chuchote' | 'joie' | 'message' | 'voix_appareil' | 'cartouche';
export type TailTarget = 'personnage' | 'ecran' | 'bord-gauche' | 'bord-droit' | 'bord-haut' | 'bord-bas';

export interface Bubble {
    type: BubbleType | string;
    personnage?: string | null;
    texte?: string | null;
    emphase?: string[] | null;
    position?: { x?: number | null; y?: number | null } | null;
    queue?: { vers?: string | null; x?: number | null; y?: number | null } | null;
    associee_a?: number | null;
    superpose?: boolean | null;
    masque?: boolean | null;
}

export interface Word { text: string; bold: boolean }

export interface Layout {
    type: BubbleType;
    font: number;
    lines: Word[][];
    textW: number;
    textH: number;
    a: number;
    b: number;
    w: number;
    h: number;
    x: number;
    y: number;
    cx: number;
    cy: number;
    outer: number;
    masked: boolean;
}

export const WIDTH = 1200;
export const HEIGHT = 800;
export const BASE_FONT = 22;
export const LINE_HEIGHT = 1.25;
export const INK = '#1B1630';
export const PAPER = '#FFFFFF';
export const CAPTION_PAPER = '#FBF7FA';
export const MASKED_PAPER = '#E4E1E8';
export const MASKED_BAR = '#B9B4C2';
export const FONT_TEXT = "Arial, 'Liberation Sans', Helvetica, sans-serif";
export const FONT_STRONG = "Montserrat, Arial, 'Liberation Sans', sans-serif";
export const TYPES: BubbleType[] = ['parole', 'pensee', 'cri', 'tremblee', 'chuchote', 'joie', 'message', 'voix_appareil', 'cartouche'];

const COS = [1, 0.9659, 0.866, 0.7071, 0.5, 0.2588, 0, -0.2588, -0.5, -0.7071, -0.866, -0.9659, -1, -0.9659, -0.866, -0.7071, -0.5, -0.2588, 0, 0.2588, 0.5, 0.7071, 0.866, 0.9659];
const SHAKE = [1.02, 0.95, 1.03, 0.96, 1.01, 0.94];

const cos = (k: number): number => COS[k % 24];
const sin = (k: number): number => COS[(k + 18) % 24];

export const asType = (value: unknown): BubbleType => (TYPES.includes(value as BubbleType) ? (value as BubbleType) : 'parole');

/** Arrondi au dixième, demi vers l'extérieur (identique à PHP). */
export const round1 = (value: number): number => {
    const sign = value < 0 ? -1 : 1;
    return (sign * Math.floor(Math.abs(value) * 10 + 0.5)) / 10;
};

/** Nombre formaté pour un attribut SVG. */
export const fmt = (value: number): string => {
    const n = round1(value);
    return n === 0 ? '0' : String(n);
};

const isRect = (type: BubbleType): boolean => type === 'message' || type === 'voix_appareil' || type === 'cartouche';

const fontScale = (type: BubbleType): number => (type === 'cri' ? 1.3 : type === 'tremblee' || type === 'chuchote' ? 0.85 : 1.0);

const charFactor = (type: BubbleType): number => (type === 'cri' || type === 'cartouche' ? 0.64 : 0.53);

const outer = (type: BubbleType): number => {
    if (type === 'pensee' || type === 'joie') return 1.08;
    if (type === 'cri') return 1.2;
    if (type === 'parole' || type === 'chuchote') return 1.02;
    if (type === 'tremblee') return 1.03;
    return 1.0;
};

const strokeWidth = (type: BubbleType): number => (type === 'cri' ? 4.5 : type === 'cartouche' ? 2 : 2.5);

/** Mots du texte, coupés aux seules espaces ASCII (une espace insécable ne coupe jamais une ligne). */
export const words = (text: string): string[] => String(text ?? '').split(/[ \t\n\r\f\v]+/).filter((w) => w !== '');

const length = (text: string): number => [...text].length;

const bare = (word: string): string => word.replace(/^[\p{P}\p{S}\p{Z}]+|[\p{P}\p{S}\p{Z}]+$/gu, '').toLowerCase();

export const boldIndexes = (list: string[], emphasis: string[]): Set<number> => {
    const plain = list.map(bare);
    const bold = new Set<number>();
    emphasis.forEach((phrase) => {
        const needle = words(phrase).map(bare);
        const size = needle.length;
        if (size === 0) return;
        for (let i = 0; i + size <= plain.length; i++) {
            if (needle.every((w, j) => plain[i + j] === w)) {
                for (let j = i; j < i + size; j++) bold.add(j);
                break;
            }
        }
    });
    return bold;
};

export const maxChars = (type: BubbleType, len: number): number => (type === 'cartouche'
    ? Math.max(16, Math.min(48, Math.floor(4.2 * Math.sqrt(len) + 0.5)))
    : Math.max(12, Math.min(30, Math.floor(3 * Math.sqrt(len) + 0.5))));

export const lines = (type: BubbleType, text: string, emphasis: string[] = []): Word[][] => {
    const list = words(text);
    if (list.length === 0) return [];
    const bold = boldIndexes(list, emphasis);
    const max = maxChars(type, length(list.join(' ')));
    const out: Word[][] = [];
    let current: Word[] = [];
    let currentLength = 0;
    list.forEach((word, index) => {
        const wordLength = length(word);
        let candidate = current.length === 0 ? wordLength : currentLength + 1 + wordLength;
        if (current.length > 0 && candidate > max) {
            out.push(current);
            current = [];
            candidate = wordLength;
        }
        current.push({ text: word, bold: bold.has(index) });
        currentLength = candidate;
    });
    out.push(current);
    return out;
};

const lineLength = (line: Word[]): number => length(line.map((w) => w.text).join(' '));

export const layout = (bubble: Bubble): Layout => {
    const type = asType(bubble.type);
    const masked = Boolean(bubble.masque);
    const font = BASE_FONT * fontScale(type);
    const emphasis = (bubble.emphase ?? []).filter((e): e is string => typeof e === 'string');
    const textLines = masked ? [] : lines(type, String(bubble.texte ?? ''), emphasis);
    let longest = 0;
    textLines.forEach((line) => { longest = Math.max(longest, lineLength(line)); });
    let textW = longest * font * charFactor(type);
    let textH = textLines.length * font * LINE_HEIGHT;
    if (masked || textLines.length === 0) {
        textW = 180;
        textH = font * LINE_HEIGHT * 2;
    }
    const padX = 0.6 * font;
    const padY = 0.45 * font;
    const o = outer(type);
    let a: number;
    let b: number;
    if (isRect(type)) {
        a = textW / 2 + padX;
        b = textH / 2 + padY;
    } else {
        a = textW / 2 * 1.3 + padX;
        b = textH / 2 * 1.3 + padY;
        if (type === 'pensee' || type === 'joie') {
            a = a * 1.06;
            b = b * 1.06;
        }
    }
    const w = 2 * a * o;
    const h = 2 * b * o;
    const x = Number(bubble.position?.x ?? 0) * WIDTH / 100;
    const y = Number(bubble.position?.y ?? 0) * HEIGHT / 100;
    return { type, font, lines: textLines, textW, textH, a, b, w, h, x, y, cx: x + w / 2, cy: y + h / 2, outer: o, masked };
};

/** Encombrement en % de la case. */
export const boxPercent = (bubble: Bubble) => {
    const l = layout(bubble);
    return { x: l.x / WIDTH * 100, y: l.y / HEIGHT * 100, w: l.w / WIDTH * 100, h: l.h / HEIGHT * 100 };
};

const isNumeric = (value: unknown): value is number | string => value !== null && value !== undefined && value !== '' && !Number.isNaN(Number(value));

export const tip = (bubble: Bubble, l: Layout): { x: number; y: number } | null => {
    const tail = bubble.queue;
    if (bubble.type === 'cartouche' || !tail || typeof tail !== 'object') return null;
    const target = tail.vers;
    const hasX = isNumeric(tail.x);
    const hasY = isNumeric(tail.y);
    const x = hasX ? Number(tail.x) * WIDTH / 100 : l.cx;
    const y = hasY ? Number(tail.y) * HEIGHT / 100 : l.cy;
    switch (target) {
        case 'bord-gauche': return { x: hasX ? x : 0, y };
        case 'bord-droit': return { x: hasX ? x : WIDTH, y };
        case 'bord-haut': return { x, y: hasY ? y : 0 };
        case 'bord-bas': return { x, y: hasY ? y : HEIGHT };
        case 'personnage':
        case 'ecran': return hasX && hasY ? { x, y } : null;
        default: return null;
    }
};

const edgeDistance = (l: Layout, ux: number, uy: number): number => {
    if (isRect(l.type)) {
        const sx = Math.abs(ux) > 0.000001 ? l.a / Math.abs(ux) : 1000000.0;
        const sy = Math.abs(uy) > 0.000001 ? l.b / Math.abs(uy) : 1000000.0;
        return Math.min(sx, sy);
    }
    return 1 / Math.sqrt((ux / l.a) * (ux / l.a) + (uy / l.b) * (uy / l.b));
};

const roundedRect = (x: number, y: number, w: number, h: number, radius: number): string => {
    const r = Math.min(radius, w / 2, h / 2);
    return `M${fmt(x + r)} ${fmt(y)}`
        + ` H${fmt(x + w - r)} A${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(x + w)} ${fmt(y + r)}`
        + ` V${fmt(y + h - r)} A${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(x + w - r)} ${fmt(y + h)}`
        + ` H${fmt(x + r)} A${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(x)} ${fmt(y + h - r)}`
        + ` V${fmt(y + r)} A${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(x + r)} ${fmt(y)} Z`;
};

export const bodyPath = (l: Layout): string => {
    const { cx, cy, a, b } = l;
    switch (l.type) {
        case 'pensee':
        case 'joie': {
            const n = a + b > 260 ? 12 : 8;
            const step = 24 / n;
            const points: [number, number][] = [];
            for (let k = 0; k < n; k++) points.push([cx + a * 0.94 * cos(k * step), cy + b * 0.94 * sin(k * step)]);
            let d = `M${fmt(points[0][0])} ${fmt(points[0][1])}`;
            for (let k = 0; k < n; k++) {
                const p = points[k];
                const q = points[(k + 1) % n];
                const chord = Math.sqrt((q[0] - p[0]) * (q[0] - p[0]) + (q[1] - p[1]) * (q[1] - p[1]));
                const r = chord * 0.6;
                d += ` A${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(q[0])} ${fmt(q[1])}`;
            }
            return `${d} Z`;
        }
        case 'cri': {
            let d = '';
            for (let k = 0; k < 24; k++) {
                const factor = k % 2 === 0 ? 1.2 : 0.97;
                d += `${k === 0 ? 'M' : ' L'}${fmt(cx + a * factor * cos(k))} ${fmt(cy + b * factor * sin(k))}`;
            }
            return `${d} Z`;
        }
        case 'tremblee': {
            const points: [number, number][] = [];
            for (let k = 0; k < 24; k++) {
                const factor = SHAKE[k % 6];
                points.push([cx + a * factor * cos(k), cy + b * factor * sin(k)]);
            }
            const mid = (p: [number, number], q: [number, number]): [number, number] => [(p[0] + q[0]) / 2, (p[1] + q[1]) / 2];
            const start = mid(points[0], points[1]);
            let d = `M${fmt(start[0])} ${fmt(start[1])}`;
            for (let k = 1; k <= 24; k++) {
                const control = points[k % 24];
                const end = mid(control, points[(k + 1) % 24]);
                d += ` Q${fmt(control[0])} ${fmt(control[1])} ${fmt(end[0])} ${fmt(end[1])}`;
            }
            return `${d} Z`;
        }
        case 'message': return roundedRect(l.x, l.y, l.w, l.h, 16);
        case 'voix_appareil': return roundedRect(l.x, l.y, l.w, l.h, 6);
        case 'cartouche': return `M${fmt(l.x)} ${fmt(l.y)} H${fmt(l.x + l.w)} V${fmt(l.y + l.h)} H${fmt(l.x)} Z`;
        default:
            return `M${fmt(cx - a)} ${fmt(cy)} A${fmt(a)} ${fmt(b)} 0 1 0 ${fmt(cx + a)} ${fmt(cy)} A${fmt(a)} ${fmt(b)} 0 1 0 ${fmt(cx - a)} ${fmt(cy)} Z`;
    }
};

export const tail = (l: Layout, point: { x: number; y: number }): { path: string | null; circles: { cx: number; cy: number; r: number }[] } => {
    const none = { path: null, circles: [] };
    const dx = point.x - l.cx;
    const dy = point.y - l.cy;
    const dist = Math.sqrt(dx * dx + dy * dy);
    if (dist < 0.000001) return none;
    const ux = dx / dist;
    const uy = dy / dist;
    const s = edgeDistance(l, ux, uy);
    if (dist <= s * 1.02) return none;
    if (l.type === 'pensee') {
        const ex = l.cx + ux * s * 1.04;
        const ey = l.cy + uy * s * 1.04;
        const r0 = Math.max(6, Math.min(l.a, l.b) * 0.16);
        return {
            path: null,
            circles: [[0.3, 1.0], [0.6, 0.66], [0.88, 0.4]].map(([t, k]) => ({ cx: ex + (point.x - ex) * t, cy: ey + (point.y - ey) * t, r: r0 * k })),
        };
    }
    const half = Math.min(24, Math.min(l.a, l.b) * 0.3);
    const bx = l.cx + ux * s * 0.75;
    const by = l.cy + uy * s * 0.75;
    const px = -uy * half;
    const py = ux * half;
    return { path: `M${fmt(bx + px)} ${fmt(by + py)} L${fmt(point.x)} ${fmt(point.y)} L${fmt(bx - px)} ${fmt(by - py)} Z`, circles: [] };
};

export const wavesPath = (l: Layout): string => {
    const d: string[] = [];
    [1, 2].forEach((i) => {
        const right = l.x + l.w + 4 + 7 * i;
        const left = l.x - 4 - 7 * i;
        const half = 9 * i;
        const r = 12 * i;
        d.push(`M${fmt(right)} ${fmt(l.cy - half)} A${fmt(r)} ${fmt(r)} 0 0 1 ${fmt(right)} ${fmt(l.cy + half)}`);
        d.push(`M${fmt(left)} ${fmt(l.cy - half)} A${fmt(r)} ${fmt(r)} 0 0 0 ${fmt(left)} ${fmt(l.cy + half)}`);
    });
    return d.join(' ');
};

/** Chaînes de bulles associées, dans l'ordre de dessin (rangs des bulles). */
export const chains = (bubbles: Bubble[]): number[][] => {
    const chainOf: number[] = [];
    const out: number[][] = [];
    bubbles.forEach((bubble, index) => {
        const link = bubble.associee_a;
        if (Number.isInteger(link) && (link as number) >= 0 && (link as number) < index && chainOf[link as number] !== undefined) {
            const chain = chainOf[link as number];
            out[chain].push(index);
            chainOf[index] = chain;
            return;
        }
        chainOf[index] = out.length;
        out.push([index]);
    });
    return out;
};

export const escapeHtml = (text: string): string => text
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const segments = (line: Word[], strong: boolean): string => {
    const hasBold = !strong && line.some((w) => w.bold);
    if (!hasBold) return escapeHtml(line.map((w) => w.text).join(' '));
    const groups: { bold: boolean; words: string[] }[] = [];
    line.forEach((word) => {
        const last = groups[groups.length - 1];
        if (last && last.bold === word.bold) last.words.push(word.text);
        else groups.push({ bold: word.bold, words: [word.text] });
    });
    return groups.map((group, i) => {
        const text = group.words.join(' ') + (i < groups.length - 1 ? ' ' : '');
        return `<tspan${group.bold ? ' font-weight="700"' : ''}>${escapeHtml(text)}</tspan>`;
    }).join('');
};

const text = (l: Layout): string => {
    if (l.lines.length === 0) return '';
    const strong = l.type === 'cri' || l.type === 'cartouche';
    let attributes = `x="${fmt(l.cx)}" font-size="${fmt(l.font)}" font-family="${escapeHtml(strong ? FONT_STRONG : FONT_TEXT)}" text-anchor="middle" fill="${INK}"`;
    if (strong) attributes += ' font-weight="700"';
    if (l.type === 'pensee' || l.type === 'voix_appareil') attributes += ' font-style="italic"';
    const lineHeight = l.font * LINE_HEIGHT;
    const top = l.cy - l.textH / 2;
    let out = `<text ${attributes}>`;
    l.lines.forEach((line, i) => {
        const baseline = top + (i + 0.5) * lineHeight + 0.35 * l.font;
        out += `<tspan x="${fmt(l.cx)}" y="${fmt(baseline)}">${segments(line, strong)}</tspan>`;
    });
    return `${out}</text>`;
};

/** SVG des bulles d'une case (même chaîne que BubbleSvg::render). */
export const renderSvg = (bubbles: Bubble[]): string => {
    let out = `<svg class="xm-bdc-svg" viewBox="0 0 ${WIDTH} ${HEIGHT}" preserveAspectRatio="xMidYMid meet" overflow="visible" aria-hidden="true" focusable="false">`;
    chains(bubbles).forEach((members) => {
        const layouts = new Map<number, Layout>();
        members.forEach((index) => layouts.set(index, layout(bubbles[index])));
        const first = layouts.get(members[0]) as Layout;
        const isThought = first.type === 'pensee';
        const last = members[members.length - 1];
        let stroke = '';
        let paper = '';
        let decor = '';
        let texts = '';
        for (let i = 1; i < members.length; i++) {
            const p = layouts.get(members[i - 1]) as Layout;
            const q = layouts.get(members[i]) as Layout;
            const coords = `x1="${fmt(p.cx)}" y1="${fmt(p.cy)}" x2="${fmt(q.cx)}" y2="${fmt(q.cy)}"`;
            stroke += `<line ${coords} stroke-width="${fmt(18 + 2 * 2.5)}" stroke-linecap="round"/>`;
            paper += `<line ${coords} stroke="${PAPER}" stroke-width="18" stroke-linecap="round"/>`;
        }
        members.forEach((index) => {
            const bubble = bubbles[index];
            const l = layouts.get(index) as Layout;
            const { type } = l;
            const width = fmt(2 * strokeWidth(type));
            const dash = type === 'chuchote' ? ' stroke-dasharray="12 8"' : '';
            const fill = l.masked ? MASKED_PAPER : type === 'cartouche' ? CAPTION_PAPER : PAPER;
            const body = bodyPath(l);
            stroke += `<path d="${body}" stroke-width="${width}"${dash}/>`;
            paper += `<path d="${body}" fill="${fill}"/>`;
            const point = index === last ? tip(bubble, l) : null;
            if (point !== null) {
                const t = tail(l, point);
                if (t.path !== null) {
                    stroke += `<path d="${t.path}" stroke-width="${width}"${dash}/>`;
                    paper += `<path d="${t.path}" fill="${fill}"/>`;
                }
                t.circles.forEach((c) => {
                    const circle = `cx="${fmt(c.cx)}" cy="${fmt(c.cy)}" r="${fmt(c.r)}"`;
                    stroke += `<circle ${circle} stroke-width="${width}"/>`;
                    paper += `<circle ${circle} fill="${fill}"/>`;
                });
            }
            if (type === 'voix_appareil') {
                decor += `<path class="xm-bdc-waves" d="${wavesPath(l)}" fill="none" stroke="${INK}" stroke-width="2.5" stroke-linecap="round"/>`;
            }
            if (l.masked) {
                [[0.8, -0.32], [0.62, 0.32]].forEach(([ratio, offset]) => {
                    const barW = l.textW * ratio;
                    paper += `<rect x="${fmt(l.cx - barW / 2)}" y="${fmt(l.cy + l.textH * offset - 6)}" width="${fmt(barW)}" height="12" rx="6" fill="${MASKED_BAR}"/>`;
                });
            }
            texts += text(l);
        });
        const klass = `xm-bdc-chain${isThought ? ' xm-bdc-thought' : ''}`;
        out += `<g class="${klass}" data-bubble-type="${first.type}">`
            + `<g fill="none" stroke="${INK}" stroke-linejoin="round">${stroke}</g>`
            + `<g stroke="none">${paper}</g>`
            + `${decor}${texts}</g>`;
    });
    return `${out}</svg>`;
};

export type Translate = (key: string, params?: Record<string, string>) => string;

/** Transcription d'une case (même résultat que BubbleTranscript::lines). */
export const transcript = (bubbles: Bubble[], names: Record<string, string>, t: Translate): { text: string; thought: boolean }[] => chains(bubbles).map((members) => {
    const first = bubbles[members[0]];
    const last = bubbles[members[members.length - 1]];
    const type = asType(first.type);
    const joined = members.map((index) => words(String(bubbles[index].texte ?? '')).join(' ')).filter((s) => s !== '').join(' ');
    let line: string;
    if (first.masque) {
        line = t('conversations_bd.sr.masked');
    } else if (type === 'cartouche') {
        line = t('conversations_bd.sr.narration', { text: joined });
    } else {
        const key = typeof first.personnage === 'string' ? first.personnage : '';
        const name = names[key] ?? (key !== '' ? key : t('conversations_bd.sr.someone'));
        const target = last.queue?.vers ?? '';
        if (type === 'message') {
            line = t(target === 'personnage' ? 'conversations_bd.sr.message_sent' : 'conversations_bd.sr.message_received', { name, text: joined });
        } else if (type === 'voix_appareil') {
            line = t('conversations_bd.sr.device', { name, text: joined });
        } else {
            const verb = t(`conversations_bd.sr.verbs.${type}`);
            line = t(String(target).startsWith('bord-') ? 'conversations_bd.sr.line_offscreen' : 'conversations_bd.sr.line', { name, verb, text: joined });
        }
    }
    return { text: line, thought: type === 'pensee' };
});
