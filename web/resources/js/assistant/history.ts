// Historique de la page /assistant (P8-11, prototype assistant.php : « La conversation est gardée uniquement dans cet
// onglet ») : sessionStorage par compte (`xm_assistant_v1:<id>` : un autre compte dans le même onglet ne voit rien),
// jamais en mode discret (rien dans le navigateur), effacé par « Effacer la conversation », à la déconnexion
// (app/logout.ts, même préfixe sans importer ce module) et, pour les autres comptes, à l'ouverture de la page.
// Stockage indisponible (navigation privée, quota, aperçu) : la page marche sans, rien n'est levé.

export const HISTORY_PREFIX = 'xm_assistant_v1';

export interface ChatLink {
    label: string;
    url: string;
    variant: string;
}

export interface ChatMessage {
    id: number;
    who: 'me' | 'bot';
    text: string;
    kind?: string;
    links?: ChatLink[];
    suggestions?: string[];
    escalation?: boolean;
    error?: boolean;
    retry?: string;
}

export interface StoredChat {
    messages: ChatMessage[];
    conversationId: number | null;
    persist: boolean;
}

const storage = (): Storage | null => {
    try {
        return typeof window === 'undefined' ? null : window.sessionStorage;
    } catch {
        return null;
    }
};

export const historyKey = (userId: number | string | null | undefined): string => `${HISTORY_PREFIX}:${userId ?? 'anonyme'}`;

const isMessage = (value: unknown): value is ChatMessage => {
    const m = value as ChatMessage;
    return Boolean(m) && typeof m === 'object' && (m.who === 'me' || m.who === 'bot') && typeof m.text === 'string' && typeof m.id === 'number';
};

export function loadHistory(key: string): StoredChat | null {
    try {
        const raw = storage()?.getItem(key);
        if (!raw) return null;
        const parsed = JSON.parse(raw) as Partial<StoredChat>;
        const messages = Array.isArray(parsed.messages) ? parsed.messages.filter(isMessage) : [];
        return {
            messages,
            conversationId: typeof parsed.conversationId === 'number' ? parsed.conversationId : null,
            persist: parsed.persist === true,
        };
    } catch {
        return null;
    }
}

export function saveHistory(key: string, chat: StoredChat): void {
    try {
        storage()?.setItem(key, JSON.stringify(chat));
    } catch {
        // Quota dépassé ou stockage refusé : l'historique reste en mémoire pour cette visite.
    }
}

/** Retire les historiques de l'onglet dont la clé vérifie `drop`. */
function forgetWhere(drop: (name: string) => boolean): void {
    const store = storage();
    if (!store) return;
    try {
        const keys: string[] = [];
        for (let i = 0; i < store.length; i += 1) {
            const name = store.key(i);
            if (name && name.startsWith(HISTORY_PREFIX) && drop(name)) keys.push(name);
        }
        keys.forEach((name) => store.removeItem(name));
    } catch {
        // Stockage indisponible : rien à effacer.
    }
}

/** Efface l'historique d'un compte, ou de tous les comptes de l'onglet (mode discret). */
export function forgetHistory(key: string | null = null): void {
    forgetWhere((name) => key === null || name === key);
}

/** Efface l'historique des autres comptes laissé dans l'onglet (session expirée, déconnexion hors de l'espace Vue). */
export function forgetOtherHistories(key: string): void {
    forgetWhere((name) => name !== key);
}
