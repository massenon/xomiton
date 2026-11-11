// @vitest-environment node
// Câblage du service worker (P5-10, ADR-26) avec caches et fetch simulés : navigation réseau d'abord et délai, pages
// gardées seulement rendues sans compte (jamais une page de session ni `no-store`), copie marquée, repli et page
// minimale avec numéros d'urgence, purge à la déconnexion (pages et fiches), bibliothèque des ressources (seulement ce
// qui manque, retrait), installation sans tout recharger, Range depuis le cache, caches inconnus retirés.
import { describe, expect, it, vi } from 'vitest';
import { META_CACHE, PAGES_CACHE, RESOURCES_CACHE } from './rules.js';
import { createWorker, markedRevisions } from './worker.js';

const ORIGIN = 'https://xomiton.test';

class FakeCache {
  constructor() { this.map = new Map(); }

  static key(request) { return typeof request === 'string' ? request : request.url; }

  async match(request) {
    const hit = this.map.get(FakeCache.key(request));
    return hit ? hit.clone() : undefined;
  }

  async put(request, response) {
    const body = await response.arrayBuffer();
    this.map.set(FakeCache.key(request), new Response(body, { status: response.status, statusText: response.statusText, headers: response.headers }));
  }

  async delete(request) { return this.map.delete(FakeCache.key(request)); }

  async keys() { return [...this.map.keys()].map((url) => new Request(url)); }
}

class FakeCaches {
  constructor() { this.stores = new Map(); }

  async open(name) {
    if (!this.stores.has(name)) this.stores.set(name, new FakeCache());
    return this.stores.get(name);
  }

  async delete(name) { return this.stores.delete(name); }

  async keys() { return [...this.stores.keys()]; }

  async match(request) {
    for (const cache of this.stores.values()) {
      const hit = await cache.match(request);
      if (hit) return hit;
    }
    return undefined;
  }

  paths(name) { return [...(this.stores.get(name)?.map.keys() || [])].map((url) => new URL(url).pathname).sort(); }
}

const html = (body, headers = {}) => new Response(`<!DOCTYPE html><html lang="fr"><body>${body}</body></html>`, {
  status: 200, headers: { 'Content-Type': 'text/html; charset=UTF-8', ...headers },
});
const guest = (body) => html(body, { 'X-Xm-Audience': 'guest' });

/** Serveur simulé : réponse selon le chemin, et le cookie pris ou non (credentials). */
function server(routes = {}) {
  return vi.fn(async (input) => {
    const request = input instanceof Request ? input : new Request(input);
    const path = new URL(request.url).pathname;
    const route = routes[path];
    if (route instanceof Error) throw route;
    if (typeof route === 'function') return route(request);
    return route ? route.clone() : new Response('absent', { status: 404 });
  });
}

function setup({ config = {}, routes = {}, caches = new FakeCaches() } = {}) {
  const fetch = server(routes);
  const expiration = { updateTimestamp: vi.fn(async () => undefined), expireEntries: vi.fn(async () => undefined) };
  const partial = vi.fn(async (request, response) => new Response('partiel', { status: 206, headers: { 'Content-Range': 'bytes 0-6/42', 'X-From': response.headers.get('X-From') || '' } }));
  const worker = createWorker({ config: { pages: ['/urgences', '/sos'], fallback: '/urgences', texts: { title: 'Hors connexion', text: 'En danger.', numbers: 'Numéros d’urgence' }, ...config }, origin: ORIGIN, caches, fetch, expiration, partial });
  return { worker, caches, fetch, expiration, partial };
}

/** Évènement simulé : waitUntil collecte les promesses, `settle` attend toutes, y compris celles ajoutées en route. */
function fakeEvent(request) {
  const pending = [];
  return {
    request,
    waitUntil: (promise) => { pending.push(promise); },
    async settle() {
      for (let seen = 0; seen < pending.length;) {
        const batch = pending.slice(seen);
        seen = pending.length;
        await Promise.allSettled(batch);
      }
    },
  };
}

