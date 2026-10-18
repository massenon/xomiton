// @vitest-environment jsdom
// XmModal (Bootstrap Modal : ouverture, focus piégé, retour du focus, mot de confirmation), XmToast + useToast()
// (4,5 s, polite / assertive, fermable), XmOfflineBar (body.xm-is-offline) — P6-04, P6-05.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, screen } from '@testing-library/vue';
import { h, nextTick, ref } from 'vue';
import { renderXm } from '@/test/helpers';
import { useToast } from '@/app/useToast';
import { TOAST_DELAY, TOAST_MAX, useUiStore } from '@/stores/ui';
import XmModal from './XmModal.vue';
import XmOfflineBar from './XmOfflineBar.vue';
import XmToast from './XmToast.vue';

afterEach(() => {
    cleanup();
    vi.useRealTimers();
    document.body.className = '';
    document.querySelectorAll('.modal-backdrop').forEach((el) => el.remove());
});

const flush = async (ms = 400) => {
    await nextTick();
    vi.advanceTimersByTime(ms);
    await nextTick();
};

describe('XmModal', () => {
    it('s’ouvre par v-model:open, piège le focus et rend le focus au déclencheur', async () => {
        vi.useFakeTimers();
        const open = ref(false);
        renderXm({ render: () => [
            h('button', { id: 'opener', onClick: () => { open.value = true; } }, 'Ouvrir'),
            h(XmModal, { id: 'mTest', title: 'Modifier', action: 'Enregistrer', open: open.value, 'onUpdate:open': (v: boolean) => { open.value = v; } }, () => 'Corps'),
        ] });
        const opener = screen.getByRole('button', { name: 'Ouvrir' });
        opener.focus();
        await fireEvent.click(opener);
        await flush();
        const modal = document.getElementById('mTest') as HTMLElement;
        expect(modal.parentElement).toBe(document.body);
        expect(modal.classList.contains('show')).toBe(true);
        expect(modal.getAttribute('aria-modal')).toBe('true');
        expect(modal.contains(document.activeElement)).toBe(true);
        expect(modal.getAttribute('aria-labelledby')).toBe('mTestTitle');
        await fireEvent.click(screen.getByRole('button', { name: 'Annuler' }));
        await flush();
        expect(open.value).toBe(false);
        expect(modal.classList.contains('show')).toBe(false);
        expect(document.activeElement).toBe(opener);
    });

    it('démontée ouverte (visite Inertia) : rend le défilement du body, retire le fond', async () => {
        vi.useFakeTimers();
        const shown = ref(true);
        document.body.style.paddingRight = '4px';
        const view = renderXm({ render: () => (shown.value ? h(XmModal, { id: 'mGone', title: 'Partir', open: true }, () => 'Corps') : h('p', 'Page suivante')) });
        await flush();
        expect(document.body.classList.contains('modal-open')).toBe(true);
        expect(document.body.style.overflow).toBe('hidden');
        shown.value = false;
        await flush();
        expect(view.container.textContent).toContain('Page suivante');
        expect(document.getElementById('mGone')).toBeNull();
        expect(document.body.classList.contains('modal-open')).toBe(false);
        expect(document.body.style.overflow).toBe('');
        expect(document.body.style.paddingRight).toBe('4px');
        expect(document.body.hasAttribute('data-bs-padding-right')).toBe(false);
        expect(document.querySelector('.modal-backdrop')).toBeNull();
        document.body.removeAttribute('style');
    });

    it('suppression : action désactivée tant que le mot n’est pas saisi', async () => {
        const actions: number[] = [];
        renderXm({ render: () => h(XmModal, { id: 'mDel', title: 'Supprimer ?', type: 'delete', confirmWord: 'SUPPRIMER', teleport: false, onAction: () => actions.push(1) }, () => 'Définitif.') });
        const action = screen.getByRole('button', { name: 'Supprimer définitivement', hidden: true });
        expect(action.hasAttribute('disabled')).toBe(true);
        await fireEvent.update(screen.getByLabelText(/Pour confirmer/, { selector: 'input' }), 'SUPPRIMER');
        expect(action.hasAttribute('disabled')).toBe(false);
        await fireEvent.click(action);
        expect(actions).toEqual([1]);
    });

    it('formulaire : envoi par le bouton principal, garde « formulaire modifié »', async () => {
        vi.useFakeTimers();
        const submits: number[] = [];
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        renderXm({ render: () => h(XmModal, { id: 'mForm', title: 'Groupe', form: true, action: 'Créer', open: true, dirty: true, dirtyGuard: true, onSubmit: () => submits.push(1) }, () => h('input', { name: 'nom' })) });
        await flush();
        const modal = document.getElementById('mForm') as HTMLElement;
        expect(modal.getAttribute('data-bs-backdrop')).toBe('static');
        await fireEvent.click(screen.getByRole('button', { name: 'Créer' }));
        expect(submits).toEqual([1]);
        await fireEvent.click(screen.getByRole('button', { name: 'Fermer' }));
        await flush();
        expect(confirm).toHaveBeenCalledWith('Abandonner les modifications ?');
        expect(modal.classList.contains('show')).toBe(true);
        confirm.mockRestore();
    });
});

describe('XmToast et useToast()', () => {
    it('succès poli, erreur assertive, retirés après 4,5 s', async () => {
        vi.useFakeTimers();
        renderXm({ render: () => h(XmToast) });
        const toast = useToast();
        toast.success('Ta publication est en ligne.');
        toast.error('Envoi impossible.');
        await flush(50);
        const status = screen.getByRole('status');
        expect(status.getAttribute('aria-live')).toBe('polite');
        expect(status.className).toContain('text-bg-success');
        const alert = screen.getByRole('alert');
        expect(alert.getAttribute('aria-live')).toBe('assertive');
        expect(alert.parentElement?.className).toBe('toast-container position-fixed top-0 end-0 p-3');
        expect(useUiStore().toasts).toHaveLength(2);
        await flush(TOAST_DELAY + 400);
        expect(useUiStore().toasts).toHaveLength(0);
    });

    it('fermable ; un toast SOS est assertif ; jamais plus de quatre', async () => {
        vi.useFakeTimers();
        renderXm({ render: () => h(XmToast) });
        const toast = useToast();
        toast.sos('Ton alerte est partie.');
        await flush(50);
        expect(screen.getByRole('alert').textContent).toContain('Ton alerte est partie.');
        await fireEvent.click(screen.getByRole('button', { name: 'Fermer' }));
        await flush();
        expect(useUiStore().toasts).toHaveLength(0);
        for (let i = 0; i < 6; i++) toast.info(`Info ${i}`);
        expect(useUiStore().toasts).toHaveLength(TOAST_MAX);
        expect(useUiStore().toasts[0].message).toBe('Info 2');
        expect(toast.show('')).toBeNull();
    });
});

describe('XmOfflineBar', () => {
    it('bandeau du gabarit, body.xm-is-offline suit le réseau', async () => {
        renderXm({ render: () => h(XmOfflineBar) });
        const bar = screen.getByRole('status');
        expect(bar.id).toBe('xmOfflineBar');
        expect(bar.textContent).toContain('Tu es hors connexion.');
        const ui = useUiStore();
        ui.setOnline(false);
        await nextTick();
        expect(document.body.classList.contains('xm-is-offline')).toBe(true);
        ui.setOnline(true);
        await nextTick();
        expect(document.body.classList.contains('xm-is-offline')).toBe(false);
    });
});
