// Câblage de la coquille Inertia (P6-03, extrait d'app.ts pour être testé) : gabarit membre par défaut des pages et
// `window.XM.outbox`, contrat de shared/outbox-boot.js (remise d'un SOS hors ligne par public/sos.js `handOff`) adossé au
// store Pinia `outbox` (P6-05). Une régression ici casserait le SOS hors ligne de l'espace membre.
// P13-08 : écran plein XmMaintenance (chargé à la demande) sur un 503 `maintenance` d'une visite ou de l'API.

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

// --- Mode maintenance (P13-08) ---------------------------------------------------------------------------------------

/** Corps d'une réponse 503 `maintenance` (API et visite Inertia, docs/API.md §1.2) : `message` générique, texte public. */
export interface MaintenancePayload {
    message?: string;
    code?: string;
    retry_after?: number | null;
    ends_at?: string | null;
    /** Reprise annoncée dépassée, maintenance toujours en cours. */
    overdue?: boolean;
    /** Fuseau d'affichage des heures (`xomiton.display_timezone`, ex. Africa/Porto-Novo). */
    timezone?: string | null;
    maintenance_message?: string | null;
}

/** Évènement de fenêtre émis par useApi sur un 503 `maintenance` (détail : MaintenancePayload). */
export const MAINTENANCE_EVENT = 'xm:maintenance';

/** En-tête posé par EnsureNotInMaintenance sur toute réponse 503 de maintenance. */
export const MAINTENANCE_HEADER = 'x-xm-maintenance';

type HeaderBag = Headers | Record<string, unknown> | null | undefined;

const headerOf = (headers: HeaderBag, name: string): string => {
    if (!headers) return '';
    if (typeof (headers as Headers).get === 'function') return (headers as Headers).get(name) ?? '';
    const bag = headers as Record<string, unknown>;
    const key = Object.keys(bag).find((k) => k.toLowerCase() === name);
    return key ? String(bag[key] ?? '') : '';
};

/** Réponse 503 de maintenance : en-tête X-Xm-Maintenance ou corps `code: maintenance` (jamais un 503 quelconque). */
export function isMaintenanceResponse(status: number, headers: HeaderBag, data: unknown): boolean {
    if (status !== 503) return false;
    if (headerOf(headers, MAINTENANCE_HEADER) !== '') return true;
    return Boolean(data && typeof data === 'object' && (data as MaintenancePayload).code === 'maintenance');
}

/**
 * Écran plein à la demande (une seule fois par page) : `load` importe le composant, `mount` le monte. Un échec du
 * chargement (morceau absent hors connexion) recharge la page : le serveur sert alors la page de maintenance.
 */
export function createMaintenanceScreen<C>(
    load: () => Promise<C>,
    mount: (component: C, payload: MaintenancePayload) => void,
    reload: () => void = () => window.location.reload(),
): (payload: MaintenancePayload) => Promise<boolean> {
    let shown: Promise<boolean> | null = null;
    return (payload) => {
        shown ??= load()
            .then((component) => {
                mount(component, payload);
                return true;
            })
            .catch(() => {
                reload();
                return false;
            });
        return shown;
    };
}

interface InertiaInvalidEvent {
    preventDefault(): void;
    detail: { response: { status: number; headers?: HeaderBag; data?: unknown } };
}

/**
 * Branche l'écran XmMaintenance (P13-08) : visite Inertia refusée par la maintenance (évènement `invalid`, au lieu de la
 * modale d'erreur d'Inertia) et appel d'API refusé (évènement `xm:maintenance` de useApi, au lieu d'un toast).
 * Renvoie la fonction de désabonnement.
 */
export function bindMaintenance(
    target: Pick<Window, 'addEventListener' | 'removeEventListener'>,
    router: { on(type: 'invalid', callback: (event: InertiaInvalidEvent) => void): () => void },
    show: (payload: MaintenancePayload) => unknown,
): () => void {
    const offInvalid = router.on('invalid', (event) => {
        const { status, headers, data } = event.detail.response;
        if (!isMaintenanceResponse(status, headers, data)) return;
        event.preventDefault();
        show(data && typeof data === 'object' ? (data as MaintenancePayload) : {});
    });
    const onEvent = (event: Event) => show(((event as CustomEvent<MaintenancePayload>).detail ?? {}) as MaintenancePayload);
    target.addEventListener(MAINTENANCE_EVENT, onEvent);
    return () => {
        offInvalid();
        target.removeEventListener(MAINTENANCE_EVENT, onEvent);
    };
}
