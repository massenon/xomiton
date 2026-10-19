// @vitest-environment jsdom
// Boîte d'envoi commune (P5-10, ADR-26, ADR-32) sur un IndexedDB factice (fake-indexeddb) : normalisation des entrées
// (forme P5-10 et remise de sos.js), corps multipart sans `_token`, ordre SOS d'abord, Idempotency-Key gardée à chaque
// essai, X-XSRF-TOKEN repris du cookie et rejeu après 419, recul sur erreur réseau / 5xx / 202, 422 (retirée, ou SOS
// renvoyé sans les champs refusés sous la clé dérivée que prend aussi sos.js), évènements queued / flushed, drapeau
// localStorage, déclencheurs, compte de la mise en file (X-Xm-Outbox-Account, signalement d'un compte gardé pour une
// page du même compte), expiration après `ttlDays` ; P9-07 : collecte et messages (`client_uuid`), SOS immédiat.
import 'fake-indexeddb/auto';
import { IDBFactory } from 'fake-indexeddb';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  ENDPOINTS, EVENTS, FLUSH_INTERVAL, PENDING_KEY, backoff, classify, compareEntries, createEntry, createOutbox, formBody,
  readCookie, requestBody, stripFields, uuid, ACCOUNT_HEADER, PRIORITIES,
} from './outbox.js';
import { derivedKey } from './sos-retry.js';

const json = (status, body = {}, headers = {}) => ({
  status,
  ok: status >= 200 && status < 300,
  headers: { get: (name) => headers[name] ?? null },
  json: async () => body,
});

const memoryStorage = () => {
  const data = new Map();
  return {
    getItem: (k) => (data.has(k) ? data.get(k) : null),
    setItem: (k, v) => data.set(k, String(v)),
    removeItem: (k) => data.delete(k),
  };
};

let clock;
const make = (overrides = {}) => {
  const events = [];
  const target = new EventTarget();
  target.addEventListener(EVENTS.queued, (e) => events.push(['queued', e.detail]));
  target.addEventListener(EVENTS.flushed, (e) => events.push(['flushed', e.detail]));
  const box = createOutbox({
    autoFlush: false, target, storage: memoryStorage(), locks: null, now: () => clock, cookies: () => 'XSRF-TOKEN=abc%3D%3D', ...overrides,
  });
  return { box, events, target };
};

beforeEach(() => {
  globalThis.indexedDB = new IDBFactory();
  clock = 1_700_000_000_000;
});

afterEach(() => {
  vi.useRealTimers();
});

