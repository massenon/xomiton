// Fiches de collecte refusées par le serveur après leur mise en file (P9-04, lot espace-pair) : la boîte d'envoi retire
// une entrée refusée (422 au rejeu, règle changée entre-temps) et ne rend que les messages du serveur ; pour que la pair·e
// ne ressaisisse pas tout, l'écran garde EN MÉMOIRE (jamais sur l'appareil : les notes en font partie) la fiche mise en
// file, par identifiant d'entrée, et la rend avec les champs refusés quand le refus arrive : « Corriger et renvoyer »
// la remet dans le formulaire. Le module vit tant que l'application Vue vit : un refus tombé pendant que la pair·e était
// sur une autre page est affiché au retour sur `/collecte` (lastFlush, une seule fois). Un rechargement complet de la
// page efface cette mémoire (la fiche refusée n'existe plus nulle part : seuls les messages restent dans la boîte).
import { ref } from 'vue';
import type { CollecteValues } from './types';

export interface Rejection {
    id: string;
    /** Messages du serveur (un par champ refusé), ou le message générique. */
    messages: string[];
    /** Erreurs par champ du formulaire (clés = champs de CollecteValues). */
    errors: Record<string, string>;
    /** Fiche mise en file ; null si l'entrée n'est plus connue (rechargement complet). */
    values: CollecteValues | null;
}

interface FailedItem {
    id: string;
    type: string;
    errors?: Record<string, string[]>;
}

export interface FlushLike {
    sent: unknown[];
    failed: Array<{ id: string; type: string }>;
}

const queued = new Map<string, CollecteValues>();
const handled = new WeakSet<object>();

/** Refus en attente d'une correction ; partagé entre les montages de la page. */
export const rejections = ref<Rejection[]>([]);

const FIELDS = ['kind', 'occurred_on', 'violence_type_id', 'zone_id', 'commune_id', 'population_id', 'sex', 'age_range', 'referral', 'notes'];

/** Garde la fiche mise en file (copie) pour pouvoir la rendre si le serveur la refuse. */
export function remember(id: string, values: CollecteValues): void {
    queued.set(id, { ...values });
}

/** Vrai la première fois qu'un passage de la boîte d'envoi est vu (WeakSet) : pas de double annonce au retour sur la page. */
export function claim(detail: object): boolean {
    if (handled.has(detail)) return false;
    handled.add(detail);
    return true;
}

/** Enregistre les refus d'un passage ; rend les nouveaux. L'entrée mémorisée est consommée. */
export function recordFailures(failed: FailedItem[], generic: string): Rejection[] {
    const added = failed.filter((item) => item.type === 'collecte').map<Rejection>((item) => {
        const errors: Record<string, string> = {};
        const messages: string[] = [];
        for (const [field, list] of Object.entries(item.errors ?? {})) {
            const message = Array.isArray(list) ? String(list[0] ?? '') : '';
            if (message === '') continue;
            messages.push(message);
            if (FIELDS.includes(field)) errors[field] = message;
        }
        const values = queued.get(item.id) ?? null;
        queued.delete(item.id);
        return { id: item.id, messages: messages.length > 0 ? messages : [generic], errors, values };
    });
    rejections.value = [...rejections.value, ...added];
    return added;
}

/** Une entrée envoyée n'a plus besoin de sa copie. */
export function forget(ids: string[]): void {
    ids.forEach((id) => queued.delete(id));
}

export function dismiss(id: string): void {
    rejections.value = rejections.value.filter((item) => item.id !== id);
}

/** Tests : mémoire vidée. */
export function resetRejections(): void {
    queued.clear();
    rejections.value = [];
}
