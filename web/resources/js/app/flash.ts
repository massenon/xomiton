// Messages flashés par Laravel (HandleInertiaRequests `flash` : `{ id, message, type }` pour `toast` et `status`),
// affichés une seule fois en toast. Dédoublonnage par l'identifiant posé côté serveur, gardé en sessionStorage : une
// page restaurée par Retour / Avant (history.state, nouvel objet page) ou rechargée depuis l'historique ne réaffiche
// jamais un ancien toast. Le statut reste en plus à l'écran (XmFlashStatus) : un toast n'est jamais la seule source.
import type { FlashMessage, SharedProps, ToastType } from './types';

export const FLASH_SEEN_KEY = 'xm:flash-seen';
const MAX_SEEN = 20;

const TOAST_TYPES: ReadonlySet<string> = new Set<ToastType>(['primary', 'secondary', 'success', 'danger', 'warning', 'info']);

/** Type connu de XmToast (classe text-bg-{type}), sinon primary : même liste que HandleInertiaRequests::TOAST_TYPES. */
export const toastType = (type: unknown): ToastType => (typeof type === 'string' && TOAST_TYPES.has(type) ? (type as ToastType) : 'primary');

type SeenStorage = Pick<Storage, 'getItem' | 'setItem'>;

const sessionStore = (): SeenStorage | null => {
    try {
        return typeof window !== 'undefined' ? window.sessionStorage : null;
    } catch {
        return null; // Stockage bloqué (navigation privée stricte) : dédoublonnage en mémoire seulement.
    }
};

function readSeen(storage: SeenStorage | null): string[] {
    try {
        const list: unknown = JSON.parse(storage?.getItem(FLASH_SEEN_KEY) || '[]');
        return Array.isArray(list) ? list.filter((id): id is string => typeof id === 'string') : [];
    } catch {
        return [];
    }
}

/** Rend la fonction à appeler avec les props de chaque page (première visite et visites Inertia). */
export function createFlashPresenter(show: (message: string, type: ToastType) => void, storage: SeenStorage | null = sessionStore()) {
    const seen = new Set<string>(readSeen(storage));
    const remember = (id: string) => {
        seen.add(id);
        try {
            storage?.setItem(FLASH_SEEN_KEY, JSON.stringify([...seen].slice(-MAX_SEEN)));
        } catch {
            // Quota ou stockage bloqué : la mémoire de l'onglet suffit.
        }
    };

    return (props: Partial<SharedProps> | undefined): void => {
        const flash = props?.flash;
        if (!flash) return;
        [flash.toast, flash.status].forEach((item: FlashMessage | null | undefined) => {
            if (!item || !item.message) return;
            if (item.id) {
                if (seen.has(item.id)) return;
                remember(item.id);
            }
            show(item.message, toastType(item.type));
        });
    };
}
