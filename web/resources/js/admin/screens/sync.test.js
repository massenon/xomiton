// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach } from 'vitest';
import mount, { describeCaches, readTexts, workerState } from './sync.js';

const texts = { active: 'actif', waiting: 'en attente', unsupported: 'non pris en charge', none: 'Aucun cache', resources: 'ressource(s)', confirm: 'Vider ?', cleared: 'Vidé.', failed: 'Lecture impossible.', unavailable: 'API Cache indisponible.' };

function fakeCaches(map) {
  return {
    keys: vi.fn(async () => Object.keys(map)),
    open: vi.fn(async (name) => ({ keys: async () => map[name] })),
    delete: vi.fn(async (name) => { delete map[name]; return true; }),
  };
}

describe('écran Hors connexion & synchro : cache local (P13-01)', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <span id="xmSyncSwState"></span><ul id="xmSyncCacheList"></ul><button id="xmSyncCacheClear"></button>
      <script type="application/json" id="xmSyncI18n">${JSON.stringify(texts)}</script>`;
  });

  it('lit les textes du serveur et tolère un JSON cassé', () => {
    expect(readTexts(document).active).toBe('actif');
    document.getElementById('xmSyncI18n').textContent = '{pas du json';
    expect(readTexts(document)).toEqual({});
  });

  it('décrit l’état du service worker', () => {
    expect(workerState({ serviceWorker: { controller: {} } }, texts)).toBe('actif');
    expect(workerState({ serviceWorker: { controller: null } }, texts)).toBe('en attente');
    expect(workerState({}, texts)).toBe('non pris en charge');
  });

  it('liste les caches par ordre de nom avec leur nombre de ressources', async () => {
    const entries = await describeCaches(fakeCaches({ b: [1, 2], a: [1] }), texts);
    expect(entries.map((e) => e.text)).toEqual(['a — 1 ressource(s)', 'b — 2 ressource(s)']);
  });

  it('affiche les caches, puis les vide après confirmation sans toucher au reste', async () => {
    const caches = fakeCaches({ shell: [1, 2, 3] });
    const win = { navigator: { serviceWorker: { controller: {} } }, caches, confirm: vi.fn(() => true), XM: { admin: { toast: vi.fn() } } };
    mount(win, document);
    await vi.waitFor(() => expect(document.getElementById('xmSyncCacheList').textContent).toContain('shell — 3 ressource(s)'));
    expect(document.getElementById('xmSyncSwState').textContent).toBe('actif');

    document.getElementById('xmSyncCacheClear').click();
    await vi.waitFor(() => expect(caches.delete).toHaveBeenCalledWith('shell'));
    expect(win.XM.admin.toast).toHaveBeenCalledWith('Vidé.', 'success');
    await vi.waitFor(() => expect(document.getElementById('xmSyncCacheList').textContent).toBe('Aucun cache'));
  });

  it('ne vide rien sans confirmation', async () => {
    const caches = fakeCaches({ shell: [1] });
    mount({ navigator: {}, caches, confirm: () => false }, document);
    document.getElementById('xmSyncCacheClear').click();
    await new Promise((resolve) => setTimeout(resolve, 10));
    expect(caches.delete).not.toHaveBeenCalled();
  });

  it('signale une API Cache indisponible', () => {
    mount({ navigator: {} }, document);
    expect(document.getElementById('xmSyncCacheList').textContent).toBe('API Cache indisponible.');
  });
});
