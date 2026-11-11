// @vitest-environment jsdom
// Enregistrement du service worker (P5-10) : seulement quand le gabarit l'annonce (meta xm-offline non vide) et en
// production, portée « / » ; relais `xm:offline-resources` → message `offline-resources` au service worker actif.
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { registerServiceWorker } from './sw-register.js';

const container = () => {
  const postMessage = vi.fn();
  return { register: vi.fn(async () => ({})), ready: Promise.resolve({ active: { postMessage } }), postMessage };
};

beforeEach(() => {
  document.head.innerHTML = '';
});

describe('registerServiceWorker', () => {
  it('enregistre /sw.js à la portée « / » quand le gabarit l’annonce', async () => {
    document.head.innerHTML = '<meta name="xm-offline" content="/sw.js" data-texts="{}">';
    const sw = container();
    await registerServiceWorker({ serviceWorker: sw, enabled: true });
    expect(sw.register).toHaveBeenCalledWith('/sw.js', { scope: '/' });
  });

  it('n’enregistre rien sans annonce (espace connecté, tunnel d’accès), hors production ou sans prise en charge', async () => {
    document.head.innerHTML = '<meta name="xm-offline" content="" data-texts="{}">';
    const sw = container();
    expect(await registerServiceWorker({ serviceWorker: sw, enabled: true })).toBeNull();
    document.head.innerHTML = '<meta name="xm-offline" content="/sw.js" data-texts="{}">';
    expect(await registerServiceWorker({ serviceWorker: sw, enabled: false })).toBeNull();
    expect(await registerServiceWorker({ serviceWorker: null, enabled: true })).toBeNull();
    expect(sw.register).not.toHaveBeenCalled();
  });

  it('relaie la bibliothèque hors connexion au service worker actif', async () => {
    const sw = container();
    const win = new EventTarget();
    registerServiceWorker({ serviceWorker: sw, enabled: false, window: win });
    const items = { guide: { url: '/ressources/guide', file: null, title: 'Guide' } };
    win.dispatchEvent(new CustomEvent('xm:offline-resources', { detail: { action: 'add', slug: 'guide', item: items.guide, items, marked: [] } }));
    await sw.ready;
    await Promise.resolve();
    expect(sw.postMessage).toHaveBeenCalledWith({ type: 'offline-resources', action: 'add', items, marked: [] });
  });
});
