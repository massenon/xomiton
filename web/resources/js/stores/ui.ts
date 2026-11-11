// Store Pinia `ui` (P6-05) : état d'interface partagé par la coquille — réseau (bandeau hors connexion) et file des
// toasts affichés par <XmToast> (useToast()). Aucune donnée personnelle ici.
import { defineStore } from 'pinia';
import type { ToastType } from '@/app/types';

export interface ToastItem {
    id: number;
    message: string;
    type: ToastType;
    /** role="alert" + aria-live="assertive" : erreurs et SOS ; sinon role="status" + polite. */
    urgent: boolean;
    delay: number;
}

/** Durée d'affichage d'un toast (NOTIFICATIONS_INSTRUCTION §4 : ≥ 4,5 s ; xomiton-ui.js : 4 500 ms). */
export const TOAST_DELAY = 4500;
/** Au-delà, le plus ancien est retiré : jamais un mur de toasts. */
export const TOAST_MAX = 4;

let nextId = 1;

export const useUiStore = defineStore('ui', {
    state: () => ({
        online: typeof navigator === 'undefined' ? true : navigator.onLine !== false,
        toasts: [] as ToastItem[],
    }),
    actions: {
        setOnline(online: boolean) {
            this.online = online;
        },
        pushToast(message: string, type: ToastType = 'primary', options: { urgent?: boolean; delay?: number } = {}): number {
            const id = nextId++;
            this.toasts.push({
                id,
                message,
                type,
                urgent: options.urgent ?? type === 'danger',
                delay: options.delay ?? TOAST_DELAY,
            });
            while (this.toasts.length > TOAST_MAX) this.toasts.shift();
            return id;
        },
        dismissToast(id: number) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    },
});
