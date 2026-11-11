// @vitest-environment jsdom
// Fermetures du gabarit membre avec Bootstrap chargé à la demande (P4-12, shared/bootstrap-lazy.js) :
// XmHeader.closeMenus() et XmSidenav.close() lisent les composants par loadedBootstrap(). Fournis (fausses classes
// espionnées) : menus déroulants, repli de l'en-tête et offcanvas ouverts sont masqués ; absents : rien ne se passe,
// sans exception. Avec le vrai Bootstrap : memberShell.test.ts.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { mountInertia, unmountInertia } from '@/test/inertia';
import { loadedBootstrap, provideBootstrap } from '@/shared/bootstrap-lazy.js';
import XmHeader from './XmHeader.vue';
import XmSidenav from './XmSidenav.vue';

type Exposed = { closeMenus?: () => void; close?: () => void };

/** Page Inertia qui rend `component` et garde ce qu'il expose. */
const withRef = (component: unknown) => {
    const exposed = ref<Exposed | null>(null);
    return { exposed, page: defineComponent({ render: () => h(component as never, { ref: exposed }) }) };
};

const fakeBootstrap = () => {
    const hide = vi.fn();
    const instance = { hide };
    const fake = {
        hide,
        Dropdown: { getInstance: vi.fn(() => instance) },
        Collapse: { getOrCreateInstance: vi.fn(() => instance) },
        Offcanvas: { getOrCreateInstance: vi.fn(() => instance) },
    };
    return fake;
};

beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn(async () => new Response(JSON.stringify({ data: { unread: 0 } }), { headers: { 'Content-Type': 'application/json' } })));
});

afterEach(() => {
    unmountInertia();
    provideBootstrap(null);
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('XmHeader.closeMenus', () => {
    it('Bootstrap absent : rien ne se passe, sans exception', async () => {
        const { exposed, page } = withRef(XmHeader);
        await mountInertia(page);
        const collapse = document.getElementById('memberNavbar') as HTMLElement;
        collapse.classList.add('show');
        expect(loadedBootstrap()).toBeNull();
        expect(() => exposed.value?.closeMenus?.()).not.toThrow();
        expect(collapse.classList.contains('show')).toBe(true);
    });

    it('Bootstrap fourni : menus déroulants et repli ouvert masqués', async () => {
        const fake = fakeBootstrap();
        provideBootstrap(fake as never);
        const { exposed, page } = withRef(XmHeader);
        await mountInertia(page);
        const collapse = document.getElementById('memberNavbar') as HTMLElement;
        const toggles = document.querySelectorAll('#memberHeader [data-bs-toggle="dropdown"]');
        expect(toggles.length).toBeGreaterThan(0);
        exposed.value?.closeMenus?.();
        expect(fake.Dropdown.getInstance).toHaveBeenCalledTimes(toggles.length);
        expect(fake.Collapse.getOrCreateInstance).not.toHaveBeenCalled();
        collapse.classList.add('show');
        fake.hide.mockClear();
        exposed.value?.closeMenus?.();
        expect(fake.Collapse.getOrCreateInstance).toHaveBeenCalledWith(collapse, { toggle: false });
        expect(fake.hide).toHaveBeenCalledTimes(toggles.length + 1);
    });
});

describe('XmSidenav.close', () => {
    it('Bootstrap absent : rien ne se passe, sans exception', async () => {
        const { exposed, page } = withRef(XmSidenav);
        await mountInertia(page);
        const offcanvas = document.getElementById('offcanvasSideNavbar') as HTMLElement;
        offcanvas.classList.add('show');
        expect(() => exposed.value?.close?.()).not.toThrow();
        expect(offcanvas.classList.contains('show')).toBe(true);
    });

    it('Bootstrap fourni : l’offcanvas ouvert est masqué, fermé il est ignoré', async () => {
        const fake = fakeBootstrap();
        provideBootstrap(fake as never);
        const { exposed, page } = withRef(XmSidenav);
        await mountInertia(page);
        const offcanvas = document.getElementById('offcanvasSideNavbar') as HTMLElement;
        exposed.value?.close?.();
        expect(fake.Offcanvas.getOrCreateInstance).not.toHaveBeenCalled();
        offcanvas.classList.add('show');
        exposed.value?.close?.();
        expect(fake.Offcanvas.getOrCreateInstance).toHaveBeenCalledWith(offcanvas);
        expect(fake.hide).toHaveBeenCalledTimes(1);
    });
});
