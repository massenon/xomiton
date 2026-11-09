// Bouton SOS flottant : une carte « plein écran » (assistant, messagerie) s'arrête au-dessus de la zone du bouton
// (feuilles assistant.css et messages.css : `height: calc(100dvh - var(--xm-chat-top) - var(--xm-sos-clear))`). Ce
// composable donne à la carte sa position réelle dans la page (`--xm-chat-top`, bandeaux d'e-mail ou de maintenance
// au-dessus compris) et la remesure à chaque changement de taille de <main> ou de la fenêtre. Sa barre de saisie, en
// bas de la carte, ne passe ainsi jamais sous le bouton quand la page défile vers le bas.
import { onBeforeUnmount, onMounted, type Ref } from 'vue';

export function useViewportFit(card: Ref<HTMLElement | null>) {
    let observer: ResizeObserver | null = null;

    function fit() {
        const el = card.value;
        if (!el) return;
        el.style.setProperty('--xm-chat-top', `${Math.max(0, Math.round(el.getBoundingClientRect().top + window.scrollY))}px`);
    }

    onMounted(() => {
        fit();
        window.addEventListener('resize', fit);
        const main = document.getElementById('contenu');
        if (main && typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(fit);
            observer.observe(main);
        }
    });
    onBeforeUnmount(() => {
        window.removeEventListener('resize', fit);
        observer?.disconnect();
    });
    return { fit };
}
