import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';
import { createHash } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Service worker (P5-10, ADR-26) : précache de la coquille du paquet public seulement (public.js, ses imports statiques et
// à la demande, xomiton.css), des polices latines et des logos ; ni l'administration ni l'espace Vue. La liste suit le
// manifest Vite écrit juste avant (public/build/manifest.json) ; les adresses sont absolues (/build/…) car le script est
// servi à la racine par GET /sw.js (ServiceWorkerController).
const SHELL_ENTRIES = ['resources/js/public.js', 'resources/css/xomiton.css'];
const SHELL_FONTS = /(?:Montserrat-(?:latin|latin-ext)-normal|bootstrap-icons)\.woff2$/;
const SHELL_STATIC = ['logo-couleur.svg', 'logo-sombre.svg', 'icone-appli-bleu.svg'].map((name) => `assets/images/xomiton/brand/${name}`);

function shellFiles(manifestPath) {
    const manifest = existsSync(manifestPath) ? JSON.parse(readFileSync(manifestPath, 'utf8')) : {};
    const files = new Set();
    const seen = new Set();
    const visit = (key) => {
        const chunk = manifest[key];
        if (!chunk || seen.has(key)) return;
        seen.add(key);
        files.add(chunk.file);
        (chunk.css || []).forEach((file) => files.add(file));
        [...(chunk.imports || []), ...(chunk.dynamicImports || [])].forEach(visit);
    };
    SHELL_ENTRIES.forEach(visit);
    Object.entries(manifest).forEach(([key, chunk]) => {
        if (SHELL_FONTS.test(key)) files.add(chunk.file);
    });
    return files;
}

const staticEntries = () => SHELL_STATIC.filter((path) => existsSync(resolve('public', path))).map((path) => ({
    url: `/${path}`,
    revision: createHash('md5').update(readFileSync(resolve('public', path))).digest('hex'),
}));

// Trois paquets, un par espace (D9) : les pages publiques ne chargent jamais Vue,
// l'administration (Livewire) ne charge ni Vue ni Alpine à part (Livewire embarque Alpine),
// l'espace membre et pair charge Vue + Inertia.
export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/critical.css',
                'resources/css/xomiton.css',
                'resources/css/admin.css',
                'resources/js/public.js',
                'resources/js/admin.js',
                'resources/js/app.ts',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js/sw',
            filename: 'sw.js',
            injectRegister: false,
            manifest: false,
            injectManifest: {
                rollupFormat: 'iife',
                globPatterns: ['assets/*.{js,css,woff2}'],
                globIgnores: ['sw.js', 'manifest.json'],
                additionalManifestEntries: staticEntries(),
                manifestTransforms: [
                    async (entries) => {
                        const shell = shellFiles(resolve('public/build/manifest.json'));
                        return {
                            manifest: entries.filter((entry) => shell.has(entry.url)).map((entry) => ({ ...entry, url: `/build/${entry.url}` })),
                            warnings: [],
                        };
                    },
                ],
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    // Vitest : seulement nos tests (les specs embarquées dans vendor/ et node_modules/ ne sont pas les nôtres).
    test: {
        include: ['resources/js/**/*.{test,spec}.{js,ts}'],
    },
});
