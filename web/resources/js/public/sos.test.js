// @vitest-environment jsdom
// Envoi d'un SOS (P5-05 / P5-08) : client de l'API, page xmSosPage et bouton flottant xmSosFab, sans Alpine ni réseau.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { derivedKey } from '../shared/sos-retry.js';
import {
  SHARE_KEY, buildPayload, createSosClient, locate, readCookie, readShare, retryDelay, strip, telHref, uuid, writeShare, xmSosFab, xmSosPage,
} from './sos.js';

const reply = (status, body = {}, headers = {}) => ({
  ok: status >= 200 && status < 300,
  status,
  json: async () => body,
  headers: { get: (name) => headers[name] ?? null },
});

const created = (extra = {}) => reply(201, {
  data: { id: '01JABCDEF', type: 'sos', cancelled: false, safe: false, created_at: new Date().toISOString(), can_cancel_until: new Date(Date.now() + 60000).toISOString(), notified: [], primary_contact: null, sos_token: 'secret-token', ...extra },
  meta: { poll_interval_seconds: 10 },
});

const memoryStorage = () => {
  const map = new Map();
  return { getItem: (k) => (map.has(k) ? map.get(k) : null), setItem: (k, v) => map.set(k, String(v)) };
};

const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

describe('fonctions pures', () => {
  it('construit la charge utile : position seulement si partagée, zone et commune entières, champs vides retirés', () => {
    const position = { lat: 6.3654321987, lng: 2.4183456789, accuracy: 23.6 };
    expect(buildPayload({ share: true, position, zoneId: '3', communeId: '12', phone: ' +229 01 97 00 00 00 ', message: '' }))
      .toEqual({ share_location: true, lat: 6.365432, lng: 2.418346, accuracy: 24, zone_id: 3, commune_id: 12, phone: '+229 01 97 00 00 00' });
    expect(buildPayload({ share: false, position, zoneId: '', communeId: '12' })).toEqual({ share_location: false });
    expect(buildPayload({ zoneId: 4 })).toEqual({ zone_id: 4 });
  });

  it('retire les champs refusés (422), la position entière et la commune avec le département', () => {
    const payload = { share_location: true, lat: 1, lng: 2, accuracy: 3, zone_id: 1, commune_id: 2, phone: 'x' };
    expect(strip(payload, ['lng', 'phone'])).toEqual({ share_location: true, zone_id: 1, commune_id: 2 });
    expect(strip(payload, ['zone_id'])).toEqual({ share_location: true, lat: 1, lng: 2, accuracy: 3, phone: 'x' });
  });

  it('produit un UUID v4 valide, même sans crypto.randomUUID', () => {
    const re = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
    expect(uuid()).toMatch(re);
    expect(uuid({ getRandomValues: (b) => b.fill(255) })).toMatch(re);
    expect(uuid(null)).toMatch(re);
  });

  it('lit le cookie XSRF-TOKEN, borne le recul, nettoie le lien d’appel', () => {
    expect(readCookie('XSRF-TOKEN', 'a=1; XSRF-TOKEN=abc%3D')).toBe('abc=');
    expect(readCookie('XSRF-TOKEN', 'a=1')).toBeNull();
    expect(retryDelay(0)).toBe(5);
    expect(retryDelay(40)).toBe(60);
    expect(telHref('+229 01 97 38 88 47')).toBe('tel:+2290197388847');
  });

  it('mémorise le choix de partage d’un·e visiteur·se, sans jamais échouer', () => {
    const storage = memoryStorage();
    expect(readShare(true, storage)).toBe(true);
    writeShare(false, storage);
    expect(storage.getItem(SHARE_KEY)).toBe('0');
    expect(readShare(true, storage)).toBe(false);
    const broken = { getItem: () => { throw new Error('bloqué'); }, setItem: () => { throw new Error('bloqué'); } };
    expect(readShare(true, broken)).toBe(true);
    expect(() => writeShare(true, broken)).not.toThrow();
  });
});

