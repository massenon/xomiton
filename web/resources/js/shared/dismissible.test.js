// @vitest-environment jsdom
// Bandeaux refermables mémorisés pour la session (P13-08, annonce de maintenance des gabarits Blade).
import { beforeEach, describe, expect, it } from 'vitest';
import { bindDismissible, DISMISS_PREFIX } from './dismissible.js';

const memory = () => {
  const data = new Map();
  return { getItem: (k) => (data.has(k) ? data.get(k) : null), setItem: (k, v) => data.set(k, String(v)), data };
};

const markup = () => {
  document.body.innerHTML = `
    <div data-xm-maintenance-notice><div class="alert" data-xm-dismiss-key="maint-1-100"><button class="btn-close" data-bs-dismiss="alert"></button></div></div>
    <div class="alert" data-xm-dismiss-key="autre"></div>`;
};

describe('bindDismissible', () => {
  beforeEach(markup);

  it('mémorise la fermeture (clic ou closed.bs.alert) sous xm-dismissed:<clé>', () => {
    const storage = memory();
    bindDismissible(document, storage);
    document.querySelector('.btn-close').click();
    expect(storage.data.get(`${DISMISS_PREFIX}maint-1-100`)).toBe('1');
    document.querySelector('[data-xm-dismiss-key="autre"]').dispatchEvent(new Event('closed.bs.alert'));
    expect(storage.data.get(`${DISMISS_PREFIX}autre`)).toBe('1');
  });

  it('retire au chargement un bandeau déjà fermé (avec son conteneur) et garde les autres', () => {
    const storage = memory();
    storage.setItem(`${DISMISS_PREFIX}maint-1-100`, '1');
    bindDismissible(document, storage);
    expect(document.querySelector('[data-xm-maintenance-notice]')).toBeNull();
    expect(document.querySelector('[data-xm-dismiss-key="autre"]')).not.toBeNull();
  });

  it('reste silencieux si le stockage est refusé', () => {
    const throwing = { getItem: () => { throw new Error('refusé'); }, setItem: () => { throw new Error('refusé'); } };
    expect(() => bindDismissible(document, throwing)).not.toThrow();
    expect(() => document.querySelector('.btn-close').click()).not.toThrow();
    expect(document.querySelector('[data-xm-maintenance-notice]')).not.toBeNull();
  });
});
