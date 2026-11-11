// useToast() (P6-04, NOTIFICATIONS_INSTRUCTION §1 et §4) : retour immédiat d'une action dans l'espace Vue, rendu par
// <XmToast> (Bootstrap Toast, haut-droite, 4,5 s, pause au survol, fermable). Polite pour succès et info, assertive pour
// les erreurs et le SOS. Un toast n'est jamais la seule source d'une information : l'écran garde l'état.
import { useUiStore } from '@/stores/ui';
import type { ToastType } from './types';

export interface ToastOptions {
    urgent?: boolean;
    delay?: number;
}

export function useToast() {
    const ui = useUiStore();
    const show = (message: string, type: ToastType = 'primary', options: ToastOptions = {}): number | null =>
        message ? ui.pushToast(message, type, options) : null;

    return {
        show,
        success: (message: string, options?: ToastOptions) => show(message, 'success', options),
        info: (message: string, options?: ToastOptions) => show(message, 'info', options),
        warning: (message: string, options?: ToastOptions) => show(message, 'warning', options),
        error: (message: string, options?: ToastOptions) => show(message, 'danger', { urgent: true, ...options }),
        /** Retour du SOS : annoncé immédiatement (assertive). */
        sos: (message: string, options?: ToastOptions) => show(message, 'danger', { urgent: true, ...options }),
        dismiss: (id: number) => ui.dismissToast(id),
    };
}
