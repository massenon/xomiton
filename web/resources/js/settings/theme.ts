// Thème de l'espace Vue (P8-09, section « Apparence ») : même mémoire que resources/js/shared/theme.js (clé
// localStorage `theme`, `auto` suit le système, attribut data-bs-theme sur <html>) ; le choix est aussi enregistré
// sur le compte (PATCH me/settings {theme}) pour l'application mobile.
import type { Theme } from './types';

export const THEME_KEY = 'theme';
export const THEMES: Theme[] = ['light', 'dark', 'auto'];

export function storedTheme(fallback: Theme = 'auto'): Theme {
    try {
        const value = window.localStorage.getItem(THEME_KEY);
        return THEMES.includes(value as Theme) ? (value as Theme) : fallback;
    } catch {
        return fallback;
    }
}

export function applyTheme(theme: Theme, root: HTMLElement = document.documentElement): void {
    const prefersDark = typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches;
    const dark = theme === 'dark' || (theme === 'auto' && prefersDark);
    root.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    try {
        window.localStorage.setItem(THEME_KEY, theme);
    } catch {
        // Stockage indisponible (navigation privée) : le thème s'applique pour cette page seulement.
    }
}
