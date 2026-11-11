// @vitest-environment jsdom
// XmToast avec Bootstrap chargé à la demande (P4-12, shared/bootstrap-lazy.js) : le premier toast attend l'arrivée de
// Bootstrap puis s'affiche et se retire comme avant ; un toast retiré de la file pendant le chargement n'est pas lié ;
// chargement en échec : repli sans Bootstrap (affiché, retiré au bout de son délai, bouton Fermer actif).
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h, nextTick } from 'vue';
import { renderXm } from '@/test/helpers';
import { useToast } from '@/app/useToast';
import { TOAST_DELAY, useUiStore } from '@/stores/ui';
import { loadBootstrap, loadedBootstrap } from '@/shared/bootstrap-lazy.js';
import XmToast from './XmToast.vue';

// Vrai module, loadBootstrap espionné (échec simulé au cas par cas).
vi.mock('@/shared/bootstrap-lazy.js', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/shared/bootstrap-lazy.js')>();
    return { ...actual, loadBootstrap: vi.fn(actual.loadBootstrap) };
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

const flush = async () => {
    for (let i = 0; i < 5; i += 1) await Promise.resolve();
    await nextTick();
};

describe('XmToast (Bootstrap à la demande)', () => {
    it('Bootstrap indisponible : le toast s’affiche quand même, se retire après son délai et se ferme', async () => {
        vi.useFakeTimers();
        const actual = vi.mocked(loadBootstrap).getMockImplementation()!;
        vi.mocked(loadBootstrap).mockImplementation(() => Promise.reject(new Error('réseau')));
        renderXm({ render: () => h(XmToast) });
        const toast = useToast();
        toast.error('Connexion perdue.');
        await flush();
        expect(screen.getByRole('alert').className).toContain('show');
        vi.advanceTimersByTime(TOAST_DELAY);
        await nextTick();
        expect(useUiStore().toasts).toHaveLength(0);

        toast.info('Signalement mis en attente.');
        await flush();
        expect(screen.getByRole('status').className).toContain('show');
        await fireEvent.click(screen.getByRole('button', { name: 'Fermer' }));
        expect(useUiStore().toasts).toHaveLength(0);
        expect(loadedBootstrap()).toBeNull();
        vi.mocked(loadBootstrap).mockImplementation(actual);
    });

    it('le premier toast charge Bootstrap puis s’affiche ; retiré pendant le chargement, il n’est pas lié', async () => {
        expect(loadedBootstrap()).toBeNull();
        renderXm({ render: () => h(XmToast) });
        const toast = useToast();
        const gone = toast.info('Retiré tout de suite.');
        await nextTick();
        expect(screen.getByRole('status').textContent).toContain('Retiré tout de suite.');
        useUiStore().dismissToast(gone!);
        toast.success('Ta publication est en ligne.');
        await nextTick();
        expect(screen.getByRole('status').className).not.toContain('show');

        await vi.waitFor(() => expect(loadedBootstrap()).not.toBeNull());
        await vi.waitFor(() => expect(screen.getByRole('status').className).toContain('show'));
        expect(screen.getByRole('status').textContent).toContain('Ta publication est en ligne.');
        expect(useUiStore().toasts).toHaveLength(1);
    });
});
