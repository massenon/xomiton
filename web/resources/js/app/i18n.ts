// Internationalisation de l'espace Vue (P6-05, NFR-11) : une seule source, lang/<locale>/*.php, exportée à plat par
// « php artisan lang:export » dans resources/js/lang/<locale>.json (clés « fichier.cle.souscle », placeholders {nom},
// pluriels « a | b | c »). Les catalogues sont chargés à la demande (un fichier par locale, hors du paquet app.ts) : la
// locale vient des props partagées Inertia `lang` (HandleInertiaRequests), jamais les messages eux-mêmes.
import { createI18n, type PathValue } from 'vue-i18n';

type Catalog = Record<string, string>;

const catalogs = import.meta.glob<Catalog>('../lang/*.json', { import: 'default' });

/** Clés à plat : « components.sos.label » est une clé, pas un chemin (les clés JSON de Laravel contiennent des points). */
export const flatResolver = (messages: unknown, path: string): PathValue => {
    if (messages === null || typeof messages !== 'object') return null;
    const value = (messages as Record<string, unknown>)[path];
    return typeof value === 'string' ? value : null;
};

/**
 * Pluriel français aligné sur Laravel (MessageSelector) : deux formes → 0 et 1 au singulier ; trois formes
 * (« {0} …|{1} …|[2,*] … ») → zéro, un, plusieurs.
 */
export const frenchPlural = (choice: number, choicesLength: number): number => {
    const n = Math.abs(choice);
    if (choicesLength === 2) return n < 2 ? 0 : 1;
    return Math.min(n, choicesLength - 1, 2);
};

export const i18n = createI18n({
    legacy: false,
    locale: 'fr',
    fallbackLocale: 'fr',
    messages: { fr: {} as Catalog } as Record<string, Catalog>,
    messageResolver: flatResolver,
    pluralRules: { fr: frenchPlural },
    missingWarn: false,
    fallbackWarn: false,
});

export const t = i18n.global.t;

/** Messages déjà en mémoire (tests, catalogue préchargé). */
export function loadMessages(locale: string, messages: Catalog): void {
    i18n.global.setLocaleMessage(locale, messages);
    i18n.global.locale.value = locale;
}

/**
 * Charge le catalogue de la locale (repli sur `fallback`, puis « fr ») et l'active ; rend la locale effective.
 * Un catalogue absent ne bloque jamais le montage : les clés s'affichent telles quelles.
 */
export async function loadLocale(locale: string, fallback = 'fr'): Promise<string> {
    const candidates = [...new Set([locale, fallback, 'fr'])];
    for (const candidate of candidates) {
        const loader = catalogs[`../lang/${candidate}.json`];
        if (!loader) continue;
        try {
            const messages = await loader();
            loadMessages(candidate, messages);
            // Repli : le français complète une traduction partielle (fallbackLocale).
            const french = catalogs['../lang/fr.json'];
            if (candidate !== 'fr' && french) i18n.global.setLocaleMessage('fr', await french());
            return candidate;
        } catch {
            // Réseau coupé pendant le chargement du morceau : on essaie la locale suivante.
        }
    }
    return i18n.global.locale.value;
}
