// @vitest-environment jsdom
// XmModal avec Bootstrap chargé à la demande (P4-12, shared/bootstrap-lazy.js) : rien n'est importé en dur ; show()
// attend l'arrivée de Bootstrap puis ouvre la modale (focus rendu au déclencheur à la fermeture) ; fermée pendant le
// chargement, elle ne s'ouvre pas ; échec du chargement : `update:open` repasse à false, sans exception.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h, nextTick, ref } from 'vue';
import { renderXm } from '@/test/helpers';
import XmModal from './XmModal.vue';

/** shared/bootstrap-lazy.js factice : `load` règle chaque demande, `loaded` tient lieu de loadedBootstrap(). */
const lazy = vi.hoisted(() => ({ loaded: null as unknown, load: vi.fn() }));
vi.mock('@/shared/bootstrap-lazy.js', () => ({
    loadedBootstrap: () => lazy.loaded,
    loadBootstrap: () => lazy.load(),
    provideBootstrap: (components: unknown) => { lazy.loaded = components; },
}));

/** Vrai chargement : shared/bootstrap.js (déjà évalué au besoin) annoncé comme le fait provideBootstrap(). */
const realLoad = async () => {
    const components = await import('@/shared/bootstrap.js');
    lazy.loaded = components;
    return components;
};

const flush = async () => {
    for (let i = 0; i < 5; i += 1) await Promise.resolve();
    await nextTick();
};

afterEach(() => {
    cleanup();
    document.body.className = '';
    document.body.removeAttribute('style');
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
});

const mount = (id: string) => {
    const open = ref(false);
    renderXm({ render: () => [
        h('button', { id: `${id}Opener`, onClick: () => { open.value = true; } }, 'Ouvrir'),
        h(XmModal, { id, title: 'Modifier', action: 'Enregistrer', open: open.value, 'onUpdate:open': (v: boolean) => { open.value = v; } }, () => 'Corps'),
    ] });
    return { open, opener: screen.getByRole('button', { name: 'Ouvrir' }), modal: () => document.getElementById(id) as HTMLElement };
};

describe('XmModal (Bootstrap à la demande)', () => {
    it('échec du chargement : la modale reste fermée, v-model:open repasse à false', async () => {
        lazy.load.mockRejectedValueOnce(new Error('réseau'));
        const view = mount('mFail');
        await fireEvent.click(view.opener);
        await flush();
        expect(lazy.load).toHaveBeenCalledTimes(1);
        expect(view.open.value).toBe(false);
        expect(view.modal().classList.contains('show')).toBe(false);
    });

    it('fermée pendant le chargement : ne s’ouvre pas à l’arrivée de Bootstrap', async () => {
        let settle: (value: unknown) => void = () => undefined;
        lazy.load.mockImplementationOnce(() => new Promise((resolve) => { settle = resolve; }));
        const view = mount('mCancel');
        await fireEvent.click(view.opener);
        view.open.value = false;
        await nextTick();
        settle(await realLoad());
        await flush();
        expect(view.modal().classList.contains('show')).toBe(false);
        expect(view.open.value).toBe(false);
    });

    it('attend Bootstrap puis s’ouvre ; fermée, le focus revient au déclencheur', async () => {
        lazy.load.mockImplementation(realLoad);
        lazy.loaded = null;
        const view = mount('mLazy');
        view.opener.focus();
        await fireEvent.click(view.opener);
        await vi.waitFor(() => expect(view.modal().classList.contains('show')).toBe(true));
        expect(view.open.value).toBe(true);
        expect(view.modal().contains(document.activeElement)).toBe(true);
        await fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
        await vi.waitFor(() => expect(view.open.value).toBe(false));
        expect(view.modal().classList.contains('show')).toBe(false);
        await vi.waitFor(() => expect(document.activeElement).toBe(view.opener));
    });
});
