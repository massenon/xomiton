// Fil d'une conversation (P8-01), sans dépendance à Vue : fusion des messages du serveur (historique, sondage court) et
// des messages locaux en attente, rapprochés par `client_uuid` (un rejeu de la boîte d'envoi ne crée jamais de
// doublon à l'écran non plus), regroupement par jour, libellés d'heure, filtre local de la liste.
import type { ChatMessage, Conversation } from './types';

const key = (message: ChatMessage): string => (message.id !== null ? `id:${message.id}` : `uuid:${message.client_uuid}`);

/** Messages du serveur fusionnés (le plus récent l'emporte), triés du plus ancien au plus récent. */
export function mergeMessages(current: ChatMessage[], incoming: ChatMessage[]): ChatMessage[] {
    const byKey = new Map<string, ChatMessage>();
    [...current, ...incoming].forEach((message) => {
        if (message.id === null) return;
        byKey.set(key(message), message);
    });
    return [...byKey.values()].sort((a, b) => (a.id ?? 0) - (b.id ?? 0));
}

/** Messages locaux encore attendus : ceux dont le serveur n'a pas encore rendu le `client_uuid`. */
export function reconcilePending(pending: ChatMessage[], server: ChatMessage[]): ChatMessage[] {
    const confirmed = new Set(server.map((message) => message.client_uuid).filter(Boolean));
    return pending.filter((message) => !confirmed.has(message.client_uuid));
}

/** Plus grand identifiant connu (curseur `after` du sondage court). */
export function lastId(messages: ChatMessage[]): number {
    return messages.reduce((max, message) => Math.max(max, message.id ?? 0), 0);
}

/** « Vu » de ses propres messages d'une conversation privée d'après le curseur de lecture de l'autre personne. */
export function applyReadCursor(messages: ChatMessage[], readUpTo: number | null | undefined): ChatMessage[] {
    if (readUpTo === null || readUpTo === undefined) return messages;
    return messages.map((message) => (message.mine && message.id !== null
        ? { ...message, status: message.id <= readUpTo ? 'seen' : 'sent' }
        : message));
}

const sameDay = (a: Date, b: Date) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

/** Clé de jour (AAAA-MM-JJ local) d'un message ; les messages locaux sont « aujourd'hui ». */
export function dayKey(message: ChatMessage, now = new Date()): string {
    const date = message.created_at ? new Date(message.created_at) : now;
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** Libellé d'un séparateur de jour : « Aujourd'hui », « Hier » ou la date longue. */
export function dayLabel(iso: string | null, labels: { today: string; yesterday: string }, now = new Date(), locale = 'fr'): string {
    const date = iso ? new Date(iso) : now;
    if (sameDay(date, now)) return labels.today;
    const yesterday = new Date(now);
    yesterday.setDate(now.getDate() - 1);
    if (sameDay(date, yesterday)) return labels.yesterday;
    return date.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long', ...(date.getFullYear() !== now.getFullYear() ? { year: 'numeric' } : {}) });
}

/** Heure « 15:04 » d'un message ; date courte s'il n'est pas du jour (liste). */
export function timeLabel(iso: string | null, now = new Date(), locale = 'fr', withDay = false): string {
    if (!iso) return '';
    const date = new Date(iso);
    if (withDay && !sameDay(date, now)) return date.toLocaleDateString(locale, { day: 'numeric', month: 'short' });
    return date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

/** Groupes de messages consécutifs du même jour, pour les séparateurs. */
export function groupByDay(messages: ChatMessage[], now = new Date()): Array<{ key: string; iso: string | null; messages: ChatMessage[] }> {
    const groups: Array<{ key: string; iso: string | null; messages: ChatMessage[] }> = [];
    messages.forEach((message) => {
        const k = dayKey(message, now);
        const last = groups[groups.length - 1];
        if (last && last.key === k) last.messages.push(message);
        else groups.push({ key: k, iso: message.created_at, messages: [message] });
    });
    return groups;
}

/** Filtre immédiat de la liste (nom affiché), en attendant la recherche serveur. */
export function filterConversations(conversations: Conversation[], term: string): Conversation[] {
    const needle = term.trim().toLocaleLowerCase('fr');
    if (needle === '') return conversations;
    return conversations.filter((c) => c.name.toLocaleLowerCase('fr').includes(needle)
        || (c.participants ?? []).some((p) => !p.is_me && p.pseudo.toLocaleLowerCase('fr').includes(needle)));
}

/** Remplace ou ajoute des conversations (par id), dernière activité en tête. */
export function upsertConversations(current: Conversation[], incoming: Conversation[]): Conversation[] {
    const byId = new Map(current.map((c) => [c.id, c] as const));
    incoming.forEach((c) => byId.set(c.id, c));
    const at = (c: Conversation) => Date.parse(c.last_message_at || c.created_at || '') || 0;
    return [...byId.values()].sort((a, b) => at(b) - at(a));
}

/** Nombre de conversations ayant un message non lu (compteur « Messages » de l'en-tête). */
export function unreadConversations(conversations: Conversation[]): number {
    return conversations.filter((c) => c.unread_count > 0).length;
}
