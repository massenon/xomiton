// Pages à zone de saisie (assistant, messagerie) : le bouton SOS flottant est DOCKÉ dans une bande réservée au lieu de
// flotter par-dessus le contenu. Tant que la page est affichée, ce composable pose `html.xm-dock` (member.css : la fenêtre
// ne défile plus, <main> est la seule zone qui défile et s'arrête au-dessus de la bande du bouton) et publie :
//   --xm-vvh       hauteur visible (`visualViewport`, clavier virtuel déduit) : la page se borne au-dessus du clavier ;
//   --xm-kbd       part du clavier qui recouvre la fenêtre sans la redimensionner (iOS, Android « resizes-visual ») :
//                  le bouton SOS est relevé d'autant, il reste visible et à portée de geste clavier ouvert ;
//   --xm-chat-top  (sur la carte) position réelle de la carte dans <main> (bandeaux au-dessus compris), pour que la carte
//                  remplisse la place restante (assistant.css, messages.css).
//   html.xm-kbd-open  clavier virtuel considéré ouvert (D-SOS-2) : `--xm-kbd` > 80 px (iOS) OU un champ de saisie de <main> a le
//                  focus et la hauteur visible est ≤ 480 px ou a perdu plus de 20 % de la hauteur vue sans champ actif (Android).
//                  member.css libère alors la place de la saisie (en-tête et barre « Menu » repliés, bande SOS à 56 px). Retiré à la
//                  fermeture du clavier (hauteur rendue ou focus perdu).
// Remesuré au redimensionnement de la fenêtre, de `visualViewport`, de <main> et à chaque changement de focus.
import { onBeforeUnmount, onMounted, type Ref } from 'vue';

/** Seuils : clavier iOS (recouvrement), hauteur visible « petite », perte relative de hauteur (Android redimensionné). */
export const KEYBOARD_OVERLAY_MIN = 80;
export const KEYBOARD_SMALL_VIEWPORT = 480;
export const KEYBOARD_LOSS_RATIO = 0.8;

/** Champ qui ouvre le clavier : saisie de texte, zone de texte ou contenu éditable (jamais case, bouton, curseur, fichier). */
export function isTextEntry(el: Element | null): boolean {
    if (!el || !(el instanceof HTMLElement)) return false;
    if (el.isContentEditable) return true;
    if (el instanceof HTMLTextAreaElement) return !el.readOnly && !el.disabled;
    if (el instanceof HTMLInputElement) return !el.readOnly && !el.disabled && !['checkbox', 'radio', 'button', 'submit', 'reset', 'range', 'file', 'color', 'image', 'hidden'].includes(el.type);
    return false;
}

export function useViewportFit(card: Ref<HTMLElement | null>) {
    let observer: ResizeObserver | null = null;
    let alive = false;
    let wasOpen = false;
    let baseline = 0; // plus grande hauteur visible vue sans champ de saisie actif : hauteur « clavier fermé »
    const root = typeof document === 'undefined' ? null : document.documentElement;

    function fit() {
        if (!root) return;
        const vv = window.visualViewport;
        const visible = vv ? vv.height : window.innerHeight;
        root.style.setProperty('--xm-vvh', `${Math.round(visible)}px`);
        const kbd = vv ? Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop)) : 0;
        root.style.setProperty('--xm-kbd', `${kbd}px`);
        const active = document.activeElement;
        const typing = isTextEntry(active) && Boolean(active?.closest('#contenu'));
        if (!typing) baseline = Math.max(baseline, visible);
        const open = kbd > KEYBOARD_OVERLAY_MIN || (typing && (visible <= KEYBOARD_SMALL_VIEWPORT || visible < baseline * KEYBOARD_LOSS_RATIO));
        root.classList.toggle('xm-kbd-open', open);
        if (open && !wasOpen) {
            // Le navigateur a amené le champ dans la vue AVANT le repli de l'en-tête : le repli rend ce défilement inutile (la
            // carte occupe alors toute la zone visible) ; on revient en haut, sans quoi la carte resterait hors de la vue.
            const main = document.getElementById('contenu');
            const top = () => { if (main && alive && root.classList.contains('xm-kbd-open')) main.scrollTop = 0; };
            top();
            requestAnimationFrame(top);
        }
        wasOpen = open;
        const el = card.value;
        if (!el) return;
        const scrolled = document.getElementById('contenu')?.scrollTop ?? 0;
        el.style.setProperty('--xm-chat-top', `${Math.max(0, Math.round(el.getBoundingClientRect().top + scrolled + window.scrollY))}px`);
    }

    // Passage d'un champ à l'autre : `activeElement` n'est connu qu'après le focusout.
    function onFocusOut() {
        setTimeout(() => { if (alive) fit(); }, 0);
    }

    onMounted(() => {
        alive = true;
        root?.classList.add('xm-dock');
        window.scrollTo?.(0, 0); // la fenêtre ne défile plus : un reste de défilement de la page précédente est remis à zéro
        fit();
        window.addEventListener('resize', fit);
        window.visualViewport?.addEventListener('resize', fit);
        window.visualViewport?.addEventListener('scroll', fit);
        document.addEventListener('focusin', fit);
        document.addEventListener('focusout', onFocusOut);
        const main = document.getElementById('contenu');
        if (main && typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(fit);
            observer.observe(main);
        }
    });
    onBeforeUnmount(() => {
        alive = false;
        window.removeEventListener('resize', fit);
        window.visualViewport?.removeEventListener('resize', fit);
        window.visualViewport?.removeEventListener('scroll', fit);
        document.removeEventListener('focusin', fit);
        document.removeEventListener('focusout', onFocusOut);
        observer?.disconnect();
        root?.classList.remove('xm-dock', 'xm-kbd-open');
        root?.style.removeProperty('--xm-vvh');
        root?.style.removeProperty('--xm-kbd');
    });
    return { fit };
}
