// Logique de la recherche membre côté client (lot recherche-membre, P8-13), sans Vue ni DOM pour être testée seule :
// terme assaini comme le serveur (App\Support\ListingQuery::search : caractères de contrôle retirés, espaces normalisés,
// longueur bornée), seuil des suggestions, adresse de la page /recherche, et « suggesteur » à anti-rebond qui n'envoie
// qu'une requête à la fois (la précédente est annulée à chaque frappe) et ignore une réponse arrivée après une frappe
// plus récente, terme confié en mémoire à la page en mode discret. **Aucun historique** : rien n'est écrit dans
// localStorage, sessionStorage ni IndexedDB.

/**
 * Valeurs de repli seulement : les bornes réelles viennent du serveur (`xomiton.search.min_length` / `max_length`,
 * prop partagée `ui.search` pour l'en-tête, `limits` de la page /recherche) et sont passées en argument.
 */
export const MIN_LENGTH = 2;
export const MAX_LENGTH = 80;
/** Anti-rebond des suggestions (UI_UX_DESIGN_SYSTEM.md §4 : recherche en direct à 300 ms). */
export const DEBOUNCE_MS = 300;

const CONTROL = /[\p{Cc}\p{Cf}]+/gu;

export function cleanTerm(raw: string, max = MAX_LENGTH): string {
    const clean = raw.replace(CONTROL, ' ').replace(/\s+/gu, ' ').trim();
    return Array.from(clean).slice(0, max).join('').trim();
}

/** Assez long pour demander des suggestions (en caractères, pas en unités UTF-16). */
export function canSuggest(term: string, min = MIN_LENGTH): boolean {
    return Array.from(term).length >= min;
}

/** Adresse de la page de résultats (`?q=` puis `?type=` pour une famille). */
export function searchHref(base: string, q: string, type?: string | null): string {
    const params = new URLSearchParams();
    if (q !== '') params.set('q', q);
    if (type) params.set('type', type);
    const query = params.toString();
    return query ? `${base}?${query}` : base;
}

// Mode discret (profile.discreet_mode, Q84) : le terme ne passe pas par l'adresse `/recherche?q=` (historique du
// navigateur, journaux d'accès). L'en-tête le confie à la page en mémoire du module, reprise une seule fois.
let heldTerm: string | null = null;

export function holdTerm(q: string): void {
    heldTerm = q;
}

export function takeHeldTerm(): string | null {
    const q = heldTerm;
    heldTerm = null;
    return q;
}

export interface SuggesterOptions<T> {
    load: (term: string, signal: AbortSignal) => Promise<T>;
    onStart?: (term: string) => void;
    onResult: (result: T, term: string) => void;
    /** Terme trop court : liste à vider, sans requête. */
    onClear?: () => void;
    onError?: (error: unknown, term: string) => void;
    delay?: number;
    min?: number;
    timers?: { set: (fn: () => void, ms: number) => unknown; clear: (id: unknown) => void };
}

/**
 * Suggestions à la frappe : `schedule(term)` après chaque saisie, `cancel()` à la fermeture. Un terme trop court annule
 * tout et appelle `onClear` sans requête.
 */
export function createSuggester<T>(options: SuggesterOptions<T>) {
    const delay = options.delay ?? DEBOUNCE_MS;
    const min = options.min ?? MIN_LENGTH;
    const timers = options.timers ?? {
        set: (fn: () => void, ms: number) => setTimeout(fn, ms),
        clear: (id: unknown) => clearTimeout(id as ReturnType<typeof setTimeout>),
    };
    let timer: unknown = null;
    let controller: AbortController | null = null;
    let generation = 0;

    const stop = () => {
        if (timer !== null) timers.clear(timer);
        timer = null;
        controller?.abort();
        controller = null;
    };

    async function run(term: string, current: number) {
        controller = new AbortController();
        const { signal } = controller;
        options.onStart?.(term);
        try {
            const result = await options.load(term, signal);
            if (current === generation && !signal.aborted) options.onResult(result, term);
        } catch (error) {
            if (signal.aborted || (error instanceof DOMException && error.name === 'AbortError')) return;
            if (current === generation) options.onError?.(error, term);
        }
    }

    return {
        schedule(term: string): void {
            generation += 1;
            stop();
            if (!canSuggest(term, min)) {
                options.onClear?.();
                return;
            }
            const current = generation;
            timer = timers.set(() => {
                timer = null;
                void run(term, current);
            }, delay);
        },
        cancel(): void {
            generation += 1;
            stop();
        },
    };
}
