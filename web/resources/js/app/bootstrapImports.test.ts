// Garde du paquet de l'espace membre (P4-12, PERFORMANCE.md §1) : shared/bootstrap.js (Bootstrap + Popper, ~18 Ko gzip)
// n'est jamais importé en dur par app.ts, app/, layouts/, components/ ni Pages/ : il passe par shared/bootstrap-lazy.js
// (loadBootstrap / loadedBootstrap). « import type » reste permis (effacé à la compilation). Le build vérifie aussi le
// résultat dans le manifest (scripts/check-budgets.mjs : garde Bootstrap).
import { describe, expect, it } from 'vitest';

const sources = {
    ...import.meta.glob<string>('../app.ts', { query: '?raw', import: 'default', eager: true }),
    ...import.meta.glob<string>(['../app/**/*.ts', '!../app/**/*.test.ts'], { query: '?raw', import: 'default', eager: true }),
    ...import.meta.glob<string>(['../{layouts,components,Pages}/**/*.{vue,ts,js}', '!../**/*.test.{ts,js}'], { query: '?raw', import: 'default', eager: true }),
};

/** Import statique (nommé, par défaut ou de bord) ou dynamique de shared/bootstrap.js, hors « import type ». */
const HARD_IMPORT = /\bimport(?:\s*\(\s*|\s+(?!\s|type\b)[^'"();]*?\bfrom\s*|\s*)['"][^'"]*shared\/bootstrap\.js['"]/;

describe('shared/bootstrap.js hors du paquet de l’espace membre', () => {
    it('la recherche couvre le gabarit et les composants', () => {
        const files = Object.keys(sources);
        for (const file of ['../app.ts', '../layouts/MemberLayout.vue', '../components/XmHeader.vue', '../components/XmSidenav.vue', '../components/XmToast.vue', '../components/XmModal.vue']) {
            expect(files).toContain(file);
        }
        expect(HARD_IMPORT.test("import { Modal } from '../shared/bootstrap.js';")).toBe(true);
        expect(HARD_IMPORT.test("import '@/shared/bootstrap.js';")).toBe(true);
        expect(HARD_IMPORT.test("await import('@/shared/bootstrap.js')")).toBe(true);
        expect(HARD_IMPORT.test("import type { Toast } from '../shared/bootstrap.js';")).toBe(false);
        expect(HARD_IMPORT.test("import { loadBootstrap } from '@/shared/bootstrap-lazy.js';")).toBe(false);
    });

    it('aucun import en dur de shared/bootstrap.js', () => {
        const offenders = Object.entries(sources).filter(([, source]) => HARD_IMPORT.test(source)).map(([file]) => file);
        expect(offenders).toEqual([]);
    });
});
