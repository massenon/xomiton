// Remplaçant de side-channel (P4-12) : même comportement que le paquet d'origine pour les opérations de qs (clés
// objets, fonctions et primitives ; set, get, has, delete, assert), et qs donne le même résultat avec l'un ou l'autre
// (objets imbriqués, tableaux, formats d'Inertia, cycle).
// Dépendances transitives (non déclarées dans package.json, par choix) : qs vient de @inertiajs/core, side-channel de qs.
// Les deux sont résolus depuis le dossier de qs ; si qs abandonne side-channel, le premier test échoue et dit quoi faire.
// Le remplaçant ne s'applique qu'au build (greffon Vite xm-side-channel-shim, apply: 'build') ; ici, qs est rechargé par
// le require de Node avec le remplaçant mis dans son cache : ni vi.mock ni les greffons Vite n'atteignent le require()
// d'un paquet CommonJS de node_modules.
import Module, { createRequire } from 'node:module';
import { dirname, sep } from 'node:path';
import { describe, expect, it, vi } from 'vitest';
import getSideChannel from './side-channel.js';

const require = createRequire(import.meta.url);
const qsDir = dirname(require.resolve('qs/package.json'));
const sideChannelPath = require.resolve('side-channel', { paths: [qsDir] });
const getOriginal = require(sideChannelPath);
const qsOriginal = require('qs');

/** qs chargé à neuf avec `replacement` à la place de side-channel ; le cache de require est rendu tel quel ensuite. */
function loadQsWith(replacement) {
    const cache = require.cache;
    const owned = (file) => file === sideChannelPath || file.startsWith(qsDir + sep);
    const saved = Object.fromEntries(Object.entries(cache).filter(([file]) => owned(file)));
    Object.keys(saved).forEach((file) => delete cache[file]);
    const fake = new Module(sideChannelPath);
    fake.filename = sideChannelPath;
    fake.loaded = true;
    fake.exports = replacement;
    cache[sideChannelPath] = fake;
    try {
        return require('qs');
    } finally {
        Object.keys(cache).filter(owned).forEach((file) => delete cache[file]);
        Object.assign(cache, saved);
    }
}

const run = (channel) => {
    const object = {};
    const fn = () => undefined;
    const keys = [object, fn, 'clé', 0, null, undefined, Symbol.for('x')];
    const out = [channel.has(object), channel.get(object), channel.delete(object)];
    keys.forEach((key, index) => channel.set(key, index));
    keys.forEach((key) => out.push(channel.has(key), channel.get(key)));
    out.push(channel.delete('clé'), channel.has('clé'), channel.delete({}), channel.get({}));
    channel.set(object, 'remplacé');
    out.push(channel.get(object));
    let thrown = false;
    try {
        channel.assert({});
    } catch (error) {
        thrown = error instanceof TypeError;
    }
    channel.assert(object);
    out.push(thrown);
    return out;
};

describe('side-channel (remplaçant)', () => {
    it('qs dépend toujours de side-channel', () => {
        const { dependencies = {} } = require('qs/package.json');
        expect(Object.keys(dependencies), 'qs ne dépend plus de side-channel : retirer app/shims/side-channel.js et le greffon xm-side-channel-shim de vite.config.js').toContain('side-channel');
    });

    it('se comporte comme le paquet d’origine', () => {
        expect(run(getSideChannel())).toEqual(run(getOriginal()));
    });

    it('canaux indépendants', () => {
        const a = getSideChannel();
        const b = getSideChannel();
        const key = {};
        a.set(key, 1);
        expect(b.has(key)).toBe(false);
    });

    it('qs avec le remplaçant : mêmes chaînes et mêmes objets qu’avec le paquet d’origine, cycle refusé', () => {
        const shim = vi.fn(getSideChannel);
        const qs = loadQsWith(shim);
        expect(qs).not.toBe(qsOriginal);
        shim.mockClear();

        const data = { page: 2, filtre: { thème: 'santé', tags: ['a', 'b'], vide: [] }, ids: [3, 1], liste: [{ id: 1, nom: 'Awa' }, { id: 2 }], rien: null };
        for (const options of [{}, { encodeValuesOnly: true, arrayFormat: 'brackets' }, { encodeValuesOnly: true, arrayFormat: 'indices' }]) {
            expect(qs.stringify(data, options)).toBe(qsOriginal.stringify(data, options));
        }
        const queries = ['page=2&filtre[th%C3%A8me]=sant%C3%A9&filtre[tags][]=a&filtre[tags][]=b', 'a[0][b]=1&a[1][b]=2&a[1][c]=3', 'a[30]=x&a[1]=y', 'a=1&a=2&b[c][d][e]=f'];
        for (const query of queries) {
            expect(qs.parse(query)).toEqual(qsOriginal.parse(query));
        }

        const cyclic = { nom: 'boucle', enfant: {} };
        cyclic.enfant.parent = cyclic;
        expect(() => qs.stringify(cyclic)).toThrow(new RangeError('Cyclic object value'));
        expect(() => qsOriginal.stringify(cyclic)).toThrow(new RangeError('Cyclic object value'));
        expect(shim).toHaveBeenCalled();
    });
});