describe('locate', () => {
  afterEach(() => vi.useRealTimers());

  it('rend la position, null sur refus ou absence de géolocalisation', async () => {
    const ok = { getCurrentPosition: (success) => success({ coords: { latitude: 6.36, longitude: 2.41, accuracy: 30 } }) };
    expect(await locate(ok, 1000)).toEqual({ lat: 6.36, lng: 2.41, accuracy: 30 });
    expect(await locate({ getCurrentPosition: (s, fail) => fail(new Error('refus')) }, 1000)).toBeNull();
    expect(await locate(null, 1000)).toBeNull();
  });

  it('abandonne au bout du délai même si l’invite d’autorisation reste ouverte', async () => {
    vi.useFakeTimers();
    const pending = locate({ getCurrentPosition: () => {} }, 12000);
    vi.advanceTimersByTime(12000);
    expect(await pending).toBeNull();
  });
});

describe('createSosClient', () => {
  const offline = () => Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => false });
  const online = () => Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => true });
  beforeEach(online);
  afterEach(online);

  it('envoie Idempotency-Key, X-XSRF-TOKEN et la charge utile en JSON', async () => {
    const fetch = vi.fn().mockResolvedValue(created());
    const client = createSosClient({ fetch, endpoint: '/api/v1/sos', cookies: 'XSRF-TOKEN=jeton' });
    const r = await client.send({ share_location: true }, 'key-1');
    expect(r.state).toBe('sent');
    expect(r.data.id).toBe('01JABCDEF');
    const [url, init] = fetch.mock.calls[0];
    expect(url).toBe('/api/v1/sos');
    expect(init.method).toBe('POST');
    expect(init.headers['Idempotency-Key']).toBe('key-1');
    expect(init.headers['X-XSRF-TOKEN']).toBe('jeton');
    expect(JSON.parse(init.body)).toEqual({ share_location: true });
  });

  it('419 : passe par /sanctum/csrf-cookie puis rejoue une fois', async () => {
    const fetch = vi.fn().mockResolvedValueOnce(reply(419)).mockResolvedValueOnce(reply(204)).mockResolvedValueOnce(created());
    const r = await createSosClient({ fetch }).send({}, 'k');
    expect(r.state).toBe('sent');
    expect(fetch.mock.calls[1][0]).toBe('/sanctum/csrf-cookie');
    expect(fetch.mock.calls[2][1].headers['Idempotency-Key']).toBe('k');
  });

  it('202 processing : nouvel essai après Retry-After avec la même clé', async () => {
    const wait = vi.fn().mockResolvedValue();
    const fetch = vi.fn().mockResolvedValueOnce(reply(202, { data: { status: 'processing' } }, { 'Retry-After': '3' })).mockResolvedValueOnce(created());
    const r = await createSosClient({ fetch, wait }).send({}, 'k');
    expect(r.state).toBe('sent');
    expect(wait).toHaveBeenCalledWith(3000);
    expect(fetch.mock.calls.map((c) => c[1].headers['Idempotency-Key'])).toEqual(['k', 'k']);
  });

  it('422 : un seul nouvel envoi sans les champs refusés, sous la clé dérivée que prend aussi la boîte d’envoi', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce(reply(422, { code: 'validation_failed', errors: { phone: ['Numéro invalide.'] } }))
      .mockResolvedValueOnce(created());
    const r = await createSosClient({ fetch }).send({ share_location: false, phone: 'abc' }, 'k');
    expect(r.state).toBe('sent');
    expect(JSON.parse(fetch.mock.calls[1][1].body)).toEqual({ share_location: false });
    expect(fetch.mock.calls[1][1].headers['Idempotency-Key']).toBe(derivedKey('k'));

    const always = vi.fn().mockResolvedValue(reply(422, { errors: { message: ['x'] } }));
    expect((await createSosClient({ fetch: always }).send({ message: 'x' }, 'k')).state).toBe('failed');
    expect(always).toHaveBeenCalledTimes(2);
  });

  it('réseau coupé ou 5xx : « hors ligne » une fois, remise à la boîte d’envoi, puis envoi au retour du réseau avec la même clé', async () => {
    const outbox = vi.fn();
    window.XM = { outbox: { add: outbox } };
    const events = [];
    window.addEventListener('xm:outbox', (e) => events.push(e.detail));
    const waitOnline = vi.fn().mockResolvedValue();
    const fetch = vi.fn()
      .mockRejectedValueOnce(new TypeError('Failed to fetch'))
      .mockResolvedValueOnce(reply(503))
      .mockResolvedValueOnce(created());
    const onQueued = vi.fn();
    const r = await createSosClient({ fetch, waitOnline }).send({ zone_id: 2 }, 'k', { onQueued });
    expect(r.state).toBe('sent');
    expect(onQueued).toHaveBeenCalledTimes(1);
    expect(waitOnline.mock.calls.map((c) => c[0])).toEqual([5000, 10000]);
    expect(fetch.mock.calls.map((c) => c[1].headers['Idempotency-Key'])).toEqual(['k', 'k', 'k']);
    expect(outbox).toHaveBeenCalledWith(expect.objectContaining({ uuid: 'k', type: 'sos', payload: { zone_id: 2 } }));
    expect(events[0]).toMatchObject({ uuid: 'k', type: 'sos' });
    delete window.XM;
  });

  it('navigateur hors ligne : aucune requête avant le retour du réseau', async () => {
    offline();
    const fetch = vi.fn().mockResolvedValue(created());
    const waitOnline = vi.fn().mockImplementation(async () => online());
    const onQueued = vi.fn();
    const r = await createSosClient({ fetch, waitOnline }).send({}, 'k', { onQueued });
    expect(onQueued).toHaveBeenCalledWith(expect.objectContaining({ reason: 'offline' }));
    expect(fetch).toHaveBeenCalledTimes(1);
    expect(r.state).toBe('sent');
  });

  it('actions cancel / safe : jeton X-Sos-Token envoyé seulement s’il existe', async () => {
    const fetch = vi.fn().mockResolvedValue(reply(200, { data: { cancelled: true } }));
    const client = createSosClient({ fetch });
    expect((await client.action('/api/v1/sos/ID/cancel', 'POST', 'tok')).data.cancelled).toBe(true);
    expect(fetch.mock.calls[0][1].headers['X-Sos-Token']).toBe('tok');
    await client.action('/api/v1/sos/ID/safe', 'POST', null);
    expect(fetch.mock.calls[1][1].headers['X-Sos-Token']).toBeUndefined();
    const down = createSosClient({ fetch: vi.fn().mockRejectedValue(new Error('réseau')) });
    expect(await down.action('/x')).toEqual({ ok: false, status: 0 });
  });
});

