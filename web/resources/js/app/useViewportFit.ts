// Pages à zone de saisie (assistant, messagerie) : le bouton SOS flottant est DOCKÉ dans une bande réservée au lieu de
// flotter par-dessus le contenu. Tant que la page est affichée, ce composable pose `html.xm-dock` (member.css : la fenêtre
// ne défile plus, <main> est la seule zone qui défile et s'arrête au-dessus de la bande du bouton) et publie :
//   --xm-vvh       hauteur visible (`visualViewport`, clavier virtuel déduit) : la page se borne au-dessus du clavier ;
//   --xm-kbd       part du clavier qui recouvre la fenêtre sans la redimensionner (iOS, Android « resizes-visual ») :
//                  le bouton SOS est relevé d'autant, il reste visible et à portée de geste clavier ouvert ;
//   --xm-chat-top  (sur la carte) position réelle de la carte dans <main> (bandeaux au-dessus compris), pour que la carte
//                  remplisse la place restante (assistant.css, messages.css).
// Remesuré au redimensionnement de la fenêtre, de `visualViewport` et de <main>.
import { onBeforeUnmount, onMounted, type Ref } from 'vue';

export function useViewportFit(card: Ref<HTMLElement | null>) {
    let observer: ResizeObserver | null = null;
    const root = typeof document === 'undefined' ? null : document.documentElement;

    function fit() {
        if (!root) return;
        const vv = window.visualViewport;
        const visible = vv ? vv.height : window.innerHeight;
        root.style.setProperty('--xm-vvh', `${Math.round(visible)}px`);
        root.style.setProperty('--xm-kbd', `${vv ? Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop)) : 0}px`);
        const el = card.value;
        if (!el) return;
        const scrolled = document.getElementById('contenu')?.scrollTop ?? 0;
        el.style.setProperty('--xm-chat-top', `${Math.max(0, Math.round(el.getBoundingClientRect().top + scrolled + window.scrollY))}px`);
    }

    onMounted(() => {
        root?.classList.add('xm-dock');
        window.scrollTo?.(0, 0); // la fenêtre ne défile plus : un reste de défilement de la page précédente est remis à zéro
        fit();
        window.addEventListener('resize', fit);
        window.visualViewport?.addEventListener('resize', fit);
        window.visualViewport?.addEventListener('scroll', fit);
        const main = document.getElementById('contenu');
        if (main && typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(fit);
            observer.observe(main);
        }
    });
    onBeforeUnmount(() => {
        window.removeEventListener('resize', fit);
        window.visualViewport?.removeEventListener('resize', fit);
        window.visualViewport?.removeEventListener('scroll', fit);
        observer?.disconnect();
        root?.classList.remove('xm-dock');
        root?.style.removeProperty('--xm-vvh');
        root?.style.removeProperty('--xm-kbd');
    });
    return { fit };
}
