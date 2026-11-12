// Contrôles de la grammaire des bulles dans l'éditeur des membres (P20-07) — jumeau TypeScript de
// App\Application\Content\ConversationsBd\ConversationGrammar : mêmes règles, mêmes codes, même ordre, pour afficher
// en direct ce que le serveur vérifiera à l'enregistrement et à la proposition (il reste seul juge). Toute modification
// se fait des deux côtés. Encombrement des bulles : boxPercent() de bubbles.ts (même calcul que BubbleGeometry).
import { boxPercent, TYPES, words, type Bubble, type BubbleType } from './bubbles';

export const MIN_PANELS = 3;
export const MAX_PANELS = 8;
export const MAX_WORDS = 20;
export const MAX_BUBBLES = 4;
export const SHOUT_OVERFLOW = 4.0;
const EPSILON = 0.05;
const TAILS = ['personnage', 'ecran', 'bord-gauche', 'bord-droit', 'bord-haut', 'bord-bas'];

export interface Issue {
    level: 'error' | 'warning';
    code: string;
    panel: number | null;
    bubble: number | null;
    params: Record<string, number>;
}

interface Box { x: number; y: number; w: number; h: number }

const issue = (level: Issue['level'], code: string, panel: number | null, bubble: number | null, params: Record<string, number> = {}): Issue =>
    ({ level, code, panel, bubble, params });

const isNumeric = (value: unknown): boolean => value !== null && value !== undefined && value !== '' && !Number.isNaN(Number(value));

const intersects = (a: Box, b: Box): boolean => a.x + EPSILON < b.x + b.w && b.x + EPSILON < a.x + a.w
    && a.y + EPSILON < b.y + b.h && b.y + EPSILON < a.y + a.h;

function checkTail(bubble: Bubble, type: BubbleType, box: Box, bubbles: Bubble[], index: number, panel: number): Issue[] {
    const n = index + 1;
    const tail = bubble.queue;
    if (type === 'cartouche') return tail && typeof tail === 'object' ? [issue('error', 'caption_tail', panel, n)] : [];
    if (!tail || typeof tail !== 'object') {
        if (bubbles.some((other) => other.associee_a === index)) return [];
        return [issue('error', 'tail_missing', panel, n)];
    }
    const target = String(tail.vers ?? '');
    if (!TAILS.includes(target)) return [issue('error', 'tail_target', panel, n)];
    const edge = target.startsWith('bord-');
    const hasX = isNumeric(tail.x);
    const hasY = isNumeric(tail.y);
    if (!edge && (!hasX || !hasY)) return [issue('error', 'tail_point', panel, n)];
    const x = hasX ? Number(tail.x) : null;
    const y = hasY ? Number(tail.y) : null;
    if ((x !== null && (x < 0 || x > 100)) || (y !== null && (y < 0 || y > 100))) return [issue('error', 'tail_outside', panel, n)];
    if (!edge && x !== null && y !== null && x > box.x && x < box.x + box.w && y > box.y && y < box.y + box.h) {
        return [issue('error', 'tail_inside', panel, n)];
    }
    return [];
}

/** Contrôles d'une case (rang 1…n), comme ConversationGrammar::checkPanel. */
export function checkPanel(bubbles: Bubble[], panel: number): Issue[] {
    const issues: Issue[] = [];
    const boxes: (Box | undefined)[] = [];
    let speaking = 0;
    let overlapping = 0;
    bubbles.forEach((bubble, i) => {
        const n = i + 1;
        const type = TYPES.includes(bubble.type as BubbleType) ? bubble.type as BubbleType : null;
        if (type === null) {
            issues.push(issue('error', 'type', panel, n));
            return;
        }
        if (type !== 'cartouche') speaking++;
        if (bubble.superpose) overlapping++;
        const count = words(String(bubble.texte ?? '')).length;
        if (count === 0 && !bubble.masque) issues.push(issue('error', 'empty', panel, n));
        if (count > MAX_WORDS) issues.push(issue('error', 'too_long', panel, n, { count, max: MAX_WORDS }));
        const box = boxPercent(bubble);
        boxes[i] = box;
        const margin = type === 'cri' ? SHOUT_OVERFLOW : 0;
        if (box.x < -margin - EPSILON || box.y < -margin - EPSILON || 100 + margin + EPSILON < box.x + box.w || 100 + margin + EPSILON < box.y + box.h) {
            issues.push(issue('error', 'outside', panel, n));
        }
        issues.push(...checkTail(bubble, type, box, bubbles, i, panel));
        const link = bubble.associee_a;
        if (link !== null && link !== undefined && (!Number.isInteger(link) || link < 0 || link >= i)) issues.push(issue('error', 'link', panel, n));
    });
    if (speaking > MAX_BUBBLES) issues.push(issue('error', 'too_many', panel, null, { count: speaking, max: MAX_BUBBLES }));
    if (overlapping > 3) issues.push(issue('warning', 'crowd', panel, null));
    boxes.forEach((a, i) => {
        boxes.forEach((b, j) => {
            if (!a || !b || j <= i || !intersects(a, b)) return;
            const later = bubbles[j];
            if (!(later.superpose || later.associee_a === i)) issues.push(issue('error', 'overlap', panel, j + 1, { other: i + 1 }));
        });
    });
    for (let j = 1; j < bubbles.length; j++) {
        const prev = boxes[j - 1];
        const cur = boxes[j];
        if (!prev || !cur || bubbles[j].superpose) continue;
        if (cur.y + cur.h <= prev.y + EPSILON && cur.x <= prev.x) issues.push(issue('warning', 'order', panel, j + 1));
    }
    return issues;
}

/** Contrôles de toute la conversation, comme ConversationGrammar::check. */
export function check(panels: { bubbles: Bubble[] }[]): Issue[] {
    const issues: Issue[] = [];
    if (panels.length < MIN_PANELS || panels.length > MAX_PANELS) {
        issues.push(issue('error', 'panel_count', null, null, { count: panels.length, min: MIN_PANELS, max: MAX_PANELS }));
    }
    let total = 0;
    panels.forEach((panel, p) => {
        total += panel.bubbles.length;
        issues.push(...checkPanel(panel.bubbles, p + 1));
    });
    if (total === 0) issues.push(issue('error', 'no_bubble', null, null));
    return issues;
}

export const errors = (issues: Issue[]): Issue[] => issues.filter((i) => i.level === 'error');