async function navigate(worker, path) {
  const url = new URL(ORIGIN + path);
  const event = fakeEvent(new Request(url));
  const response = await worker.navigate({ request: event.request, url, event });
  await event.settle();
  return response;
}

describe('navigations', () => {
  it('garde une page de la liste rendue sans compte, jamais celle d’une session ni une réponse no-store', async () => {
    const { worker, caches, expiration } = setup({ routes: { '/urgences': guest('Urgences'), '/sos': html('Zone du profil : Littoral') } });

    expect(await (await navigate(worker, '/urgences')).text()).toContain('Urgences');
    expect(await (await navigate(worker, '/sos')).text()).toContain('Zone du profil');
    expect(caches.paths(PAGES_CACHE)).toEqual(['/urgences']);
    expect(expiration.expireEntries).toHaveBeenCalled();

    const other = setup({ routes: { '/sos': html('Formulaire', { 'X-Xm-Audience': 'guest', 'Cache-Control': 'no-store, private' }) } });
    await navigate(other.worker, '/sos');
    expect(other.caches.paths(PAGES_CACHE)).toEqual([]);
  });

  it('sans réseau : copie marquée data-xm-offline-copy, sinon /urgences, sinon page minimale avec les numéros en tel:', async () => {
    const caches = new FakeCaches();
    const { worker } = setup({ caches, config: { numbers: [{ label: 'Police', number: '117' }, { label: 'Ligne verte', number: '+229 01 00 00' }] }, routes: { '/urgences': new TypeError('offline'), '/aide': new TypeError('offline') } });

    const minimal = await navigate(worker, '/aide');
    const body = await minimal.text();
    expect(minimal.status).toBe(503);
    expect(body).toContain('href="tel:117"');
    expect(body).toContain('href="tel:+229010000"');
    expect(body).toContain('Numéros d’urgence');

    await (await caches.open(PAGES_CACHE)).put(`${ORIGIN}/urgences`, guest('Contacts d’urgence'));
    const copy = await navigate(worker, '/urgences');
    expect(copy.headers.get('X-Xm-Offline-Copy')).toBe('1');
    expect(await copy.text()).toContain('<html data-xm-offline-copy lang="fr">');
    expect(await (await navigate(worker, '/aide')).text()).toContain('Contacts d’urgence');
  });

  it('réseau lent : la copie en cache après le délai, sinon la réponse du réseau quand elle arrive', async () => {
    const caches = new FakeCaches();
    let release;
    const slow = () => new Promise((resolve) => { release = () => resolve(guest('Réseau')); });
    const { worker } = setup({ caches, config: { timeout: 20 }, routes: { '/urgences': slow, '/sos': slow } });
    await (await caches.open(PAGES_CACHE)).put(`${ORIGIN}/urgences`, guest('Copie'));

    const url = new URL(`${ORIGIN}/urgences`);
    const event = fakeEvent(new Request(url));
    expect(await (await worker.navigate({ request: event.request, url, event })).text()).toContain('Copie');
    release();
    await event.settle();

    const sos = new URL(`${ORIGIN}/sos`);
    const sosEvent = fakeEvent(new Request(sos));
    const pending = worker.navigate({ request: sosEvent.request, url: sos, event: sosEvent });
    await new Promise((resolve) => { setTimeout(resolve, 40); });
    release();
    expect(await (await pending).text()).toContain('Réseau');
    await sosEvent.settle();
  });
});

