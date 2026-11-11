// Progression du manuel (P8-12) : cases « point vu » gardées **sur l'appareil seulement** (localStorage, par compte pour
// un appareil partagé), jamais envoyées au serveur. Chaque lecture et chaque écriture est protégée (navigation privée,
// stockage bloqué ou plein) : sans stockage, la progression vit en mémoire le temps de la page.
import type { ManualModule } from './types';

export const PROGRESS_KEY = 'xm_ccc_manuel_v1';

/** Clé du module → rangs (à partir de 0) des points vus, triés et sans doublon. */
export type SeenState = Record<string, number[]>;

export function storageKey(account: number | string | null): string {
    return account === null || account === '' ? PROGRESS_KEY : `${PROGRESS_KEY}:${account}`;
}

/** Ne garde que les modules connus et les rangs existants : un chapitre retiré ou un module masqué n'y reste pas. */
export function sanitize(raw: unknown, modules: ManualModule[]): SeenState {
    const out: SeenState = {};
    if (raw === null || typeof raw !== 'object' || Array.isArray(raw)) return out;
    const source = raw as Record<string, unknown>;
    for (const module of modules) {
        const list = source[module.key];
        if (!Array.isArray(list)) continue;
        const ranks = [...new Set(list.filter((rank): rank is number => Number.isInteger(rank) && rank >= 0 && rank < module.chapters.length))].sort((a, b) => a - b);
        if (ranks.length > 0) out[module.key] = ranks;
    }
    return out;
}

/** Lecture tolérante : stockage absent, illisible ou corrompu → progression vide. */
export function loadSeen(account: number | string | null, modules: ManualModule[]): { seen: SeenState; available: boolean } {
    try {
        const stored = window.localStorage.getItem(storageKey(account));
        if (stored === null) return { seen: {}, available: true };
        return { seen: sanitize(JSON.parse(stored), modules), available: true };
    } catch {
        return { seen: {}, available: isStorageAvailable() };
    }
}

/** Écriture protégée : false si le navigateur refuse de garder la progression. */
export function saveSeen(account: number | string | null, seen: SeenState): boolean {
    try {
        window.localStorage.setItem(storageKey(account), JSON.stringify(seen));
        return true;
    } catch {
        return false;
    }
}

export function clearSeen(account: number | string | null): boolean {
    try {
        window.localStorage.removeItem(storageKey(account));
        return true;
    } catch {
        return false;
    }
}

function isStorageAvailable(): boolean {
    try {
        const probe = `${PROGRESS_KEY}:probe`;
        window.localStorage.setItem(probe, '1');
        window.localStorage.removeItem(probe);
        return true;
    } catch {
        return false;
    }
}

export function seenCount(seen: SeenState, module: ManualModule): number {
    return seen[module.key]?.length ?? 0;
}

/** Pourcentage entier de `done` sur `total` (0 si rien à voir). */
export function percentOf(done: number, total: number): number {
    return total > 0 ? Math.round((100 * done) / total) : 0;
}

export function totals(seen: SeenState, modules: ManualModule[]): { done: number; total: number; percent: number } {
    const total = modules.reduce((sum, module) => sum + module.chapters.length, 0);
    const done = modules.reduce((sum, module) => sum + seenCount(seen, module), 0);
    return { done, total, percent: percentOf(done, total) };
}

/** Coche ou décoche un point : nouvel état, l'ancien n'est pas modifié. */
export function toggle(seen: SeenState, key: string, rank: number, on: boolean): SeenState {
    const ranks = new Set(seen[key] ?? []);
    if (on) ranks.add(rank);
    else ranks.delete(rank);
    const next: SeenState = { ...seen };
    if (ranks.size > 0) next[key] = [...ranks].sort((a, b) => a - b);
    else delete next[key];
    return next;
}