describe('xmSosPage', () => {
  const page = (opts = {}) => {
    const p = xmSosPage({ storage: memoryStorage(), texts: { sent: 'Ton alerte est partie', queued: 'Tu es hors ligne.', undo: 'Annuler pendant :seconds s.', callPrimary: 'Appeler :name', auto: 'Départ dans :s s.' }, ...opts });
    p.$nextTick = (fn) => fn();
    p.$root = document.createElement('div');
    p.$watch = vi.fn();
    p.init();
    return p;
  };

  it('partage coché par défaut ; choix d’un·e visiteur·se relu du stockage, celui d’un compte vient du serveur', () => {
    const storage = memoryStorage();
    storage.setItem(SHARE_KEY, '0');
    expect(page().share).toBe(true);
    expect(page({ storage }).share).toBe(false);
    expect(page({ storage, member: true, share: true }).share).toBe(true);
    expect(page({ member: true, share: false }).share).toBe(false);
  });

  it('position partagée : localisation puis envoi avec la position ; jeton gardé hors de l’état', async () => {
    const send = vi.fn().mockResolvedValue({ state: 'sent', data: { id: 'ID1', sos_token: 'tok', notified: [], can_cancel_until: new Date(Date.now() + 60000).toISOString(), created_at: new Date().toISOString() }, meta: { poll_interval_seconds: 10 } });
    const action = vi.fn().mockResolvedValue({ ok: true, data: { cancelled: true } });
    const geolocation = { getCurrentPosition: (ok) => ok({ coords: { latitude: 6.36, longitude: 2.41, accuracy: 15 } }) };
    const p = page({ client: { send, action, stop: vi.fn() }, geolocation, showUrl: '/api/v1/sos/__ID__' });
    const resolve = vi.fn();
    await p.onSend({ resolve });
    expect(resolve).toHaveBeenCalledWith({ state: 'idle' });
    expect(send.mock.calls[0][0]).toMatchObject({ share_location: true, lat: 6.36, lng: 2.41, accuracy: 15 });
    expect(p.phase).toBe('sent');
    expect(p.alert.sos_token).toBeUndefined();
    expect(JSON.stringify(p)).not.toContain('tok');
    expect(p.notifiedOptions()).toMatchObject({ endpoint: '/api/v1/sos/ID1', token: 'tok', poll: 10 });
    expect(p.cancelLeft()).toBeGreaterThan(55);
    expect(p.undoText()).toMatch(/^Annuler pendant \d+ s\.$/);
    await p.cancel();
    expect(action).toHaveBeenCalledWith('/api/v1/sos/ID1/cancel', 'POST', 'tok');
    expect(p.phase).toBe('cancelled');
    p.destroy();
  });

  it('sans GPS ni zone : repli manuel, puis envoi automatique au bout du délai', async () => {
    vi.useFakeTimers();
    const send = vi.fn().mockResolvedValue({ state: 'sent', data: { id: 'ID2' }, meta: {} });
    const p = page({ client: { send, action: vi.fn(), stop: vi.fn() }, geolocation: null, manualSeconds: 15 });
    await p.onSend({ resolve: vi.fn() });
    expect(p.phase).toBe('manual');
    expect(p.autoText()).toBe('Départ dans 15 s.');
    p.zoneId = '5';
    vi.advanceTimersByTime(15000);
    await Promise.resolve();
    expect(send).toHaveBeenCalledTimes(1);
    expect(send.mock.calls[0][0]).toMatchObject({ share_location: true, zone_id: 5 });
    vi.useRealTimers();
    p.destroy();
  });

  it('partage refusé avec un département de profil : envoi direct, sans position', async () => {
    const send = vi.fn().mockResolvedValue({ state: 'sent', data: { id: 'ID3' }, meta: {} });
    const p = page({ client: { send, action: vi.fn(), stop: vi.fn() }, member: true, share: false, profile: { zone_id: 7, commune_id: 70 } });
    await p.onSend({});
    expect(send.mock.calls[0][0]).toEqual({ share_location: false, zone_id: 7, commune_id: 70 });
    expect(p.positionText()).toBe('');
    p.destroy();
  });

  it('hors ligne : état « queued » puis « envoyée » quand le réseau revient', async () => {
    let finish;
    const send = vi.fn().mockImplementation((payload, key, { onQueued }) => { onQueued({ reason: 'offline' }); return new Promise((r) => { finish = r; }); });
    const p = page({ client: { send, action: vi.fn(), stop: vi.fn() }, share: false, profile: { zone_id: 1 }, member: true });
    const pending = p.onSend({});
    await tick();
    expect(p.phase).toBe('queued');
    await tick();
    expect(p.announce).toBe('Tu es hors ligne.');
    finish({ state: 'sent', data: { id: 'ID4' }, meta: {} });
    await pending;
    await tick();
    expect(p.phase).toBe('sent');
    p.destroy();
  });

  it('SOS retrouvé (compte, ?alerte=) : état « envoyée », appel du contact principal, « Je suis en sécurité » sans jeton', async () => {
    const action = vi.fn().mockResolvedValue({ ok: true, data: { safe: true } });
    const initial = { data: { id: 'ID5', cancelled: false, safe: false, can_cancel_until: null, notified: [{ key: 'p1', label: 'Chantal H.', phone: '+229 01 00' }], primary_contact: { label: 'Chantal H.', phone: '+229 01 00' } }, meta: { poll_interval_seconds: 10 } };
    const p = page({ client: { send: vi.fn(), action, stop: vi.fn() }, initial, member: true });
    expect(p.phase).toBe('sent');
    expect(p.callPrimaryText()).toBe('Appeler Chantal H.');
    expect(p.callHref(p.primary().phone)).toBe('tel:+2290100');
    expect(p.cancelLeft()).toBe(0);
    await p.safe();
    expect(action).toHaveBeenCalledWith('/api/v1/sos/ID5/safe', 'POST', null);
    expect(p.alert.safe).toBe(true);
    p.restart();
    expect(p.phase).toBe('idle');
    p.destroy();
  });
});

