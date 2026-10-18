// Câblage de la coquille Inertia (P6-03, extrait d'app.ts pour être testé) : gabarit membre par défaut des pages et
// `window.XM.outbox`, contrat de shared/outbox-boot.js (remise d'un SOS hors ligne par public/sos.js `handOff`) adossé au
// store Pinia `outbox` (P6-05). Une régression ici casserait le SOS hors ligne de l'espace membre.

/** Ce que le store `outbox` offre au contrat `XM.outbox`. */
export interface OutboxLike {
    add(entry: Record<string, unknown>): Promise<unknown>;
    flush(force?: boolean): Promise<unknown>;
    refresh(): Promise<unknown>;
}

/** Contrat `window.XM.outbox` (shared/outbox-boot.js) : jamais d'exception vers l'appelant, null en cas d'échec. */
export interface OutboxBridge {
    add(entry: Record<string, unknown>): Promise<unknown>;
    flush(options?: { force?: boolean }): Promise<unknown>;
    count(): Promise<unknown>;
}

/**
 * Gabarit par défaut : une page qui ne déclare pas le sien (`layout` absent) reçoit `layout` ; `layout: null`
 * (defineOptions) la laisse sans gabarit, un autre gabarit est gardé.
 */
export function withDefaultLayout<T extends object>(page: T, layout: unknown): T {
    const options = page as T & { layout?: unknown };
    if (options.layout === undefined) options.layout = layout;
    return page;
}

/** Pont `XM.outbox` vers le store, posé sur `target.XM` (créé s'il manque). */
export function bindOutbox(target: { XM?: Record<string, unknown> }, outbox: OutboxLike): OutboxBridge {
    const bridge: OutboxBridge = {
        add: (entry) => outbox.add(entry).catch(() => null),
        flush: (options) => outbox.flush(Boolean(options?.force)).catch(() => null),
        count: () => outbox.refresh().catch(() => null),
    };
    target.XM = target.XM || {};
    target.XM.outbox = bridge;
    return bridge;
}
