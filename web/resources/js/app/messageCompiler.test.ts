// @vitest-environment jsdom
// Compilateur de messages de l'espace Vue (P4-12) : même rendu que le compilateur de vue-i18n pour chaque message du
// catalogue (resources/js/lang/*.json, sortie de « php artisan lang:export » : {nom}, {'@'}, {'{'}, pluriels « | »), avec
// et sans nombre ; cas limites de la syntaxe ; une syntaxe non couverte est rendue telle quelle.
import { describe, expect, it } from 'vitest';
import { createI18n } from 'vue-i18n';
import { compileMessage, parseMessage } from './messageCompiler';
import { flatResolver, frenchPlural } from './i18n';

type Catalog = Record<string, string>;

const catalogs = import.meta.glob<Catalog>('../lang/*.json', { import: 'default', eager: true });

/**
 * Compilateur d'origine le temps d'un appel : le drapeau __INTLIFY_DROP_MESSAGE_COMPILER__ de vite.config.js est aussi
 * posé sur globalThis par Vitest, et @intlify/core-base (non transformé) le relit à chaque compilation.
 * @intlify/core-base est une dépendance transitive (vue-i18n), non déclarée dans package.json : si une mise à jour
 * cesse de relire ce drapeau, le premier test (« référence valide ») échoue avant toute comparaison.
 */
const withOriginalCompiler = <T>(fn: () => T): T => {
    const flags = globalThis as { __INTLIFY_DROP_MESSAGE_COMPILER__?: boolean };
    const dropped = flags.__INTLIFY_DROP_MESSAGE_COMPILER__;
    flags.__INTLIFY_DROP_MESSAGE_COMPILER__ = false;
    try {
        return fn();
    } finally {
        flags.__INTLIFY_DROP_MESSAGE_COMPILER__ = dropped;
    }
};

/** Deux instances : compilateur d'origine (vue-i18n, absent du paquet de production) et le nôtre. */
const pair = (messages: Catalog) => {
    const options = {
        legacy: false as const,
        locale: 'fr',
        messages: { fr: messages },
        messageResolver: flatResolver,
        pluralRules: { fr: frenchPlural },
        missingWarn: false,
        fallbackWarn: false,
        warnHtmlMessage: false,
    };
    const original = createI18n(options).global.t as (...args: unknown[]) => string;
    return {
        reference: (...args: unknown[]) => withOriginalCompiler(() => original(...args)),
        ours: createI18n({ ...options, messageCompiler: compileMessage }).global.t,
    };
};

const namesOf = (message: string) => [...message.matchAll(/\{\s*([A-Za-z_][\w$-]*)\s*\}/g)].map((match) => match[1]);

describe('compileMessage', () => {
    it('le compilateur d’origine est bien celui de vue-i18n dans les tests (référence valide)', () => {
        const { reference } = pair({ a: '{n} heure | {n} heures', b: "nom{'@'}exemple.bj" });
        expect(reference('a', { n: 2 }, 2)).toBe('2 heures');
        expect(reference('b')).toBe('nom@exemple.bj');
    });

    for (const [path, catalog] of Object.entries(catalogs)) {
        it(`rend chaque message de ${path.replace('../lang/', '')} comme vue-i18n`, () => {
            const { reference, ours } = pair(catalog);
            const keys = Object.keys(catalog);
            expect(keys.length).toBeGreaterThan(100);
            const diffs: string[] = [];
            for (const key of keys) {
                const message = catalog[key];
                expect(parseMessage(message), `syntaxe non couverte : ${key}`).not.toBeNull();
                const named = Object.fromEntries(namesOf(message).map((name) => [name, `«${name}»`]));
                const calls: Array<() => [string, string]> = [
                    () => [reference(key), ours(key)],
                    () => [reference(key, named), ours(key, named)],
                    ...[0, 1, 2, 5].map((count) => () => [
                        reference(key, { ...named, count }, count),
                        ours(key, { ...named, count }, count),
                    ] as [string, string]),
                ];
                for (const call of calls) {
                    const [expected, actual] = call();
                    if (expected !== actual) diffs.push(`${key} : ${JSON.stringify(expected)} ≠ ${JSON.stringify(actual)}`);
                }
            }
            expect(diffs).toEqual([]);
        });
    }

    it('cas limites de la syntaxe : mêmes résultats que vue-i18n', () => {
        const messages: Catalog = {
            pipe_spaces: 'a  |  b  |  c',
            pipe_tight: ' x|y ',
            pipe_newline: 'un\n|\nplusieurs',
            literals: "{'{'}x{'}'} {'\\''} {'\\u00e9'}{'\\U01F600'} {'\\\\'}",
            spaced: '{ name } ok',
            dashed: '{a-b} et {a_b}',
            list: '{0} et {1}',
            crlf: 'l1\r\nl2',
            percent: '{value} %',
            empty: '',
            lead: '  début',
        };
        const { reference, ours } = pair(messages);
        const named = { name: 'N', 'a-b': 'AB', a_b: 'A_B', value: 5 };
        for (const key of Object.keys(messages)) {
            expect(ours(key, named), key).toBe(reference(key, named));
            for (const count of [0, 1, 2, 3]) expect(ours(key, count), `${key} ${count}`).toBe(reference(key, count));
            expect(ours(key, ['L0', 'L1']), key).toBe(reference(key, ['L0', 'L1']));
        }
        expect(ours('literals')).toBe("{x} ' é😀 \\");
    });

    it('syntaxe hors du sous-ensemble (message lié, accolade seule) : rendue telle quelle', () => {
        expect(parseMessage('@:common.close')).toBeNull();
        expect(parseMessage('a } b')).toBeNull();
        expect(parseMessage('{ouvert')).toBeNull();
        expect(parseMessage('{$x}')).toBeNull();
        expect(parseMessage('x | ')).toBeNull();
        const { ours } = pair({ linked: '@:common.close', brace: 'a } b' });
        expect(ours('linked')).toBe('@:common.close');
        expect(ours('brace')).toBe('a } b');
    });

    it('met en cache la fonction d’un même message', () => {
        const context = { key: 'k', locale: 'fr' };
        expect(compileMessage('{n} x', context)).toBe(compileMessage('{n} x', context));
    });
});