describe('helpers', () => {
  it('lit un cookie et produit des UUID v4', () => {
    expect(readCookie('XSRF-TOKEN', 'a=1; XSRF-TOKEN=x%2By')).toBe('x+y');
    expect(readCookie('absent', 'a=1')).toBeNull();
    expect(uuid()).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    expect(uuid({})).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('normalise la remise de sos.js : point d’API, JSON, priorité SOS, clé d’idempotence = uuid', () => {
    const entry = createEntry({ uuid: 'k-1', type: 'sos', payload: { share_location: false }, created_at: '2026-10-18T10:00:00Z' }, 5);
    expect(entry).toMatchObject({
      id: 'k-1', type: 'sos', url: ENDPOINTS.sos, method: 'POST', body: '{"share_location":false}', priority: 0,
      idempotencyKey: 'k-1', createdAt: Date.parse('2026-10-18T10:00:00Z'), attempts: 0,
    });
    expect(entry.headers).toEqual({ Accept: 'application/json', 'Content-Type': 'application/json' });
  });

  it('garde la forme P5-10 et ne stocke jamais de jeton', () => {
    const entry = createEntry({
      url: '/api/v1/sync/push', body: { a: 1 }, priority: 3, idempotencyKey: 'key',
      headers: { 'X-XSRF-TOKEN': 'old', 'Idempotency-Key': 'x', 'X-Device': 'd1' },
    }, 9);
    expect(entry).toMatchObject({ id: 'key', type: 'default', url: '/api/v1/sync/push', body: '{"a":1}', priority: 3, createdAt: 9 });
    expect(entry.headers).toEqual({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-Device': 'd1' });
    expect(() => createEntry({ type: 'inconnu' })).toThrow(TypeError);
  });

  it('écarte `_token` et les champs fichier vides du corps multipart, puis le reconstruit', () => {
    const data = new FormData();
    data.append('_token', 'csrf');
    data.append('type', 'report');
    data.append('needs[]', 'ecoute');
    data.append('attachments[]', new File([], ''));
    data.append('attachments[]', new File(['%PDF'], 'preuve.pdf', { type: 'application/pdf' }));
    const body = formBody(data);
    expect(body.form.map(([name]) => name)).toEqual(['type', 'needs[]', 'attachments[]']);
    const rebuilt = requestBody(body);
    expect(rebuilt).toBeInstanceOf(FormData);
    expect(rebuilt.get('type')).toBe('report');
    expect(rebuilt.get('attachments[]').name).toBe('preuve.pdf');
    const unnamed = requestBody({ form: [['audio', new Blob(['ogg'], { type: 'audio/ogg' })]] });
    expect(unnamed.get('audio').name).toBe('blob'); // nom par défaut du navigateur, aucun texte en dur
    expect(requestBody('{"a":1}')).toBe('{"a":1}');
    expect(requestBody(null)).toBeUndefined();
  });

  it('classe les statuts, ordonne SOS d’abord, recule avec plafond', () => {
    expect([201, 200, 202, 419, 422, 0, 408, 429, 503, 403, 404].map(classify))
      .toEqual(['sent', 'sent', 'processing', 'csrf', 'invalid', 'retry', 'retry', 'retry', 'retry', 'failed', 'failed']);
    const list = [{ priority: 10, createdAt: 1 }, { priority: 0, createdAt: 5 }, { priority: 0, createdAt: 2 }].sort(compareEntries);
    expect(list.map((e) => `${e.priority}/${e.createdAt}`)).toEqual(['0/2', '0/5', '10/1']);
    expect([1, 2, 3].map((n) => backoff(n))).toEqual([5000, 10000, 20000]);
    expect(backoff(10, 'sos')).toBe(30000);
    expect(backoff(20)).toBe(FLUSH_INTERVAL);
    expect(stripFields('{"lat":1,"lng":2,"zone_id":3}', ['lat', 'lng'])).toBe('{"zone_id":3}');
  });
});

describe('createOutbox', () => {
  it('met en file, remplace une entrée de même clé, annonce queued et tient le drapeau localStorage', async () => {
    const storage = memoryStorage();
    const { box, events } = make({ storage });
    await box.add({ uuid: 'k1', type: 'sos', payload: { a: 1 } });
    await box.add({ uuid: 'k1', type: 'sos', payload: { a: 1 } });
    expect(await box.count()).toBe(1);
    expect(storage.getItem(PENDING_KEY)).toBe('1');
    expect(events[0]).toEqual(['queued', { id: 'k1', type: 'sos', pending: 1 }]);
  });

  it('rejoue SOS d’abord avec sa clé, X-XSRF-TOKEN du cookie, puis vide la file et annonce flushed', async () => {
    const calls = [];
    const fetch = vi.fn(async (url, init) => {
      calls.push({ url, init });
      return json(201, { data: { tracking_code: url.includes('signalements') ? 'SIG-1' : 'SOS-1' } });
    });
    const storage = memoryStorage();
    const { box, events } = make({ fetch, storage });
    const data = new FormData();
    data.append('_token', 'old');
    data.append('description', 'Des faits assez longs pour être valides.');
    await box.add({ type: 'signalement', url: '/api/v1/signalements', body: formBody(data), idempotencyKey: 'r1' });
    clock += 1000;
    await box.add({ uuid: 's1', type: 'sos', payload: { share_location: false } });

    const report = await box.flush();

    expect(calls.map((c) => c.url)).toEqual(['/api/v1/sos', '/api/v1/signalements']);
    expect(calls[0].init.headers).toMatchObject({ 'Idempotency-Key': 's1', 'X-XSRF-TOKEN': 'abc==', 'Content-Type': 'application/json' });
    expect(calls[0].init).toMatchObject({ method: 'POST', credentials: 'same-origin', body: '{"share_location":false}' });
    expect(calls[1].init.headers['Idempotency-Key']).toBe('r1');
    expect(calls[1].init.headers['Content-Type']).toBeUndefined();
    expect(calls[1].init.body.get('_token')).toBeNull();
    expect(report.sent.map((r) => [r.type, r.data.tracking_code])).toEqual([['sos', 'SOS-1'], ['signalement', 'SIG-1']]);
    expect(report.pending).toBe(0);
    expect(await box.count()).toBe(0);
    expect(storage.getItem(PENDING_KEY)).toBeNull();
    expect(events.at(-1)[0]).toBe('flushed');
  });

  it('garde l’entrée et la même clé sur coupure réseau ou 5xx, avec recul, puis l’envoie', async () => {
    const keys = [];
    const responses = [() => { throw new TypeError('Failed to fetch'); }, () => json(503), () => json(200, { data: { id: 'x' } })];
    const fetch = vi.fn(async (url, init) => { keys.push(init.headers['Idempotency-Key']); return responses.shift()(); });
    const { box } = make({ fetch });
    await box.add({ uuid: 'k', type: 'sos', payload: {} });

    expect((await box.flush()).pending).toBe(1);
    let [entry] = await box.list();
    expect(entry).toMatchObject({ attempts: 1, lastStatus: 0, nextAttemptAt: clock + 5000 });

    expect((await box.flush()).sent).toEqual([]); // pas encore dû
    expect(fetch).toHaveBeenCalledTimes(1);
    expect((await box.flush({ force: true })).pending).toBe(1);
    [entry] = await box.list();
    expect(entry).toMatchObject({ attempts: 2, lastStatus: 503 });

    clock += 60_000;
    expect((await box.flush()).sent).toHaveLength(1);
    expect(keys).toEqual(['k', 'k', 'k']);
  });

  it('attend Retry-After sur 202 (SOS en cours de traitement)', async () => {
    const fetch = vi.fn(async () => json(202, { data: { status: 'processing' } }, { 'Retry-After': '3' }));
    const { box } = make({ fetch });
    await box.add({ uuid: 'k', type: 'sos', payload: {} });
    await box.flush();
    const [entry] = await box.list();
    expect(entry.nextAttemptAt).toBe(clock + 3000);
  });

  it('rafraîchit le cookie XSRF sur 419 puis rejoue', async () => {
    let cookie = 'XSRF-TOKEN=old';
    const fetch = vi.fn(async (url, init) => {
      if (url === '/sanctum/csrf-cookie') { cookie = 'XSRF-TOKEN=new'; return json(204); }
      return init.headers['X-XSRF-TOKEN'] === 'new' ? json(201, { data: {} }) : json(419);
    });
    const { box } = make({ fetch, cookies: () => cookie });
    await box.add({ uuid: 'k', type: 'signalement', body: '{}' });
    const report = await box.flush();
    expect(fetch.mock.calls.map(([url]) => url)).toEqual(['/api/v1/signalements', '/sanctum/csrf-cookie', '/api/v1/signalements']);
    expect(report.sent).toHaveLength(1);
  });

  it('retire un signalement refusé (422) et le signale dans flushed.failed', async () => {
    const fetch = vi.fn(async () => json(422, { code: 'validation_failed', errors: { description: ['Trop court.'] } }));
    const { box } = make({ fetch });
    await box.add({ uuid: 'k', type: 'signalement', body: '{}' });
    const report = await box.flush();
    expect(report.failed).toEqual([{ id: 'k', type: 'signalement', state: 'invalid', status: 422, errors: { description: ['Trop court.'] } }]);
    expect(await box.count()).toBe(0);
  });

  it('renvoie un SOS refusé (422) une fois, sans les champs refusés et sous la clé dérivée de sos.js (RG-01)', async () => {
    const seen = [];
    const fetch = vi.fn(async (url, init) => {
      seen.push([init.headers['Idempotency-Key'], init.body]);
      return seen.length === 1 ? json(422, { errors: { lat: ['x'], lng: ['x'] } }) : json(201, { data: { id: 'a' } });
    });
    const { box } = make({ fetch });
    await box.add({ uuid: 'k', type: 'sos', payload: { lat: 99, lng: 99, zone_id: 3 } });
    const report = await box.flush();
    expect(seen[0]).toEqual(['k', '{"lat":99,"lng":99,"zone_id":3}']);
    expect(seen[1][0]).toBe(derivedKey('k'));
    expect(seen[1][1]).toBe('{"zone_id":3}'); // position refusée : partie entière, comme sos.js
    expect(stripFields('{"zone_id":3,"commune_id":4,"accuracy":5,"lat":1}', ['zone_id', 'lat'])).toBe('{}');
    expect(report.sent).toHaveLength(1);
  });

  it('n’envoie rien hors ligne et ne lance qu’un passage à la fois', async () => {
    let online = false;
    const fetch = vi.fn(async () => json(201, { data: {} }));
    const { box, events } = make({ fetch, online: () => online });
    await box.add({ uuid: 'k', type: 'sos', payload: {} });
    expect((await box.flush()).pending).toBe(1);
    expect(fetch).not.toHaveBeenCalled();
    expect(events.filter(([name]) => name === 'flushed')).toHaveLength(0);
    online = true;
    const [a, b] = [box.flush(), box.flush()];
    expect(a).toBe(b);
    await a;
    expect(fetch).toHaveBeenCalledTimes(1);
  });

  it('passe par Web Locks quand ils existent (un onglet à la fois)', async () => {
    const request = vi.fn((name, job) => job());
    const { box } = make({ fetch: vi.fn(async () => json(201, { data: {} })), locks: { request } });
    await box.add({ uuid: 'k', type: 'sos', payload: {} });
    await box.flush();
    expect(request).toHaveBeenCalledWith('xm-outbox', expect.any(Function));
  });

  it('rejoue au retour du réseau et toutes les 5 minutes une fois démarrée', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    const win = new EventTarget();
    let online = false;
    const fetch = vi.fn(async () => json(201, { data: {} }));
    const box = createOutbox({ autoFlush: false, target: win, storage: null, locks: null, fetch, online: () => online });
    const stop = box.start(win);
    await box.add({ uuid: 'k', type: 'sos', payload: {} });
    online = true;
    win.dispatchEvent(new Event('online'));
    await vi.waitFor(async () => expect(await box.count()).toBe(0));

    online = false;
    await box.add({ uuid: 'k2', type: 'signalement', body: '{}' });
    online = true;
    expect(fetch).toHaveBeenCalledTimes(1);
    vi.advanceTimersByTime(FLUSH_INTERVAL);
    await vi.waitFor(async () => expect(await box.count()).toBe(0));
    expect(fetch).toHaveBeenCalledTimes(2);
    stop();
  });

  it('annonce le compte de la mise en file ; garde le signalement d’un compte pour une page de ce compte, jamais un SOS', async () => {
    const calls = [];
    const fetch = vi.fn(async (url, init) => { calls.push([url, init.headers[ACCOUNT_HEADER]]); return json(201, { data: {} }); });
    let current = '';
    const { box } = make({ fetch, account: () => current });
    await box.add({ id: 'g', type: 'signalement', url: '/api/v1/signalements', body: '{}', account: '' });
    await box.add({ id: 'm', type: 'signalement', url: '/api/v1/signalements', body: '{}', account: 'compte-a' });
    await box.add({ uuid: 's', type: 'sos', payload: {}, account: 'compte-a' });
    await box.add({ id: 'x', type: 'signalement', url: '/api/v1/signalements', body: '{}' });

    let report = await box.flush();
    expect(calls).toEqual([['/api/v1/sos', 'compte-a'], ['/api/v1/signalements', 'guest'], ['/api/v1/signalements', undefined]]);
    expect(report.pending).toBe(1);
    expect((await box.get('m')).attempts).toBe(0);

    current = 'compte-b';
    report = await box.flush();
    expect(report.pending).toBe(1);

    current = 'compte-a';
    report = await box.flush();
    expect(calls.at(-1)).toEqual(['/api/v1/signalements', 'compte-a']);
    expect(report.pending).toBe(0);
  });

  it('purge les entrées plus anciennes que ttlDays (contenu en clair sur l’appareil), même hors ligne', async () => {
    const fetch = vi.fn(async () => json(201, { data: {} }));
    const { box } = make({ fetch, ttlDays: 2, online: () => false });
    await box.add({ uuid: 'vieille', type: 'sos', payload: {}, created_at: new Date(clock - 3 * 86_400_000).toISOString() });
    await box.add({ uuid: 'recente', type: 'sos', payload: {}, created_at: new Date(clock - 86_400_000).toISOString() });
    const report = await box.flush();
    expect(report.pending).toBe(1);
    expect(await box.get('vieille')).toBeUndefined();
    expect(await box.get('recente')).toBeDefined();
    expect(fetch).not.toHaveBeenCalled();

    const fallback = make({ fetch }).box; // 7 jours par défaut
    await fallback.add({ uuid: 'six', type: 'sos', payload: {}, created_at: new Date(clock - 6 * 86_400_000).toISOString() });
    expect(await fallback.purge()).toBe(0);
    clock += 2 * 86_400_000;
    expect(await fallback.purge()).toBe(1);
  });
});

// P9-07 (lot synchro) : collecte et messages dans la même boîte d'envoi, SOS jamais en attente si le réseau est là.
describe('P9-07 : collecte, messages, SOS immédiat', () => {
  it('normalise une collecte (POST pair/cases) et un message : priorités, client_uuid = clé dans le corps JSON', () => {
    const collecte = createEntry({ uuid: 'c-1', type: 'collecte', payload: { kind: 'VBG', zone_id: 3 } }, 1);
    expect(collecte).toMatchObject({ url: ENDPOINTS.collecte, priority: PRIORITIES.collecte, idempotencyKey: 'c-1' });
    expect(JSON.parse(collecte.body)).toEqual({ kind: 'VBG', zone_id: 3, client_uuid: 'c-1' });

    const message = createEntry({ type: 'message', url: '/api/v1/conversations/01J/messages', body: { body: 'Salut' }, idempotencyKey: 'm-1' }, 1);
    expect(message).toMatchObject({ url: '/api/v1/conversations/01J/messages', priority: PRIORITIES.message });
    expect(JSON.parse(message.body)).toEqual({ body: 'Salut', client_uuid: 'm-1' });

    // Clé déjà portée par le corps, corps multipart ou autre type : jamais réécrits.
    expect(JSON.parse(createEntry({ type: 'message', url: '/x', body: { client_uuid: 'k' }, idempotencyKey: 'k' }).body)).toEqual({ client_uuid: 'k' });
    expect(createEntry({ type: 'message', url: '/x', body: { form: [['body', 'a']] }, idempotencyKey: 'k' }).body).toEqual({ form: [['body', 'a']] });
    expect(JSON.parse(createEntry({ uuid: 's', type: 'sos', payload: { a: 1 } }).body)).toEqual({ a: 1 });
    expect(() => createEntry({ type: 'message', body: {} })).toThrow(TypeError);
    expect([PRIORITIES.sos, PRIORITIES.signalement, PRIORITIES.collecte, PRIORITIES.message, PRIORITIES.default]).toEqual([0, 10, 20, 30, 50]);
  });

  it('envoie SOS, signalement, collecte puis message dans un passage', async () => {
    const urls = [];
    const fetch = vi.fn(async (url) => { urls.push(url); return json(201, { data: {} }); });
    const { box } = make({ fetch });
    await box.add({ type: 'message', url: '/api/v1/conversations/01J/messages', body: { body: 'a' }, idempotencyKey: 'm' });
    await box.add({ uuid: 'c', type: 'collecte', payload: { kind: 'VDH' } });
    await box.add({ uuid: 'r', type: 'signalement', payload: { type: 'report' } });
    await box.add({ uuid: 's', type: 'sos', payload: {} });

    await box.flush();
    expect(urls).toEqual(['/api/v1/sos', '/api/v1/signalements', '/api/v1/pair/cases', '/api/v1/conversations/01J/messages']);
  });

  it('n’attend jamais pour un SOS si le réseau est là : envoyé pendant un passage en cours, une seule fois', async () => {
    let release;
    const held = new Promise((resolve) => { release = resolve; });
    const calls = [];
    const fetch = vi.fn(async (url, init) => {
      calls.push([url, init.headers['Idempotency-Key']]);
      if (url === ENDPOINTS.collecte) { await held; return json(201, { data: { id: 7 } }); }
      return json(201, { data: { id: 'sos' } });
    });
    const { box, events } = make({ fetch, autoFlush: true });

    await box.add({ uuid: 'c', type: 'collecte', payload: { kind: 'VBG' } }); // lance un passage, bloqué sur la collecte
    await vi.waitFor(() => expect(calls).toHaveLength(1));
    const pass = box.flush();

    await box.add({ uuid: 's', type: 'sos', payload: { message: 'Aide' } });
    await vi.waitFor(() => expect(calls).toEqual([[ENDPOINTS.collecte, 'c'], [ENDPOINTS.sos, 's']]));
    await vi.waitFor(async () => expect(await box.get('s')).toBeUndefined());
    expect(events.some(([name, detail]) => name === 'flushed' && detail.sent.some((r) => r.id === 's'))).toBe(true);

    release();
    await pass;
    await box.flush({ force: true });
    expect(calls.filter(([url]) => url === ENDPOINTS.sos)).toHaveLength(1);
    expect(await box.count()).toBe(0);
  });

  it('garde un SOS immédiat sur coupure réseau, avec sa clé, pour le passage suivant', async () => {
    const responses = [() => { throw new TypeError('Failed to fetch'); }, () => json(201, { data: {} })];
    const fetch = vi.fn(async () => responses.shift()());
    const { box } = make({ fetch });

    await box.add({ uuid: 's', type: 'sos', payload: {} });
    const first = await box.express(await box.get('s'));
    expect(first.pending).toBe(1);
    expect((await box.get('s')).attempts).toBe(1);

    const second = await box.flush({ force: true });
    expect(second.sent.map((r) => r.id)).toEqual(['s']);
    expect(fetch.mock.calls.map(([, init]) => init.headers['Idempotency-Key'])).toEqual(['s', 's']);
  });
});
