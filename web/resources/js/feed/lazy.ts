// Composants chargés à la demande dans les écrans du fil (lot fil-ecrans) : modales de création et de signalement en
// morceaux séparés, demandés au premier clic (ou au survol). Volontairement sans defineAsyncComponent de Vue ni
// <i18n-t> de vue-i18n : les utiliser depuis une page ajouterait leur code au morceau partagé avec app.ts (budget
// « JS espace membre », app.ts + imports statiques < 150 Ko gzip, scripts/check-budgets.mjs).
import { markRaw, shallowRef, type Component } from 'vue';

export function lazyComponent(loader: () => Promise<{ default: Component }>) {
    const component = shallowRef<Component | null>(null);
    let pending: Promise<Component | null> | null = null;

    /** Charge le morceau (une seule fois) ; rend null si le réseau manque (nouvel essai au prochain appel). */
    const load = (): Promise<Component | null> => (pending ||= loader()
        .then((module) => {
            component.value = markRaw(module.default);
            return component.value;
        })
        .catch(() => {
            pending = null;
            return null;
        }));

    return { component, load };
}

export type TextPart = { text: string } | { slot: string };

/**
 * Découpe une phrase traduite autour de ses emplacements (`{report}`, `{sos}`…) pour y insérer des liens sans
 * v-html : `t(key, marks(names))` puis `textParts()`.
 */
const MARK = '\u0000';

export const marks = (names: string[]): Record<string, string> => Object.fromEntries(names.map((name) => [name, `${MARK}${name}${MARK}`]));

export function textParts(message: string, names: string[]): TextPart[] {
    return message.split(MARK).filter((part) => part !== '').map((part) => (names.includes(part) ? { slot: part } : { text: part }));
}
