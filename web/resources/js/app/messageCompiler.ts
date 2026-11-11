// Compilateur de messages de l'espace Vue (P4-12, ADR-18) : remplace celui de vue-i18n (@intlify/message-compiler,
// ~5 Ko gzip), retiré du paquet app.ts par le drapeau __INTLIFY_DROP_MESSAGE_COMPILER__ (vite.config.js). Il couvre la
// syntaxe du catalogue exporté de lang/<locale>/*.php (resources/js/lang/<locale>.json) : texte, placeholders nommés
// {nom} et de liste {0}, littéraux {'@'} (avec les échappements \\ \' \uXXXX \UXXXXXX), pluriels « a | b | c »
// (espaces et sauts de ligne autour de la barre ignorés, comme vue-i18n). Le résultat passe par le contexte de
// vue-i18n (named, list, interpolate, normalize, plural) : implicites `count` / `n`, règle de pluriel française et
// affichage des valeurs restent les siens. Une syntaxe hors de ce sous-ensemble (message lié @:cle, accolade seule) est
// rendue telle quelle ; messageCompiler.test.ts compare chaque message du catalogue au compilateur d'origine. Hors
// production, vue-i18n signale une fois en console l'usage d'un compilateur personnalisé (option expérimentale).
import type { MessageCompiler, MessageContext } from 'vue-i18n';

/** Morceau d'un message : texte, placeholder nommé ou indice de liste. */
export type MessagePart = string | { named: string } | { list: number };

const NAMED = /^[A-Za-z_][A-Za-z0-9_$-]*$/;
const LIST = /^\d+$/;
const SPACE = (ch: string | undefined) => ch === ' ' || ch === '\n';

/**
 * Placeholder qui commence à `start` (juste après « { ») : morceau lu et position de « } » ; null si la syntaxe n'est pas
 * couverte. Littéral {'…'} : échappements \\ \' \uXXXX \UXXXXXX, ni saut de ligne ni apostrophe nue (vue-i18n).
 */
function placeholder(text: string, start: number): { part: MessagePart; end: number } | null {
    let i = start;
    while (SPACE(text[i])) i += 1;
    let part: MessagePart;
    if (text[i] === "'") {
        let value = '';
        for (i += 1; text[i] !== "'"; i += 1) {
            const ch = text[i];
            if (ch === undefined || ch === '\n') return null;
            if (ch !== '\\') {
                value += ch;
                continue;
            }
            const next = text[i + 1];
            const hex = next === 'u' ? 4 : next === 'U' ? 6 : 0;
            const digits = text.slice(i + 2, i + 2 + hex);
            if (next === '\\' || next === "'") {
                value += next;
                i += 1;
            } else if (hex && digits.length === hex && /^[0-9a-fA-F]+$/.test(digits)) {
                value += String.fromCodePoint(parseInt(digits, 16));
                i += 1 + hex;
            } else {
                return null;
            }
        }
        part = value;
        i += 1;
    } else {
        const close = text.indexOf('}', i);
        if (close < 0) return null;
        const name = text.slice(i, close).replace(/[ \n]+$/, '');
        if (NAMED.test(name)) part = { named: name };
        else if (LIST.test(name)) part = { list: Number(name) };
        else return null;
        i = close;
    }
    while (SPACE(text[i])) i += 1;
    return text[i] === '}' ? { part, end: i } : null;
}

/**
 * Découpe un message en formes (une seule hors pluriel), chacune en morceaux ; null si la syntaxe sort du sous-ensemble.
 * Sauts de ligne CRLF, U+2028 et U+2029 lus comme \n (vue-i18n fait de même).
 */
export function parseMessage(source: string): MessagePart[][] | null {
    const text = source.replace(/\r\n|\u2028|\u2029/g, '\n');
    const cases: MessagePart[][] = [];
    let parts: MessagePart[] = [];
    let buffer = '';
    const flush = () => {
        if (buffer) parts.push(buffer);
        buffer = '';
    };
    for (let i = 0; i < text.length; i += 1) {
        const ch = text[i];
        if (ch === '{') {
            const read = placeholder(text, i + 1);
            if (read === null) return null;
            if (typeof read.part === 'string') buffer += read.part;
            else {
                flush();
                parts.push(read.part);
            }
            i = read.end;
        } else if (ch === '|') {
            // Espaces et sauts de ligne autour de la barre : hors des formes.
            buffer = buffer.replace(/[ \n]+$/, '');
            flush();
            cases.push(parts);
            parts = [];
            while (SPACE(text[i + 1])) i += 1;
        } else if (ch === '}' || ch === '@') {
            return null;
        } else {
            buffer += ch;
        }
    }
    flush();
    cases.push(parts);
    if (cases.length > 1 && cases.some((forms) => forms.length === 0)) return null;
    return cases;
}

/**
 * Contexte que reçoivent les messages précompilés de vue-i18n (ceux de @intlify/unplugin-vue-i18n s'en servent aussi) :
 * normalize et interpolate n'y sont pas typés publiquement.
 */
type CompiledContext = MessageContext<string> & {
    normalize(values: unknown[]): string;
    interpolate(value: unknown): string;
};

const render = (ctx: CompiledContext, parts: MessagePart[]): string => ctx.normalize(parts.map((part) => {
    if (typeof part === 'string') return part;
    return ctx.interpolate('named' in part ? ctx.named(part.named) : ctx.list(part.list));
}));

const cache = new Map<string, ReturnType<MessageCompiler<string, string>>>();

/** Compilateur pour l'option `messageCompiler` de createI18n (messages en chaînes seulement). */
export const compileMessage: MessageCompiler<string, string> = (source) => {
    const key = String(source);
    const cached = cache.get(key);
    if (cached) return cached;
    const cases = parseMessage(key);
    const message = cases === null
        ? () => key
        : (ctx: MessageContext<string>) => (cases.length > 1
            ? ctx.plural(cases.map((parts) => render(ctx as CompiledContext, parts)))
            : render(ctx as CompiledContext, cases[0]));
    cache.set(key, message as ReturnType<MessageCompiler<string, string>>);
    return message as ReturnType<MessageCompiler<string, string>>;
};
