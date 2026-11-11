// Modales des écrans Livewire (P10-02, x-admin.modal) : `$this->openModal('id')` / `$this->closeModal('id')`
// (InteractsWithUi) émettent l'évènement window `xm-modal` {id, action: show|hide|toggle}, traduit ici en appel Bootstrap.
import { Modal } from '../shared/bootstrap.js';

export function handleModalEvent(detail, doc = document, ModalClass = Modal) {
  const d = detail || {};
  const el = d.id ? doc.getElementById(d.id) : null;
  if (!el) return false;
  const modal = ModalClass.getOrCreateInstance(el);
  const action = ['show', 'hide', 'toggle'].includes(d.action) ? d.action : 'show';
  modal[action]();
  return true;
}

export function bindModals(win = window) {
  win.addEventListener('xm-modal', (e) => handleModalEvent(e.detail));
}
