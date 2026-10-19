// @vitest-environment jsdom
import { describe, it, expect, vi } from 'vitest';
import { handleModalEvent } from './modals.js';

describe('modales Livewire (évènement xm-modal, P10-02)', () => {
  it('ouvre et ferme la modale Bootstrap visée, ignore un identifiant inconnu', () => {
    document.body.innerHTML = '<div class="modal" id="mEdit"></div>';
    const instance = { show: vi.fn(), hide: vi.fn(), toggle: vi.fn() };
    const ModalClass = { getOrCreateInstance: vi.fn(() => instance) };
    expect(handleModalEvent({ id: 'mEdit', action: 'hide' }, document, ModalClass)).toBe(true);
    expect(instance.hide).toHaveBeenCalled();
    handleModalEvent({ id: 'mEdit', action: 'détruire' }, document, ModalClass);
    expect(instance.show).toHaveBeenCalled();
    expect(handleModalEvent({ id: 'absente' }, document, ModalClass)).toBe(false);
  });
});
