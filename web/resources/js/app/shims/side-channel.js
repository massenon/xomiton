// Remplaçant de « side-channel » (P4-12, ADR-18), substitué au build seulement par le greffon Vite xm-side-channel-shim
// (vite.config.js, resolveId, apply: 'build' ; `vite dev` et Vitest gardent le paquet d'origine) : qs, dont
// @inertiajs/core se sert pour les chaînes de requête, n'en use que get / set / has sur des objets (copies et cycles).
// Le paquet d'origine tire ~8 Ko gzip de dépendances (get-intrinsic, object-inspect, call-bind…) pour des
// environnements sans WeakMap ; même interface ici : WeakMap pour les objets et fonctions, Map pour les autres clés.
export default function getSideChannel() {
    let weak;
    let map;
    const store = (key, create) => {
        if (key !== null && (typeof key === 'object' || typeof key === 'function')) {
            if (!weak && create) weak = new WeakMap();
            return weak;
        }
        if (!map && create) map = new Map();
        return map;
    };
    const channel = {
        assert(key) {
            if (!channel.has(key)) throw new TypeError('Side channel does not contain the given key');
        },
        delete(key) {
            const data = store(key, false);
            return !!data && data.delete(key);
        },
        get(key) {
            return store(key, false)?.get(key);
        },
        has(key) {
            const data = store(key, false);
            return !!data && data.has(key);
        },
        set(key, value) {
            store(key, true).set(key, value);
        },
    };
    return channel;
}
