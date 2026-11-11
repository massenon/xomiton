// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { parseConfirm, confirmDialog, bindConfirm } from './confirm.js';
import { resetAdminI18n } from './i18n.js';

const I18N = { confirmTitle: 'Confirmer l’action', confirm: 'Confirmer', cancel: 'Annuler', prompt: 'Pour confirmer, saisissez : :word', mismatch: 'Le texte saisi ne correspond pas.' };

describe('confirmations wire:confirm en modale rouge (P10-03)', () => {
  beforeEach(() => {
    document.body.innerHTML = `<script type="application/json" id="xmAdminI18n">${JSON.stringify(I18N)}</script>`;
    resetAdminI18n();
  });

  it('lit le message et le mot attendu comme Livewire (\\n, Question|MOT)', () => {
    expect(parseConfirm('Annuler la fenêtre ?\\nAction définitive.', [])).toEqual({ message: 'Annuler la fenêtre ?\nAction définitive.', expected: null });
    expect(parseConfirm('Purger le journal ?|PURGER', ['prompt'])).toEqual({ message: 'Purger le journal ?', expected: 'PURGER' });
  });

  it('ouvre une modale rouge, focus sur Annuler placé à gauche', async () => {
    const swal = { fire: vi.fn(() => Promise.resolve({ isConfirmed: true })) };
    await expect(confirmDialog({ message: 'Supprimer ?' }, swal)).resolves.toBe(true);
    expect(swal.fire.mock.calls[0][0]).toMatchObject({
      icon: 'warning', titleText: 'Confirmer l’action', text: 'Supprimer ?', showCancelButton: true, focusCancel: true, reverseButtons: true,
      confirmButtonText: 'Confirmer', cancelButtonText: 'Annuler', customClass: expect.objectContaining({ confirmButton: 'btn btn-danger', popup: 'xm-swal xm-swal--danger' }),
    });
  });

  it('s’affiche dans la modale Bootstrap ouverte (le piège du focus ne la contredit pas)', async () => {
    document.body.insertAdjacentHTML('beforeend', '<div class="modal show" id="mOpen"></div>');
    const swal = { fire: vi.fn(() => Promise.resolve({ isConfirmed: false })) };
    await confirmDialog({ message: 'Supprimer ?' }, swal);
    expect(swal.fire.mock.calls[0][0].target).toBe(document.getElementById('mOpen'));
  });

  it('exige la saisie exacte du mot pour une confirmation renforcée', async () => {
    const swal = { fire: vi.fn(() => Promise.resolve({ isConfirmed: false, isDismissed: true })) };
    await expect(confirmDialog({ message: 'Purger ?', expected: 'PURGER' }, swal)).resolves.toBe(false);
    const opts = swal.fire.mock.calls[0][0];
    expect(opts.input).toBe('text');
    expect(opts.inputLabel).toBe('Pour confirmer, saisissez : PURGER');
    expect(opts.inputValidator('purger')).toBe('Le texte saisi ne correspond pas.');
    expect(opts.inputValidator(' PURGER ')).toBeUndefined();
  });

  it('remplace le confirm() natif de wire:confirm : action si confirmé, rien sinon', async () => {
    let hook;
    const win = { Livewire: { hook: vi.fn((name, cb) => { if (name === 'directive.init') hook = cb; }) }, document };
    const answers = [{ isConfirmed: true }, { isConfirmed: false }];
    const swal = { fire: vi.fn(() => Promise.resolve(answers.shift())) };
    bindConfirm(win, swal);
    const el = document.createElement('button');
    el.__livewire_confirm = () => { throw new Error('confirm natif'); };
    hook({ el, directive: { value: 'confirm', expression: 'Annuler la fenêtre ?', modifiers: [] } });
    hook({ el: document.createElement('a'), directive: { value: 'click', expression: 'x', modifiers: [] } });

    const action = vi.fn();
    const instead = vi.fn();
    el.__livewire_confirm(action, instead);
    await Promise.resolve(); await Promise.resolve();
    expect(action).toHaveBeenCalledTimes(1);
    el.__livewire_confirm(action, instead);
    await Promise.resolve(); await Promise.resolve();
    expect(instead).toHaveBeenCalledTimes(1);
    expect(win.Livewire.hook).toHaveBeenCalledTimes(1);
  });

  it('attend livewire:init quand Livewire n’est pas encore chargé', () => {
    const win = { document };
    bindConfirm(win, { fire: vi.fn() });
    win.Livewire = { hook: vi.fn() };
    document.dispatchEvent(new Event('livewire:init'));
    expect(win.Livewire.hook).toHaveBeenCalledWith('directive.init', expect.any(Function));
  });
});