describe('xmSosFab', () => {
  it('envoie sans GPS avec le département du profil puis ouvre le suivi', async () => {
    const send = vi.fn().mockResolvedValue({ state: 'sent', data: { id: 'ID6' } });
    const navigate = vi.fn();
    const fab = xmSosFab({ client: { send, stop: vi.fn() }, navigate, zoneId: 3, communeId: null, followUrl: '/sos?alerte=__ID__', texts: { sent: 'Ton alerte est partie.' } });
    const resolve = vi.fn();
    await fab.onSend({ resolve });
    expect(send.mock.calls[0][0]).toEqual({ zone_id: 3 });
    expect(resolve).toHaveBeenCalledWith(expect.objectContaining({ state: 'sent', title: 'Ton alerte est partie.' }));
    expect(navigate).toHaveBeenCalledWith('/sos?alerte=ID6');
  });

  it('hors ligne : panneau « hors ligne » avec appel du premier numéro ; échec : lien vers la page SOS', async () => {
    const queued = vi.fn().mockImplementation(async (p, k, { onQueued }) => { onQueued({}); return { state: 'stopped' }; });
    const resolve = vi.fn();
    await xmSosFab({ client: { send: queued, stop: vi.fn() }, number: '117', texts: { offlineTitle: 'Tu es hors ligne', call: 'Appeler le :number' } }).onSend({ resolve });
    expect(resolve).toHaveBeenCalledWith(expect.objectContaining({ state: 'queued', call: { href: 'tel:117', label: 'Appeler le 117' } }));

    const failed = vi.fn().mockResolvedValue({ state: 'failed', status: 422 });
    const r2 = vi.fn();
    await xmSosFab({ client: { send: failed, stop: vi.fn() }, pageUrl: '/sos', texts: { openPage: 'Ouvrir la page SOS' } }).onSend({ resolve: r2 });
    expect(r2).toHaveBeenCalledWith(expect.objectContaining({ state: 'failed', link: { href: '/sos', label: 'Ouvrir la page SOS' } }));
  });

  it('deux envois hors ligne : une seule alerte (même clé), le 2e appui remontre « en attente »', async () => {
    let online;
    const fetch = vi.fn()
      .mockRejectedValueOnce(new TypeError('Failed to fetch'))
      .mockResolvedValueOnce(reply(201, { data: { id: 'ID7' }, meta: {} }));
    const client = createSosClient({ fetch, cookies: '', waitOnline: () => new Promise((r) => { online = r; }) });
    const navigate = vi.fn();
    const fab = xmSosFab({ client, navigate, texts: { offlineTitle: 'Tu es hors ligne', sent: 'Ton alerte est partie.' } });
    const first = vi.fn();
    const sending = fab.onSend({ resolve: first });
    await tick();
    expect(first).toHaveBeenCalledWith(expect.objectContaining({ state: 'queued' }));

    const second = vi.fn();
    fab.onSend({ resolve: second });
    expect(second).toHaveBeenCalledWith(expect.objectContaining({ state: 'queued', title: 'Tu es hors ligne' }));
    expect(fetch).toHaveBeenCalledTimes(1);

    online();
    await sending;
    expect(fetch).toHaveBeenCalledTimes(2);
    const keys = fetch.mock.calls.map(([, init]) => init.headers['Idempotency-Key']);
    expect(new Set(keys).size).toBe(1);
    expect(second).toHaveBeenLastCalledWith(expect.objectContaining({ state: 'sent' }));
    expect(navigate).toHaveBeenCalledTimes(1);
    expect(navigate).toHaveBeenCalledWith('/sos?alerte=ID7');
  });

  it('SOS non rattaché au compte (sos_token) : pas de navigation, panneau « partie » avec appel et page SOS', async () => {
    const send = vi.fn().mockResolvedValue({ state: 'sent', data: { id: 'ID8', sos_token: 'tok' } });
    const navigate = vi.fn();
    const resolve = vi.fn();
    await xmSosFab({ client: { send, stop: vi.fn() }, navigate, number: '117', pageUrl: '/sos', texts: { sent: 'Ton alerte est partie.', detached: 'Pas reliée à ton compte.', call: 'Appeler le :number', openPage: 'Ouvrir la page SOS' } }).onSend({ resolve });
    expect(navigate).not.toHaveBeenCalled();
    expect(resolve).toHaveBeenCalledWith({ state: 'sent', title: 'Ton alerte est partie.', text: 'Pas reliée à ton compte.', call: { href: 'tel:117', label: 'Appeler le 117' }, link: { href: '/sos', label: 'Ouvrir la page SOS' } });
    expect(JSON.stringify(resolve.mock.calls)).not.toContain('tok');
  });
});

