// Piège à focus d'une fenêtre modale maison (mode animation) : Tab et Maj+Tab restent dans le conteneur.
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

export function focusables(container: HTMLElement): HTMLElement[] {
    return [...container.querySelectorAll<HTMLElement>(FOCUSABLE)].filter((element) => element.getAttribute('aria-hidden') !== 'true');
}

/** À appeler sur la touche Tab : renvoie true si le focus a été replacé. */
export function trapTab(event: KeyboardEvent, container: HTMLElement): boolean {
    if (event.key !== 'Tab') return false;
    const items = focusables(container);
    if (items.length === 0) {
        event.preventDefault();
        container.focus();
        return true;
    }
    const first = items[0] as HTMLElement;
    const last = items[items.length - 1] as HTMLElement;
    const active = document.activeElement;
    if (!container.contains(active)) {
        event.preventDefault();
        first.focus();
        return true;
    }
    if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
        return true;
    }
    if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
        return true;
    }
    return false;
}
