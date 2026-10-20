// Store Pinia `drafts` (P6-05, DEVELOPMENT_STANDARDS §4) : brouillons de l'espace Vue (publication, commentaire…) en
// IndexedDB, dans la base de /signaler (resources/js/public/signaler.js : `xomiton-drafts`, magasin `drafts`, version 1)
// sous des clés `vue:<compte>:<nom>` — jamais lus par un autre compte du même appareil. Contenu en clair sur
// l'appareil : chaque brouillon expire après `ttlDays` jours (xomiton.alerts.draft_days) et `clearAccount()` efface ceux
// du compte, appelé par la déconnexion Vue (app/logout.ts). Comme le brouillon de /signaler (DRAFT_FIELDS : ni contact,
// ni preuves, ni position), jamais enregistrés : les champs de EXCLUDED_DRAFT_FIELDS (à toute profondeur), ceux passés
// dans `exclude`, et tout fichier (Blob / File). idb est chargé à la demande, hors du paquet app.ts.
import { defineStore } from 'pinia';
import type { IDBPDatabase } from 'idb';

export const DRAFT_DB = 'xomiton-drafts';
export const DRAFT_STORE = 'drafts';
const DAY_MS = 86_400_000;

/**
 * Champs jamais écrits dans un brouillon : secrets (mot de passe, code, PIN, jetons), coordonnées d'une personne
 * (contact, téléphone, e-mail, contact de confiance), position et preuves ou pièces jointes.
 */
export const EXCLUDED_DRAFT_FIELDS: readonly string[] = [
    'password', 'password_confirmation', 'current_password', 'pin', 'code', 'otp', 'token', '_token', 'idempotency_key',
    'contact', 'phone', 'email', 'trusted_contact_phone', 'trusted_contact_name',
    'lat', 'lng', 'latitude', 'longitude', 'accuracy', 'position', 'location', 'geolocation',
    'evidence', 'evidences', 'proofs', 'files', 'attachments', 'media',
];

/** Copie JSON du brouillon sans les champs exclus ni les fichiers. */
export function sanitizeDraft<T>(value: T, exclude: readonly string[] = []): T {
    const excluded = new Set([...EXCLUDED_DRAFT_FIELDS, ...exclude]);
    const json = JSON.stringify(value, function (key, item: unknown) {
        if (key !== '' && !Array.isArray(this) && excluded.has(key)) return undefined;
        if (typeof Blob !== 'undefined' && item instanceof Blob) return undefined;
        return item;
    });
    return (json === undefined ? null : JSON.parse(json)) as T;
}

export interface DraftRecord<T = unknown> {
    value: T;
    savedAt: number;
    /** Durée de vie propre à ce brouillon (jours), jamais au-delà de celle du store. */
    ttlDays?: number;
}

type Opener = () => Promise<IDBPDatabase>;

const defaultOpen: Opener = async () => {
    const { openDB } = await import('idb');
    return openDB(DRAFT_DB, 1, {
        upgrade(db) {
            if (!db.objectStoreNames.contains(DRAFT_STORE)) db.createObjectStore(DRAFT_STORE);
        },
    });
};

let opener: Opener = defaultOpen;
let database: Promise<IDBPDatabase> | null = null;
const db = () => (database ||= opener());

/** Tests : base factice (fake-indexeddb) ; `null` rétablit l'ouverture par défaut. */
export function setDraftOpener(open: Opener | null): void {
    opener = open ?? defaultOpen;
    database = null;
}

export const useDraftsStore = defineStore('drafts', {
    state: () => ({
        account: '',
        ttlDays: 7,
    }),
    actions: {
        configure(account: string | null, ttlDays = 7) {
            this.account = account || 'guest';
            this.ttlDays = ttlDays > 0 ? ttlDays : 7;
        },
        key(name: string): string {
            return `vue:${this.account || 'guest'}:${name}`;
        },
        /** Durée de vie effective d'un brouillon : la sienne si elle est plus courte que celle du store. */
        lifetime(record: DraftRecord): number {
            return record.ttlDays && record.ttlDays > 0 ? Math.min(record.ttlDays, this.ttlDays) : this.ttlDays;
        },
        async save<T>(name: string, value: T, now = Date.now(), exclude: readonly string[] = [], ttlDays?: number): Promise<void> {
            const record: DraftRecord<T> = { value: sanitizeDraft(value, exclude), savedAt: now, ...(ttlDays && ttlDays > 0 ? { ttlDays } : {}) };
            await (await db()).put(DRAFT_STORE, record, this.key(name));
        },
        async load<T>(name: string, now = Date.now()): Promise<T | null> {
            const record = (await (await db()).get(DRAFT_STORE, this.key(name))) as DraftRecord<T> | undefined;
            if (!record) return null;
            if (now - record.savedAt > this.lifetime(record) * DAY_MS) {
                await this.remove(name);
                return null;
            }
            return record.value;
        },
        async remove(name: string): Promise<void> {
            await (await db()).delete(DRAFT_STORE, this.key(name));
        },
        /** Brouillons expirés de tous les comptes de l'appareil (clés `vue:` seulement : jamais celui de /signaler). */
        async purge(now = Date.now()): Promise<number> {
            const base = await db();
            let removed = 0;
            for (const key of await base.getAllKeys(DRAFT_STORE)) {
                if (typeof key !== 'string' || !key.startsWith('vue:')) continue;
                const record = (await base.get(DRAFT_STORE, key)) as DraftRecord | undefined;
                if (!record || now - record.savedAt > this.lifetime(record) * DAY_MS) {
                    await base.delete(DRAFT_STORE, key);
                    removed++;
                }
            }
            return removed;
        },
        async clearAccount(): Promise<void> {
            const base = await db();
            const prefix = `vue:${this.account || 'guest'}:`;
            for (const key of await base.getAllKeys(DRAFT_STORE)) {
                if (typeof key === 'string' && key.startsWith(prefix)) await base.delete(DRAFT_STORE, key);
            }
        },
    },
});