describe('xmSosPage (revue)', () => {
  const page = (opts = {}) => {
    const p = xmSosPage({ storage: memoryStorage(), texts: { positionNo: 'Envoyée sans position GPS.', positionJoined: 'Position jointe.', positionYes: 'Position jointe (± :meters m).', confirmShared: 'Avec ta position.', confirmUnshared: 'Sans ta position.' }, ...opts });
    p.$nextTick = (fn) => fn();
    p.$root = document.createElement('div');
    p.$watch = vi.fn();
    p.init();
    return p;
  };

  it('dit si la position est jointe d’après l’API, aussi après le bouton flottant ou un 422 qui l’a retirée', async () => {
    expect(page({ member: true, initial: { data: { id: 'A', located: false }, meta: {} } }).positionText()).toBe('Envoyée sans position GPS.');
    expect(page({ member: true, initial: { data: { id: 'A', located: true }, meta: {} } }).positionText()).toBe('Position jointe.');
    expect(page({ member: true, initial: { data: { id: 'A' }, meta: {} } }).positionText()).toBe('');

    const send = vi.fn().mockResolvedValue({ state: 'sent', data: { id: 'B', located: false }, meta: {} });
    const geolocation = { getCurrentPosition: (ok) => ok({ coords: { latitude: 6.36, longitude: 2.41, accuracy: 15 } }) };
    const p = page({ client: { send, action: vi.fn(), stop: vi.fn() }, geolocation });
    await p.onSend({});
    expect(p.positionText()).toBe('Envoyée sans position GPS.');
    p.alert.located = true;
    expect(p.positionText()).toBe('Position jointe (± 15 m).');
    p.destroy();
  });

  it('confirmation selon la case « Partager ma position »', () => {
    const p = page();
    expect(p.sosConfirmText()).toBe('Avec ta position.');
    p.share = false;
    expect(p.sosConfirmText()).toBe('Sans ta position.');
  });

  it('suit le sondage de x-alerts.notified : contact principal, statut et annulation à jour, jeton jamais repris', () => {
    const p = page({ member: true, initial: { data: { id: 'C', cancelled: false, safe: false, status: 'new', primary_contact: null }, meta: { poll_interval_seconds: 10 } } });
    const { onUpdate } = p.notifiedOptions();
    onUpdate({ id: 'AUTRE', status: 'closed' });
    expect(p.alert.status).toBe('new');
    onUpdate({ id: 'C', status: 'acknowledged', primary_contact: { label: 'Chantal H.', phone: '+229 01' }, sos_token: 'x' });
    expect(p.primary()).toMatchObject({ label: 'Chantal H.' });
    expect(p.alert.status).toBe('acknowledged');
    expect(p.alert.sos_token).toBeUndefined();
    onUpdate({ id: 'C', cancelled: true });
    expect(p.phase).toBe('cancelled');
    p.destroy();
  });
});
