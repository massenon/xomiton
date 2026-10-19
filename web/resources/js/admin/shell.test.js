// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { bindSidebar, toggleSidebar, handleShortcut, syncOnline, handleTabKeys, SIDEBAR_KEY, isIdleExpiry, bindSessionExpiry } from './shell.js';

const SIDEBAR = `
  <button id="xmSidebarCollapse" aria-pressed="false" data-label-collapse="Replier le menu" data-label-expand="Déplier le menu" title="Replier le menu">
    <i class="bi bi-chevron-double-left"></i><span data-xm-collapse-label>Replier le menu</span>
  </button>
  <input id="xmGlobalSearch" type="search">
  <span data-xm-online-state class="badge bg-success" data-label-online="En ligne" data-label-offline="Hors connexion">En ligne</span>
  <div data-xm-admin-bell><div role="tablist"><button role="tab">Non lus</button><button role="tab">Lues</button><button role="tab">Toutes</button></div></div>`;

describe('coquille de l’administration (P10-01)', () => {
  beforeEach(() => {
    document.documentElement.className = '';
    document.body.innerHTML = SIDEBAR;
    localStorage.clear();
  });

  it('replie la barre latérale en icônes, mémorise le choix et met à jour le bouton', () => {
    bindSidebar(document);
    const btn = document.getElementById('xmSidebarCollapse');
    expect(btn.getAttribute('aria-pressed')).toBe('false');
    expect(toggleSidebar(document)).toBe(true);
    expect(document.documentElement.classList.contains('xm-sidebar-collapsed')).toBe(true);
    expect(localStorage.getItem(SIDEBAR_KEY)).toBe('collapsed');
    expect(btn.getAttribute('aria-pressed')).toBe('true');
    expect(btn.title).toBe('Déplier le menu');
    expect(btn.querySelector('[data-xm-collapse-label]').textContent).toBe('Déplier le menu');
    btn.click();
    expect(document.documentElement.classList.contains('xm-sidebar-collapsed')).toBe(false);
    expect(localStorage.getItem(SIDEBAR_KEY)).toBe('expanded');
  });

  it('restaure l’état replié mémorisé et survit à un stockage indisponible', () => {
    localStorage.setItem(SIDEBAR_KEY, 'collapsed');
    bindSidebar(document);
    expect(document.documentElement.classList.contains('xm-sidebar-collapsed')).toBe(true);
    const spy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('quota'); });
    expect(() => toggleSidebar(document)).not.toThrow();
    spy.mockRestore();
  });

  it('Ctrl K et ⌘ K placent le curseur dans la recherche globale visible', () => {
    const input = document.getElementById('xmGlobalSearch');
    Object.defineProperty(input, 'offsetParent', { get: () => document.body, configurable: true });
    const e = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, cancelable: true });
    expect(handleShortcut(e, document)).toBe(true);
    expect(e.defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(input);
    expect(handleShortcut(new KeyboardEvent('keydown', { key: 'K', metaKey: true }), document)).toBe(true);
    expect(handleShortcut(new KeyboardEvent('keydown', { key: 'k' }), document)).toBe(false);
  });

  it('laisse Ctrl K à l’éditeur riche, au contenteditable, à un gestionnaire antérieur et aux modales ouvertes', () => {
    const input = document.getElementById('xmGlobalSearch');
    Object.defineProperty(input, 'offsetParent', { get: () => document.body, configurable: true });
    document.body.insertAdjacentHTML('beforeend', '<div class="ql-editor" contenteditable="true"><p id="xmQuillP">texte</p></div><div contenteditable id="xmEditable"></div><div contenteditable="false"><span id="xmReadonly">lu</span></div>');
    const press = (target) => {
      const e = new KeyboardEvent('keydown', { key: 'k', ctrlKey: true, cancelable: true, bubbles: true });
      Object.defineProperty(e, 'target', { value: target });
      return e;
    };
    expect(handleShortcut(press(document.getElementById('xmQuillP')), document)).toBe(false);
    expect(handleShortcut(press(document.getElementById('xmEditable')), document)).toBe(false);
    expect(handleShortcut(press(document.getElementById('xmReadonly')), document)).toBe(true);

    const handled = press(document.body);
    handled.preventDefault();
    expect(handleShortcut(handled, document)).toBe(false);

    document.body.insertAdjacentHTML('beforeend', '<div class="modal show" id="xmOpenModal"></div>');
    expect(handleShortcut(press(document.body), document)).toBe(false);
    document.getElementById('xmOpenModal').remove();
    expect(handleShortcut(press(document.body), document)).toBe(true);
  });

  it('recharge la page quand la session est fermée pour inactivité, et seulement dans ce cas', () => {
    expect(isIdleExpiry(419, '{"message":"…","reason":"session_idle"}')).toBe(true);
    expect(isIdleExpiry(419, '{"message":"CSRF token mismatch."}')).toBe(false);
    expect(isIdleExpiry(500, '"session_idle"')).toBe(false);

    let failHandler = null;
    const reload = vi.fn();
    const win = { Livewire: { hook: (name, cb) => cb({ fail: (h) => { failHandler = h; } }) }, location: { reload }, document };
    bindSessionExpiry(win);
    const preventDefault = vi.fn();
    failHandler({ status: 419, content: '{"reason":"session_idle"}', preventDefault });
    expect(preventDefault).toHaveBeenCalled();
    expect(reload).toHaveBeenCalled();
    failHandler({ status: 419, content: '{}', preventDefault: vi.fn() });
    expect(reload).toHaveBeenCalledTimes(1);
  });

  it('affiche l’état hors connexion (bandeau et badge)', () => {
    syncOnline(false, document);
    const badge = document.querySelector('[data-xm-online-state]');
    expect(document.body.classList.contains('xm-is-offline')).toBe(true);
    expect(badge.textContent).toBe('Hors connexion');
    expect(badge.classList.contains('bg-secondary')).toBe(true);
    syncOnline(true, document);
    expect(badge.textContent).toBe('En ligne');
    expect(document.body.classList.contains('xm-is-offline')).toBe(false);
  });

  it('parcourt les onglets de la cloche aux flèches, Début et Fin', () => {
    const tabs = document.querySelectorAll('[role="tab"]');
    const clicked = [];
    tabs.forEach((t) => t.addEventListener('click', () => clicked.push(t.textContent)));
    tabs[0].focus();
    handleTabKeys({ target: tabs[0], key: 'ArrowRight', preventDefault() {} });
    expect(document.activeElement).toBe(tabs[1]);
    handleTabKeys({ target: tabs[0], key: 'ArrowLeft', preventDefault() {} });
    expect(document.activeElement).toBe(tabs[2]);
    handleTabKeys({ target: tabs[2], key: 'Home', preventDefault() {} });
    expect(clicked).toEqual(['Lues', 'Toutes', 'Non lus']);
    expect(handleTabKeys({ target: tabs[0], key: 'Enter', preventDefault() {} })).toBe(false);
  });
});
