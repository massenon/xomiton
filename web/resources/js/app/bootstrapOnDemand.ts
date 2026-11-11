// Bootstrap à la demande dans l'espace membre (P4-12, ADR-18) : shared/bootstrap.js (Bootstrap + Popper) sort du paquet
// initial d'app.ts. Il est demandé dès que le navigateur est libre après le montage (requestIdleCallback, 2 s au plus),
// et, s'il n'est pas encore là, le premier geste sur un déclencheur data-bs-* (clic, ou flèche sur un menu déroulant) est
// retenu, Bootstrap chargé puis le même geste rejoué sur le même élément : menus, offcanvas, repli de l'en-tête et
// boutons Fermer se comportent comme avec un import statique. Un échec réseau rejoue quand même le geste (gestionnaires
// Vue de l'élément) et un nouveau geste refait la demande. Pendant l'attente, le déclencheur porte aria-busy="true" et
// .xm-is-waiting (curseur d'attente, app/member.css), retirés au rejeu ; un seul geste en attente par déclencheur : un
// second clic impatient est absorbé, sans quoi les deux seraient rejoués (menu ouvert puis aussitôt refermé).
import { loadBootstrap, loadedBootstrap } from '@/shared/bootstrap-lazy.js';

const TRIGGERS = '[data-bs-toggle], [data-bs-dismiss]';
const DROPDOWN = '[data-bs-toggle="dropdown"]';
const ARROWS = ['ArrowDown', 'ArrowUp'];
export const WAITING_CLASS = 'xm-is-waiting';

type Idle = (callback: () => void) => void;

const whenIdle: Idle = (callback) => {
    if (typeof window.requestIdleCallback === 'function') window.requestIdleCallback(callback, { timeout: 2000 });
    else window.setTimeout(callback, 1);
};

/**
 * Installe l'interception des gestes et planifie le chargement ; rend la fonction qui retire les écouteurs (retirés
 * d'eux-mêmes une fois Bootstrap chargé).
 */
export function bootstrapOnDemand(
    doc: Document = document,
    { load = loadBootstrap, loaded = loadedBootstrap, idle = whenIdle }: { load?: () => Promise<unknown>; loaded?: () => unknown; idle?: Idle } = {},
): () => void {
    let replaying = false;
    /** Déclencheurs dont le geste attend Bootstrap. */
    const waiting = new Set<HTMLElement>();

    const stop = () => {
        doc.removeEventListener('click', onClick, true);
        doc.removeEventListener('keydown', onKeydown, true);
    };

    const replay = (run: () => void) => {
        replaying = true;
        try {
            run();
        } finally {
            replaying = false;
        }
    };

    /** Retient l'évènement s'il vise un déclencheur et que Bootstrap manque ; rejoue après le chargement. */
    const hold = (event: Event, selector: string, run: (target: HTMLElement) => void) => {
        if (replaying) return;
        if (loaded()) {
            stop();
            return;
        }
        const target = event.target instanceof Element ? event.target.closest<HTMLElement>(selector) : null;
        if (!target) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (waiting.has(target)) return;
        waiting.add(target);
        const busy = target.getAttribute('aria-busy');
        target.setAttribute('aria-busy', 'true');
        target.classList.add(WAITING_CLASS);
        const again = () => {
            waiting.delete(target);
            if (busy === null) target.removeAttribute('aria-busy');
            else target.setAttribute('aria-busy', busy);
            target.classList.remove(WAITING_CLASS);
            if (loaded()) stop();
            if (target.isConnected) replay(() => run(target));
        };
        load().then(again, again);
    };

    function onClick(event: MouseEvent) {
        hold(event, TRIGGERS, (target) => target.click());
    }

    function onKeydown(event: KeyboardEvent) {
        if (!ARROWS.includes(event.key)) return;
        hold(event, DROPDOWN, (target) => target.dispatchEvent(new KeyboardEvent('keydown', { key: event.key, bubbles: true, cancelable: true })));
    }

    if (loaded()) return () => undefined;
    doc.addEventListener('click', onClick, true);
    doc.addEventListener('keydown', onKeydown, true);
    idle(() => {
        load().then(() => {
            if (loaded()) stop();
        }, () => undefined);
    });
    return stop;
}
