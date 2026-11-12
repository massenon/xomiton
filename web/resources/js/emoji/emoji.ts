// Sélecteur d'émojis : logique sans interface (données à la demande, recherche, teintes de peau, récents, insertion).
// Données : resources/js/emoji/emojis.json, UNIQUE source de vérité (copiée vers l'application Flutter par
// scripts/emoji-sync.mjs). Émojis Unicode natifs seulement : aucune image, aucune police, aucun CDN.

export interface EmojiEntry { c: string; n: string; k?: string; t?: 1 }
export interface EmojiCategory { id: string; label: string; icon: string; emojis: EmojiEntry[] }
export interface EmojiData { version: number; categories: EmojiCategory[] }

/** Teintes de peau (modificateurs Fitzpatrick U+1F3FB à U+1F3FF). Index 0 = teinte par défaut (aucun modificateur). */
export const TONES = ['', '\u{1F3FB}', '\u{1F3FC}', '\u{1F3FD}', '\u{1F3FE}', '\u{1F3FF}'] as const;
export const TONE_KEYS = ['default', 'light', 'medium_light', 'medium', 'medium_dark', 'dark'] as const;

export const RECENTS_KEY = 'xm_emoji_recent_v1';
export const TONE_KEY = 'xm_emoji_tone_v1';
export const RECENTS_MAX = 24;

let loading: Promise<EmojiData> | null = null;

/** Charge la liste à la demande (morceau Vite séparé) ; un échec n'est pas gardé : la demande suivante réessaie. */
export function loadEmojis(): Promise<EmojiData> {
    if (!loading) {
        loading = import('./emojis.json').then((module) => module.default as EmojiData).catch((error) => {
            loading = null;
            throw error;
        });
    }
    return loading;
}

/** Minuscules, sans accents ni ligatures : « Cœur » et « coeur » se valent. */
export function normalize(text: string): string {
    return text.toLowerCase().replace(/œ/g, 'oe').replace(/æ/g, 'ae').normalize('NFD').replace(/\p{M}/gu, '').replace(/[’']/g, ' ').replace(/\s+/g, ' ').trim();
}

/** Clé d'un émoji sans teinte ni sélecteur de variante : retrouve l'entrée d'un récent teinté. */
export function baseKey(emoji: string): string {
    return emoji.replace(/[\u{1F3FB}-\u{1F3FF}️]/gu, '');
}

/**
 * Applique une teinte : le modificateur se place après le premier point de code (le sélecteur de variante qui le suit
 * est retiré), ce qui garde les séquences ZWJ valides (👩🏽‍⚕️, 🙋🏽‍♀️).
 */
export function applyTone(emoji: string, tone: number): string {
    const modifier = TONES[tone] ?? '';
    if (!modifier) return emoji;
    const [first, ...rest] = Array.from(emoji);
    if (rest[0] === '️') rest.shift();
    return `${first}${modifier}${rest.join('')}`;
}

/** Longueur en points de code : même règle que la validation serveur (`max:` de Laravel = mb_strlen) et feed/composer.ts. */
export const codePointLength = (value: string): number => Array.from(value).length;

export interface SearchIndexEntry { entry: EmojiEntry; category: string; haystack: string }

export function buildIndex(data: EmojiData): SearchIndexEntry[] {
    return data.categories.flatMap((category) => category.emojis.map((entry) => ({ entry, category: category.id, haystack: normalize(`${entry.n} ${entry.k ?? ''}`) })));
}

/** Tous les mots de la requête doivent se retrouver (début de mot ou dans le mot) dans le nom ou les mots-clés. */
export function search(index: SearchIndexEntry[], query: string): EmojiEntry[] {
    const words = normalize(query).split(' ').filter(Boolean);
    if (words.length === 0) return [];
    const scored: Array<{ entry: EmojiEntry; score: number }> = [];
    for (const item of index) {
        let score = 0;
        let ok = true;
        for (const word of words) {
            const at = item.haystack.indexOf(word);
            if (at === -1) { ok = false; break; }
            score += at === 0 || item.haystack[at - 1] === ' ' ? 0 : 1;
        }
        if (ok) scored.push({ entry: item.entry, score });
    }
    return scored.sort((a, b) => a.score - b.score).map((item) => item.entry);
}

function readList(): string[] {
    try {
        const raw = JSON.parse(window.localStorage.getItem(RECENTS_KEY) ?? '[]') as unknown;
        if (!Array.isArray(raw)) return [];
        return raw.filter((value): value is string => typeof value === 'string' && value !== '' && value.length <= 32).slice(0, RECENTS_MAX);
    } catch {
        return [];
    }
}

/** Récents de cet appareil (jamais envoyés au serveur) ; stockage indisponible ou corrompu : liste vide. */
export const readRecents = (): string[] => readList();

export function pushRecent(emoji: string): string[] {
    const list = [emoji, ...readList().filter((value) => value !== emoji)].slice(0, RECENTS_MAX);
    try {
        window.localStorage.setItem(RECENTS_KEY, JSON.stringify(list));
    } catch {
        // Stockage refusé (navigation privée, quota) : la liste vit le temps de la page seulement.
    }
    return list;
}

export function readTone(): number {
    try {
        const value = Number(window.localStorage.getItem(TONE_KEY));
        return Number.isInteger(value) && value >= 0 && value < TONES.length ? value : 0;
    } catch {
        return 0;
    }
}

export function writeTone(tone: number): void {
    try {
        window.localStorage.setItem(TONE_KEY, String(tone));
    } catch {
        // Voir pushRecent.
    }
}

export type TextTarget = HTMLInputElement | HTMLTextAreaElement;

export interface InsertResult { inserted: boolean }

/**
 * Insère à la position du curseur (ou remplace la sélection) puis remet le focus et place le curseur après l'émoji.
 * Refuse sans rien changer si la longueur en points de code dépasserait `max` (null = illimité).
 * L'événement `input` est émis pour que v-model et les écouteurs voient le changement.
 */
export function insertAtCursor(target: TextTarget, emoji: string, max: number | null, selection?: { start: number; end: number }): InsertResult {
    const value = target.value;
    const start = Math.min(selection?.start ?? target.selectionStart ?? value.length, value.length);
    const end = Math.min(Math.max(selection?.end ?? target.selectionEnd ?? start, start), value.length);
    const next = codePointLength(value.slice(0, start)) + codePointLength(emoji) + codePointLength(value.slice(end));
    if (max !== null && max > 0 && next > max) return { inserted: false };
    target.focus();
    target.setRangeText(emoji, start, end, 'end');
    const caret = start + emoji.length;
    target.setSelectionRange(caret, caret);
    target.dispatchEvent(new Event('input', { bubbles: true }));
    return { inserted: true };
}
