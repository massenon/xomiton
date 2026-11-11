// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { fireToast, bindToasts, toastIcon, TOAST_DELAY, TOAST_QUEUE_RETRY, resetToastQueue } from './toasts.js';
import { resetAdminI18n } from './i18n.js';

const fakeSwal = () => ({ fire: vi.fn((opts) => Promise.resolve(opts)), stopTimer: vi.fn(), resumeTimer: vi.fn() });

describe('toasts SweetAlert2 de l’administration (P10-03)', () => {
  beforeEach(() => {
    document.body.innerHTML = '<script type="application/json" id="xmAdminI18n">{"close":"Fermer"}</script>';
    resetAdminI18n();
    resetToastQueue();
    delete window.XM;
  });

  it('attend la fermeture d’une confirmation ouverte avant d’afficher un toast (SweetAlert2 est unique)', () => {
    vi.useFakeTimers();
    const swal = fakeSwal();
    const popup = document.createElement('div');
    popup.className = 'swal2-popup xm-swal--danger';
    let open = true;
    swal.isVisible = vi.fn(() => open);
    swal.getPopup = vi.fn(() => popup);

    expect(fireToast('Alerte reçue.', 'info', swal)).toBeNull();
    fireToast('Deuxième.', 'success', swal);
    vi.advanceTimersByTime(TOAST_QUEUE_RETRY * 3);
    expect(swal.fire).not.toHaveBeenCalled();

    open = false;
    vi.advanceTimersByTime(TOAST_QUEUE_RETRY);
    expect(swal.fire).toHaveBeenCalledTimes(2);
    expect(swal.fire.mock.calls[0][0]).toMatchObject({ titleText: 'Alerte reçue.', icon: 'info' });

    // Un toast déjà affiché n'est pas une confirmation : le suivant le remplace aussitôt.
    open = true;
    popup.className = 'swal2-popup swal2-toast';
    fireToast('Troisième.', 'success', swal);
    expect(swal.fire).toHaveBeenCalledTimes(3);
    vi.useRealTimers();
  });

  it('affiche un toast haut-droite de 4,5 s, en texte brut, avec bouton Fermer', () => {
    const swal = fakeSwal();
    fireToast('<b>Contact</b> mis à jour.', 'success', swal);
    const opts = swal.fire.mock.calls[0][0];
    expect(opts).toMatchObject({ toast: true, position: 'top-end', icon: 'success', titleText: '<b>Contact</b> mis à jour.', timer: TOAST_DELAY, timerProgressBar: true, showCloseButton: true, closeButtonAriaLabel: 'Fermer' });
    expect(opts.title).toBeUndefined();
    expect(TOAST_DELAY).toBe(4500);
  });

  it('annonce poliment un succès et de façon assertive une erreur, avec pause au survol', () => {
    const swal = fakeSwal();
    fireToast('Enregistré.', 'success', swal);
    fireToast('Échec.', 'danger', swal);
    const ok = document.createElement('div');
    const ko = document.createElement('div');
    swal.fire.mock.calls[0][0].didOpen(ok);
    swal.fire.mock.calls[1][0].didOpen(ko);
    expect(ok.getAttribute('role')).toBe('status');
    expect(ok.getAttribute('aria-live')).toBe('polite');
    expect(ko.getAttribute('role')).toBe('alert');
    expect(ko.getAttribute('aria-live')).toBe('assertive');
    ok.dispatchEvent(new Event('mouseenter'));
    expect(swal.stopTimer).toHaveBeenCalled();
    ok.dispatchEvent(new Event('mouseleave'));
    expect(swal.resumeTimer).toHaveBeenCalled();
  });

  it('traduit les types Livewire en icônes SweetAlert2 et ignore un message vide', () => {
    expect(toastIcon('danger')).toBe('error');
    expect(toastIcon('warning')).toBe('warning');
    expect(toastIcon('primary')).toBe('info');
    expect(toastIcon('inconnu')).toBe('success');
    const swal = fakeSwal();
    expect(fireToast('', 'success', swal)).toBeNull();
    expect(swal.fire).not.toHaveBeenCalled();
  });

  it('pose l’afficheur window.XM.toastHandler et rejoue le message flashé de la page', () => {
    document.body.insertAdjacentHTML('beforeend', '<template data-xm-flash-toast data-type="warning">Maintenance activée.</template>');
    const swal = fakeSwal();
    bindToasts(document, swal);
    expect(swal.fire.mock.calls[0][0]).toMatchObject({ titleText: 'Maintenance activée.', icon: 'warning' });
    expect(document.querySelector('[data-xm-flash-toast]')).toBeNull();
    window.XM.toastHandler({ message: 'Publié.', type: 'success' });
    expect(swal.fire).toHaveBeenCalledTimes(2);
  });
});
