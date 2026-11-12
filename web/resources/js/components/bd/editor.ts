// État et gestes de l'éditeur des conversations en BD (P20-07), en fonctions pures testées (Vitest) : ajouter, déplacer
// (glisser ou clavier, pas de 2 %), réordonner et supprimer des bulles ; cases choisies dans la bibliothèque de scènes ;
// charge utile de l'enregistrement (`PUT /api/v1/bd-conversations/{ulid}`, format de la grammaire §6) ; anti-rebond de
// l'enregistrement automatique. Aucune image importée : une case ne référence qu'une scène de la bibliothèque.
import type { Bubble, BubbleType } from './bubbles';

export const STEP = 2;
export const BIG_STEP = 10;

export interface SceneCharacter { key: string; name: string }

export interface Scene {
    key: string;
    title: string;
    image: string | null;
    description: string;
    zone: string;
    characters: SceneCharacter[];
}

export interface EditorBubble extends Bubble {
    uid: string;
    type: BubbleType;
    personnage: string | null;
    texte: string;
    emphase: string[];
    position: { x: number; y: number };
    queue: { vers: string; x: number | null; y: number | null } | null;
    associee_a: number | null;
    superpose: boolean;
}

export interface EditorPanel {
    uid: string;
    scene: string;
    bubbles: EditorBubble[];
}

let counter = 0;
export const uid = (prefix = 'b'): string => `${prefix}${Date.now().toString(36)}${(counter++).toString(36)}`;

const clamp = (value: number, min: number, max: number): number => Math.min(max, Math.max(min, value));
const round = (value: number): number => Math.round(value * 100) / 100;

/** Bulle venue du serveur (format de la grammaire) → bulle de l'éditeur. */
export function fromGrammar(bubble: Bubble): EditorBubble {
    const queue = bubble.queue && typeof bubble.queue === 'object' && bubble.queue.vers
        ? { vers: String(bubble.queue.vers), x: bubble.queue.x ?? null, y: bubble.queue.y ?? null }
        : null;
    return {
        uid: uid(),
        type: (bubble.type as BubbleType) || 'parole',
        personnage: typeof bubble.personnage === 'string' && bubble.personnage !== '' ? bubble.personnage : null,
        texte: String(bubble.texte ?? ''),
        emphase: (bubble.emphase ?? []).filter((e): e is string => typeof e === 'string'),
        position: { x: Number(bubble.position?.x ?? 0), y: Number(bubble.position?.y ?? 0) },
        queue,
        associee_a: Number.isInteger(bubble.associee_a) ? bubble.associee_a as number : null,
        superpose: Boolean(bubble.superpose),
    };
}

/** Nouvelle bulle : parole du premier personnage de la scène (ou cartouche s'il n'y en a pas), décalée des autres. */
export function newBubble(scene: Scene | undefined, existing: EditorBubble[], type?: BubbleType): EditorBubble {
    const first = scene?.characters[0]?.key ?? null;
    const kind: BubbleType = type ?? (first ? 'parole' : 'cartouche');
    const offset = existing.length;
    const x = clamp(4 + offset * 24, 2, 70);
    const y = clamp(4 + (offset % 2) * 6, 2, 60);
    return {
        uid: uid(),
        type: kind,
        personnage: kind === 'cartouche' ? null : first,
        texte: '',
        emphase: [],
        position: { x, y },
        queue: kind === 'cartouche' ? null : { vers: 'personnage', x: clamp(x + 8, 0, 100), y: 55 },
        associee_a: null,
        superpose: false,
    };
}

/** Déplace la bulle (en %), dans les bornes acceptées par le serveur ; la pointe de la queue reste où elle est. */
export function moveBubble(bubble: EditorBubble, dx: number, dy: number): EditorBubble {
    return { ...bubble, position: { x: round(clamp(bubble.position.x + dx, -4, 100)), y: round(clamp(bubble.position.y + dy, -4, 100)) } };
}

/** Place la bulle à une position absolue (glisser). */
export function placeBubble(bubble: EditorBubble, x: number, y: number): EditorBubble {
    return moveBubble({ ...bubble, position: { x: 0, y: 0 } }, x, y);
}

/** Déplace la pointe de la queue (en %, 0 à 100). */
export function moveTail(bubble: EditorBubble, dx: number, dy: number): EditorBubble {
    if (!bubble.queue) return bubble;
    const x = bubble.queue.x ?? 50;
    const y = bubble.queue.y ?? 50;
    return { ...bubble, queue: { ...bubble.queue, x: round(clamp(x + dx, 0, 100)), y: round(clamp(y + dy, 0, 100)) } };
}

/** Pas d'une touche fléchée (Maj : grand pas) ; null pour une autre touche. */
export function arrowDelta(key: string, shift = false): [number, number] | null {
    const step = shift ? BIG_STEP : STEP;
    switch (key) {
        case 'ArrowLeft': return [-step, 0];
        case 'ArrowRight': return [step, 0];
        case 'ArrowUp': return [0, -step];
        case 'ArrowDown': return [0, step];
        default: return null;
    }
}

