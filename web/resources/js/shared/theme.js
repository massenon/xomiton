// Thème clair / sombre / automatique (data-bs-theme), repris du prototype (partials/head-css.php).
// La préférence est stockée localement ; « auto » suit le système.
const KEY = 'theme';

const preferred = () => {
    const stored = localStorage.getItem(KEY);
    if (stored) return stored;
    return 'light';
};

const apply = (theme) => {
    const dark = theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
};

apply(preferred());

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (preferred() === 'auto') apply('auto');
});

document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('[data-bs-theme-value]');
    const showActive = (theme) => {
        buttons.forEach((b) => {
            const active = b.getAttribute('data-bs-theme-value') === theme;
            b.classList.toggle('active', active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const icon = document.querySelector('.theme-icon-active use');
        const btn = document.querySelector(`[data-bs-theme-value="${theme}"] .mode-switch use`);
        if (icon && btn) icon.setAttribute('href', btn.getAttribute('href'));
    };
    showActive(preferred());
    buttons.forEach((b) => b.addEventListener('click', () => {
        const theme = b.getAttribute('data-bs-theme-value');
        localStorage.setItem(KEY, theme);
        apply(theme);
        showActive(theme);
    }));
});
