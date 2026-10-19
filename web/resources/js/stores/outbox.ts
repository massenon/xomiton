// Store Pinia `outbox` (P6-05, ADR-26) : adossé à la boîte d'envoi commune resources/js/shared/outbox.js (P5-10), sans
// seconde implémentation. Mêmes options que shared/outbox-boot.js : `account` (empreinte du compte de la page, props
// partagées ui.outbox.account) et `ttlDays` (xomiton.alerts.draft_days). outbox.js (IndexedDB) et les avis
// public/outbox-notices.js (code de suivi d'un signalement envoyé au rejeu, affiché une seule fois) sont chargés à la
// demande : au démarrage si un envoi attend (`xm.outbox.pending`), sinon au premier `add()`. Le compteur `pending`
// suit les évènements window `xm:outbox:queued` / `xm:outbox:flushed` émis par outbox.js ; `byType` (P9-07) compte les
// envois en attente du compte de la page par type (file d'attente visible de la collecte, messages « en attente »).
// `queue(type, payload)` (P9-07) : signalement, SOS, collecte (`POST /api/v1/pair/cases`, `client_uuid` = clé) ou
// message (URL de la conversation) ; un SOS part tout de suite si le réseau est là (outbox.js `express`).
import { defineStore } from 'pinia';
import { ref } from 'vue';

type OutboxCore = typeof import('../shared/outbox.js');
type OutboxNotices = typeof import('../public/outbox-notices.js');
type Box = ReturnType<OutboxCore['getOutbox']>;

export interface OutboxStartOptions {
    account: string | null;
    ttlDays?: number;
    /** Textes neutres des avis (lang/fr/offline.php `offline.outbox.*` + report_url, urgences_url). */
    texts?: Record<string, string>;
    importer?: () => Promise<[OutboxCore, OutboxNotices]>;
    storage?: Storage | null;
    target?: Window;
}

export interface FlushDetail {
    sent: Array<{ id: string; type: string; status: number; data?: unknown }>;
    failed: Array<{ id: string; type: string; status: number; data?: unknown }>;
    pending: number;
}

const PENDING_KEY = 'xm.outbox.pending'; // = PENDING_KEY de shared/outbox.js

/** Types d'envoi de la boîte d'envoi (shared/outbox.js : ENDPOINTS, PRIORITIES). */
export type OutboxType = 'sos' | 'signalement' | 'collecte' | 'message' | 'post';

const defaultImporter = () => Promise.all([import('../shared/outbox.js'), import('../public/outbox-notices.js')]);

export const useOutboxStore = defineStore('outbox', () => {
    const pending = ref(0);
    const byType = ref<Record<string, number>>({});
    const lastFlush = ref<FlushDetail | null>(null);
    const ready = ref(false);
    let options: OutboxStartOptions | null = null;
    let loading: Promise<{ box: Box; core: OutboxCore }> | null = null;
    let listening: AbortController | null = null;

    const load = () => {
        if (!options) return Promise.reject(new Error('outbox: start() d’abord'));
        const opts = options;
        return (loading ||= (opts.importer ?? defaultImporter)().then(([core, notices]) => {
            const box = core.getOutbox(opts.account === null
                ? { ttlDays: opts.ttlDays }
                : { account: () => opts.account as string, ttlDays: opts.ttlDays });
            notices.mountOutboxNotices(opts.texts ?? {});
            box.start();
            ready.value = true;
            return { box, core };
        }));
    };

    const storedPending = (storage: Storage | null | undefined): number => {
        try {
            return Number(storage?.getItem(PENDING_KEY)) || 0;
        } catch {
            return 0;
        }
    };

    /** Branche la boîte d'envoi de la page ; charge outbox.js tout de suite si un envoi attend. */
    function start(opts: OutboxStartOptions): Promise<void> {
        options = opts;
        const target = opts.target ?? (typeof window !== 'undefined' ? window : undefined);
        if (target && !listening) {
            listening = new AbortController();
            target.addEventListener('xm:outbox:queued', (event) => {
                pending.value = Number((event as CustomEvent).detail?.pending) || pending.value + 1;
                recount();
            }, { signal: listening.signal });
            target.addEventListener('xm:outbox:flushed', (event) => {
                const detail = (event as CustomEvent<FlushDetail>).detail;
                lastFlush.value = detail ?? null;
                pending.value = Number(detail?.pending) || 0;
                recount();
            }, { signal: listening.signal });
        }
        const storage = opts.storage === undefined ? (() => { try { return target?.localStorage ?? null; } catch { return null; } })() : opts.storage;
        pending.value = storedPending(storage);
        if (pending.value > 0) return load().then(() => undefined).catch(() => undefined);
        return Promise.resolve();
    }

    /** Met en file (forme de shared/outbox.js : { type, url, body, idempotencyKey, … }) puis tente l'envoi. */
    async function add(entry: Record<string, unknown>) {
        const { box } = await load();
        const withAccount = options && options.account !== null && typeof entry.account !== 'string'
            ? { ...entry, account: options.account }
            : entry;
        return box.add(withAccount);
    }

    /**
     * Met en file un envoi typé (P9-07) : `signalement`, `sos`, `collecte` (point d'API déduit du type) ou `message`
     * (`url` de la conversation obligatoire) ; clé neuve sauf `idempotencyKey` donnée (un nouvel essai garde la sienne).
     */
    async function queue(type: OutboxType, payload: Record<string, unknown>, options: { idempotencyKey?: string; url?: string } = {}) {
        const { core } = await load();
        const idempotencyKey = options.idempotencyKey ?? core.uuid();
        return add({ type, payload, idempotencyKey, ...(options.url ? { url: options.url } : {}) });
    }

    async function flush(force = false) {
        const { box } = await load();
        return box.flush({ force });
    }

    async function refresh() {
        const { box } = await load();
        pending.value = await box.count();
        await recount();
        return pending.value;
    }

    /** Envois en attente du compte de la page, par type (jamais ceux d'un autre compte : appareil partagé). */
    async function recount() {
        if (!loading) return byType.value;
        const all = await entries().catch(() => [] as Array<Record<string, unknown>>);
        byType.value = all.reduce<Record<string, number>>((acc, entry) => {
            const type = String(entry.type ?? 'default');
            acc[type] = (acc[type] ?? 0) + 1;
            return acc;
        }, {});
        return byType.value;
    }

    /**
     * Entrées en attente (P8-01 : messages affichés « en attente d'envoi » après un rechargement), filtrées par type et,
     * quand la page a un compte, sans celles d'un **autre** compte (appareil partagé) — même règle que `heldFor` de
     * shared/outbox.js : une entrée sans compte (page sans session) reste visible.
     */
    async function entries(type?: string): Promise<Array<Record<string, unknown>>> {
        const { box } = await load();
        const all = (await box.list()) as Array<Record<string, unknown>>;
        const account = options?.account ?? null;
        const mine = (entry: Record<string, unknown>) => account === null || typeof entry.account !== 'string' || entry.account === '' || entry.account === account;
        return all.filter((entry) => (!type || entry.type === type) && mine(entry));
    }

    /** Retire une entrée (message abandonné par la personne) ; rend le nombre restant. */
    async function remove(id: string) {
        const { box } = await load();
        await box.remove(id);
        return refresh();
    }

    function stop() {
        listening?.abort();
        listening = null;
    }

    return { pending, byType, lastFlush, ready, start, add, queue, flush, refresh, recount, entries, remove, stop };
});
