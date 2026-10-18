// Sondage court (P8-01, D7 : ni WebSocket ni processus persistant sur cPanel ; DECISIONS.md : 4 s dans une conversation
// ouverte seulement). Un passage à la fois ; rien tant que l'onglet est caché ou le réseau absent (reprise immédiate au
// retour) ; recul croissant après une erreur (jusqu'à 8 fois l'intervalle), retour à l'intervalle au premier succès.

export interface PollerOptions {
    /** Intervalle en millisecondes, ou fonction relue à chaque passage (repli du sondage de la liste à 30 s). */
    interval: number | (() => number);
    run: () => Promise<unknown>;
    doc?: Pick<Document, 'visibilityState' | 'addEventListener' | 'removeEventListener'>;
    win?: Pick<Window, 'addEventListener' | 'removeEventListener'> & { navigator?: { onLine?: boolean } };
    maxBackoff?: number;
}

export interface Poller {
    start: () => void;
    stop: () => void;
    /** Passage immédiat (après un envoi, à l'ouverture), sans attendre l'intervalle. */
    now: () => Promise<void>;
    readonly running: boolean;
}

export function createPoller(options: PollerOptions): Poller {
    const doc = options.doc ?? (typeof document !== 'undefined' ? document : undefined);
    const win = options.win ?? (typeof window !== 'undefined' ? window : undefined);
    const maxBackoff = options.maxBackoff ?? 8;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let busy: Promise<void> | null = null;
    let running = false;
    let failures = 0;

    const visible = () => !doc || doc.visibilityState !== 'hidden';
    const online = () => win?.navigator?.onLine !== false;

    const schedule = () => {
        if (timer) clearTimeout(timer);
        timer = null;
        if (!running) return;
        const factor = failures === 0 ? 1 : Math.min(maxBackoff, 2 ** failures);
        const base = typeof options.interval === 'function' ? options.interval() : options.interval;
        timer = setTimeout(() => { tick().catch(() => undefined); }, base * factor);
    };

    const tick = async (): Promise<void> => {
        if (busy) return busy;
        if (!running || !visible() || !online()) {
            schedule();
            return;
        }
        busy = options.run().then(() => { failures = 0; }, () => { failures += 1; })
            .finally(() => { busy = null; schedule(); });
        return busy;
    };

    const onVisible = () => { if (visible()) tick().catch(() => undefined); };
    const onOnline = () => { failures = 0; tick().catch(() => undefined); };

    return {
        start() {
            if (running) return;
            running = true;
            doc?.addEventListener('visibilitychange', onVisible);
            win?.addEventListener('online', onOnline);
            schedule();
        },
        stop() {
            running = false;
            if (timer) clearTimeout(timer);
            timer = null;
            doc?.removeEventListener('visibilitychange', onVisible);
            win?.removeEventListener('online', onOnline);
        },
        now: () => tick(),
        get running() { return running; },
    };
}
