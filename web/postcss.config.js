// PostCSS (Vite) : PurgeCSS du thème Social et de Bootstrap Icons au build de production seulement (P0-04, ADR-18,
// PERFORMANCE.md §3). En développement (vite dev, vite build --mode development) et avec XM_PURGE=0, rien n'est retiré :
// XM_PURGE=0 sert aux comparaisons avant / après (captures pixel à pixel) et au diagnostic d'une classe disparue.
//
// Contenu analysé : vues Blade, JS/TS/Vue, PHP d'app/ (classes de badge et d'état produites par les enums et les
// composants), Livewire (classes posées par son JS : wire:loading, etc.). Toute classe construite dynamiquement côté
// serveur ou JS qui n'apparaît pas littéralement dans ces fichiers doit être couverte par la liste de sécurité.
import purgecss from '@fullhuman/postcss-purgecss';

const production = process.env.NODE_ENV === 'production' && process.env.XM_PURGE !== '0';

/**
 * Liste de sécurité documentée (ADR-18). PurgeCSS teste chaque motif sur chaque partie d'un sélecteur : le nom d'une
 * classe (sans le point), d'un id, d'une balise ou d'un attribut (`data-level` pour `[data-level="weak"]`).
 * `standard` : la partie est réputée présente ; `greedy` : la règle entière est conservée avec ses descendants.
 */
const safelist = {
    standard: [
        'html', 'body', 'light-mode-item', 'dark-mode-item', 'no-print',
        // Préfixes et familles de classes jamais purgés (`bs-` : posées par Popper / Bootstrap JS, ex. `bs-tooltip-auto`).
        /^xm-/, /^bi-/, /^bs-/, /^wire:/, /^x-/, /^livewire-/, /^data-bs-/,
        // États et composants pilotés par le JS de Bootstrap, Alpine ou Livewire.
        /^(show|showing|hide|hiding|fade|collapse|collapsing|active|disabled|invalid|valid|is-invalid|is-valid|was-validated|visually-hidden|visually-hidden-focusable|modal|modal-backdrop|modal-open|offcanvas|offcanvas-backdrop|tooltip|popover|toast|dropdown|dropdown-menu|carousel|placeholder|placeholder-glow|placeholder-wave|table|form-)/,
        // Utilitaires Bootstrap composés dans le PHP et le JS (ex. `text-bg-${type}`, `bg-${status}`, `col-md-${n}`).
        /^(bg|text|border|btn|badge|alert|status|rounded|shadow|d|flex|justify|align|gap|m|p|mt|mb|ms|me|mx|my|pt|pb|ps|pe|px|py|w|h|col|row|g|order|position|top|bottom|start|end|fs|fw|lh|opacity)-/,
        // Utilitaires sans tiret utilisés tels quels et conteneurs.
        /^(container|navbar|nav|card|list-group|breadcrumb|pagination|progress|spinner|ratio|sticky|fixed|avatar|icon)(-|$)/,
        // États posés par le JS maison (`is-on`, `is-${tone}`, `is-ok` : password-meter.js, xomiton-ui.js).
        /^(is|has)-/,
    ],
    // Règles conservées en entier avec leurs descendants : thème sombre ([data-bs-theme=dark]), Livewire / Alpine
    // ([wire:loading], [x-cloak]) et toute règle portant un attribut data-* ou aria-* ([data-level], [data-holding],
    // [aria-expanded]…) : ces attributs sont posés par le JS (dataset, Alpine :data-*) et n'apparaissent pas
    // littéralement dans le contenu analysé.
    greedy: [/^data-bs-theme$/, /^x-cloak$/, /^livewire-/, /^wire:/, /^(data|aria)-/],
};

const purge = purgecss({
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,ts,vue}',
        './app/**/*.php',
        './vendor/livewire/livewire/src/**/*.php',
    ],
    // Extraction tolérante aux classes Bootstrap (`:`, `/`, `.` dans les noms, ex. `w-50`, `ratio-16x9`).
    defaultExtractor: (content) => content.match(/[\w-/:%.]+(?<!:)/g) || [],
    safelist,
    // Jetons --xm-*, @keyframes et @font-face du thème sont toujours conservés (jamais purgés).
    keyframes: false,
    variables: false,
    fontFace: false,
});

/**
 * Seules les feuilles du thème (xomiton.css, critical.css) sont purgées. admin.css embarque les styles de Quill,
 * Choices et flatpickr dont les classes (`ql-*`, `choices*`, `flatpickr-*`) sont posées par leur JS, hors du contenu
 * analysé : elle est servie telle quelle (51 Ko, 8,5 Ko gzip, administration seulement).
 */
const purgeThemeOnly = {
    postcssPlugin: 'xomiton-purgecss-theme-only',
    OnceExit(root, helpers) {
        const file = (root.source && root.source.input.file) || '';
        if (/[\\/]admin\.css$/.test(file)) return undefined;

        return purge.OnceExit(root, helpers);
    },
};

export default {
    plugins: production ? [purgeThemeOnly] : [],
};
