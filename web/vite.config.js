import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

// Trois paquets, un par espace (D9) : les pages publiques ne chargent jamais Vue,
// l'administration (Livewire) ne charge ni Vue ni Alpine à part (Livewire embarque Alpine),
// l'espace membre et pair charge Vue + Inertia.
export default defineConfig({
    plugins: [
        laravel({
            input: [
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
});