describe('déconnexion', () => {
  it.each(['/deconnexion', '/api/v1/auth/logout'])('POST %s : pages et fiches rechargées sans cookie, fichiers gardés', async (path) => {
    const caches = new FakeCaches();
    const fiche = vi.fn((request) => guest(`Fiche ${request.credentials}`));
    const { worker, fetch } = setup({ caches, routes: { '/urgences': guest('Urgences'), '/sos': guest('SOS vierge'), '/ressources/kit': fiche } });
    await (await caches.open(PAGES_CACHE)).put(`${ORIGIN}/sos`, html('Zone du profil'));
    await (await caches.open(PAGES_CACHE)).put(`${ORIGIN}/thematiques/droits`, html('En-tête membre'));
    const resources = await caches.open(RESOURCES_CACHE);
    await resources.put(`${ORIGIN}/ressources/kit`, html('Fiche de session'));
    await resources.put(`${ORIGIN}/ressources/kit/telecharger`, new Response('%PDF'));

    const event = fakeEvent(new Request(ORIGIN + path, { method: 'POST' }));
    worker.observe(event);
    await event.settle();

    expect(caches.paths(PAGES_CACHE)).toEqual(['/sos', '/urgences']);
    expect(await (await caches.match(`${ORIGIN}/sos`)).text()).toContain('SOS vierge');
    expect(await (await resources.match(`${ORIGIN}/ressources/kit`)).text()).toContain('Fiche omit');
    expect(await (await resources.match(`${ORIGIN}/ressources/kit/telecharger`)).text()).toBe('%PDF');
    expect(fetch.mock.calls.every(([request]) => request.credentials === 'omit')).toBe(true);
  });

  it('ignore les autres envois', async () => {
    const { worker, fetch } = setup();
    const event = fakeEvent(new Request(`${ORIGIN}/api/v1/signalements`, { method: 'POST' }));
    worker.observe(event);
    await event.settle();
    expect(fetch).not.toHaveBeenCalled();
  });
});