/** Change le type : un cartouche n'a ni personnage ni queue ; une autre bulle reprend un personnage et une queue. */
export function setType(bubble: EditorBubble, type: BubbleType, scene: Scene | undefined): EditorBubble {
    if (type === 'cartouche') return { ...bubble, type, personnage: null, queue: null };
    return {
        ...bubble,
        type,
        personnage: bubble.personnage ?? scene?.characters[0]?.key ?? null,
        queue: bubble.queue ?? { vers: type === 'message' || type === 'voix_appareil' ? 'ecran' : 'personnage', x: clamp(bubble.position.x + 8, 0, 100), y: 55 },
    };
}

/** Personnage choisi ; `null` = narration (cartouche). */
export function setCharacter(bubble: EditorBubble, key: string | null, scene: Scene | undefined): EditorBubble {
    if (key === null) return setType(bubble, 'cartouche', scene);
    const next = bubble.type === 'cartouche' ? setType(bubble, 'parole', scene) : bubble;
    return { ...next, personnage: key };
}

/** Change la cible de la queue (les bords n'ont pas besoin de pointe). */
export function setTail(bubble: EditorBubble, vers: string): EditorBubble {
    if (bubble.type === 'cartouche') return bubble;
    const x = bubble.queue?.x ?? clamp(bubble.position.x + 8, 0, 100);
    const y = bubble.queue?.y ?? 55;
    return { ...bubble, queue: { vers, x, y } };
}

/** Échange une bulle avec sa voisine (ordre de lecture). Les liens des bulles associées sont remis à zéro. */
export function reorder<T extends { associee_a?: number | null }>(list: T[], index: number, direction: -1 | 1): T[] {
    const target = index + direction;
    if (target < 0 || target >= list.length) return list;
    const out = list.map((item) => ({ ...item, associee_a: null }));
    [out[index], out[target]] = [out[target], out[index]];
    return out;
}

export function move<T>(list: T[], index: number, direction: -1 | 1): T[] {
    const target = index + direction;
    if (target < 0 || target >= list.length) return list;
    const out = [...list];
    [out[index], out[target]] = [out[target], out[index]];
    return out;
}

/** Les personnages qui ne sont plus dans la scène choisie sont remplacés par le premier du casting. */
export function adaptToScene(bubbles: EditorBubble[], scene: Scene | undefined): EditorBubble[] {
    const cast = scene?.characters.map((c) => c.key) ?? [];
    return bubbles.map((bubble) => (bubble.type === 'cartouche' || bubble.personnage === null || cast.includes(bubble.personnage)
        ? bubble
        : { ...bubble, personnage: cast[0] ?? null }));
}

/** Mots en gras saisis en liste séparée par des virgules. */
export const parseEmphasis = (value: string): string[] => value.split(',').map((s) => s.trim()).filter(Boolean).slice(0, 5);

/** Bulle au format de la grammaire (sans identifiant local). */
export function toGrammar(bubble: EditorBubble): Bubble & { emphase: string[] } {
    return {
        type: bubble.type,
        personnage: bubble.type === 'cartouche' ? null : bubble.personnage,
        texte: bubble.texte,
        emphase: bubble.emphase,
        position: { x: bubble.position.x, y: bubble.position.y },
        queue: bubble.type === 'cartouche' || !bubble.queue ? null : { vers: bubble.queue.vers, x: bubble.queue.x, y: bubble.queue.y },
        associee_a: bubble.associee_a,
        superpose: bubble.superpose,
    };
}

/** Charge utile de l'enregistrement. */
export function payload(title: string, summary: string, panels: EditorPanel[]) {
    return {
        title: title.trim(),
        summary: summary.trim() === '' ? null : summary.trim(),
        panels: panels.map((panel) => ({ scene: panel.scene, bubbles: panel.bubbles.map(toGrammar) })),
    };
}

/**
 * Anti-rebond : `schedule()` relance le minuteur ; `flush()` exécute tout de suite l'action en attente ; `cancel()`
 * l'abandonne. Une action en cours n'est jamais doublée : un appel pendant l'envoi est rejoué juste après.
 */
export function debounce(action: () => Promise<void>, delay: number) {
    let timer: ReturnType<typeof setTimeout> | null = null;
    let running: Promise<void> | null = null;
    let again = false;
    const run = async (): Promise<void> => {
        timer = null;
        if (running) {
            again = true;
            return running;
        }
        running = action().finally(() => { running = null; });
        await running;
        if (again) {
            again = false;
            await run();
        }
    };
    return {
        schedule(): void {
            if (timer) clearTimeout(timer);
            timer = setTimeout(() => { run().catch(() => undefined); }, delay);
        },
        async flush(): Promise<void> {
            if (timer) {
                clearTimeout(timer);
                await run();
            } else if (running) {
                await running;
            }
        },
        cancel(): void {
            if (timer) clearTimeout(timer);
            timer = null;
        },
        pending: (): boolean => timer !== null,
    };
}