describe('ressources hors ligne', () => {
  const file = (body) => new Response(body, { status: 200, headers: { 'Content-Type': 'application/pdf' } });

  it('message offline-resources : ajoute seulement ce qui manque, retire ce qui n’est plus enregistré ni marqué', async () => {
    const caches = new FakeCaches();
    const { worker, fetch } = setup({
      caches,
      config: { marked: [{ url: '/ressources/marquee', file: null }] },
      routes: { '/ressources/a': guest('A'), '/ressources/a/telecharger': file('A'), '/ressources/marquee': guest('M') },
    });
    const resources = await caches.open(RESOURCES_CACHE);
    await resources.put(`${ORIGIN}/ressources/marquee`, guest('M'));
    await resources.put(`${ORIGIN}/ressources/ancienne`, guest('Retirée'));

    const event = fakeEvent(null);
    worker.message({ ...event, origin: ORIGIN, data: { type: 'offline-resources', items: { a: { url: `${ORIGIN}/ressources/a`, file: '/ressources/a/telecharger' } } } });
    await event.settle();

    expect(caches.paths(RESOURCES_CACHE)).toEqual(['/ressources/a', '/ressources/a/telecharger', '/ressources/marquee']);
    expect(fetch.mock.calls.map(([request]) => new URL(request.url).pathname).sort()).toEqual(['/ressources/a', '/ressources/a/telecharger']);
    expect((await worker.readState()).device).toEqual(['/ressources/a', '/ressources/a/telecharger']);

    const foreign = fakeEvent(null);
    worker.message({ ...foreign, origin: 'https://ailleurs.test', data: { type: 'offline-resources', items: {} } });
    await foreign.settle();
    expect(caches.paths(RESOURCES_CACHE)).toHaveLength(3);
  });

  it('installation : seulement les ressources marquées manquantes ou modifiées, retrait de celles qui ne le sont plus', async () => {
    const caches = new FakeCaches();
    const routes = { '/urgences': guest('U'), '/sos': guest('S'), '/ressources/kit': guest('Kit'), '/ressources/kit/lire': file('Vidéo v1'), '/ressources/neuve': guest('Neuve') };
    const first = setup({ caches, routes, config: { marked: [{ url: '/ressources/kit', file: '/ressources/kit/lire', rev: '1' }, { url: '/ressources/gardee', file: null, rev: '1' }] } });
    routes['/ressources/gardee'] = guest('Gardée');
    await first.worker.install();
    expect(caches.paths(RESOURCES_CACHE)).toEqual(['/ressources/gardee', '/ressources/kit', '/ressources/kit/lire']);

    // Même révision : rien n'est rechargé (seules les pages le sont) ; « gardée » n'est plus marquée mais l'appareil l'a enregistrée.
    await (await caches.open(META_CACHE)).put(`${ORIGIN}/__xm-sw/state`, new Response(JSON.stringify({ ...(await first.worker.readState()), device: ['/ressources/gardee'] })));
    const second = setup({ caches, routes, config: { marked: [{ url: '/ressources/kit', file: '/ressources/kit/lire', rev: '1' }, { url: '/ressources/neuve', file: null, rev: '1' }] } });
    await second.worker.install();
    const loaded = second.fetch.mock.calls.map(([request]) => new URL(request.url).pathname).sort();
    expect(loaded).toEqual(['/ressources/neuve', '/sos', '/urgences']);
    expect(caches.paths(RESOURCES_CACHE)).toEqual(['/ressources/gardee', '/ressources/kit', '/ressources/kit/lire', '/ressources/neuve']);

    // Fichier modifié (révision 2) : rechargé ; « neuve » n'est plus marquée ni enregistrée : retirée.
    routes['/ressources/kit/lire'] = file('Vidéo v2');
    const third = setup({ caches, routes, config: { marked: [{ url: '/ressources/kit', file: '/ressources/kit/lire', rev: '2' }] } });
    await third.worker.install();
    expect(caches.paths(RESOURCES_CACHE)).toEqual(['/ressources/gardee', '/ressources/kit', '/ressources/kit/lire']);
    expect(await (await caches.match(`${ORIGIN}/ressources/kit/lire`)).text()).toBe('Vidéo v2');
  });

  it('garde un fichier marqué redirigé vers son lien signé, servi depuis le cache, avec Range', async () => {
    const caches = new FakeCaches();
    const redirected = () => {
      const response = new Response('octets', { status: 200, headers: { 'X-From': 'cache' } });
      Object.defineProperty(response, 'redirected', { value: true });
      return response;
    };
    const { worker, partial, fetch } = setup({ caches, routes: { '/ressources/kit/lire': redirected } });
    expect(await worker.warm(['/ressources/kit/lire'], RESOURCES_CACHE, true)).toEqual(['/ressources/kit/lire']);
    expect(await worker.warm(['/ressources/kit/lire'], PAGES_CACHE)).toEqual([]);
    fetch.mockClear();

    const url = new URL(`${ORIGIN}/ressources/kit/lire`);
    const full = await worker.resourceFile({ request: new Request(url), url });
    expect(await full.text()).toBe('octets');
    expect(full.redirected).toBe(false);

    const ranged = await worker.resourceFile({ request: new Request(url, { headers: { Range: 'bytes=0-6' } }), url });
    expect(ranged.status).toBe(206);
    expect(partial).toHaveBeenCalledTimes(1);
    expect(fetch).not.toHaveBeenCalled();

    const other = new URL(`${ORIGIN}/ressources/autre/lire`);
    expect((await worker.resourceFile({ request: new Request(other), url: other })).status).toBe(404);
  });

  it('révisions par chemin (page et fichier)', () => {
    expect(markedRevisions([{ url: `${ORIGIN}/ressources/kit`, file: '/ressources/kit/lire', rev: 7 }, null], ORIGIN))
      .toEqual({ '/ressources/kit': '7', '/ressources/kit/lire': '7' });
  });
});

it('activation : retire les caches xomiton inconnus, garde les siens et ceux de Workbox', async () => {
  const caches = new FakeCaches();
  await Promise.all(['xomiton-pages', 'xomiton-ressources', 'xomiton-api', META_CACHE, 'xomiton-ancien', 'workbox-precache-v2'].map((name) => caches.open(name)));
  const { worker } = setup({ caches });
  await worker.activate();
  expect((await caches.keys()).sort()).toEqual(['workbox-precache-v2', 'xomiton-api', 'xomiton-pages', 'xomiton-ressources', META_CACHE].sort());
});
