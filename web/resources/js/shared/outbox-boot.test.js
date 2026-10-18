// @vitest-environment jsdom
// Amorce de la boîte d'envoi (P5-10) : chargement à la demande (page [data-outbox] ou envoi en attente), façade
// window.XM.outbox (remise de sos.js), évènement `xm:outbox` sans doublon, `xm:outbox-submit` de signaler.js (point
// d'API + Idempotency-Key du formulaire, sans `_token`), envoi natif détourné sur une copie en cache (même après le
// retour du réseau), clé neuve à chaque affichage d'une copie, compte et durée de vie transmis, bandeau hors ligne.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { bootOutbox as boot, formEntry } from './outbox-boot.js';
import { formBody } from './outbox.js';

const flush = () => new Promise((resolve) => { setTimeout(resolve, 0); });

function fakeImporter() {
  const added = [];
  const box = { add: vi.fn(async (entry) => { added.push(entry); return entry; }), start: vi.fn(), flush: vi.fn(async () => ({})), count: vi.fn(async () => 0) };
  const notices = { mountOutboxNotices: vi.fn() };
  const getOutbox = vi.fn(() => box);
  const importer = vi.fn(async () => [{ getOutbox, formBody }, notices]);
  return { importer, box, notices, added, getOutbox };
}

const FORM = `<main id="contenu"><div x-data="xmReport({})"><form data-outbox="signalement" data-outbox-endpoint="/api/v1/signalements" method="POST" action="/signaler">
  <input type="hidden" name="_token" value="csrf"><input type="hidden" name="idempotency_key" value="11111111-1111-4111-8111-111111111111">
  <input name="description" value="Des faits suffisamment détaillés."><button>Envoyer</button></form></div></main>`;

let online;
const booted = [];
const bootOutbox = (opts) => { const b = boot(opts); booted.push(b); return b; };
beforeEach(() => {
  online = true;
  vi.spyOn(window.navigator, 'onLine', 'get').mockImplementation(() => online);
  document.documentElement.removeAttribute('data-xm-offline-copy');
  document.head.innerHTML = '<meta name="xm-offline" content="/sw.js" data-texts="{&quot;queued&quot;:&quot;En file&quot;}">';
  document.body.className = '';
  document.body.innerHTML = '';
  delete window.XM;
});

afterEach(() => {
  booted.splice(0).forEach((b) => b.destroy());
  vi.restoreAllMocks();
});

describe('formEntry', () => {
  it('reprend le point d’API, le type et la clé du formulaire', () => {
    document.body.innerHTML = FORM;
    const entry = formEntry(document.querySelector('form'));
    expect(entry).toMatchObject({
      type: 'signalement', url: '/api/v1/signalements', method: 'POST',
      id: '11111111-1111-4111-8111-111111111111', idempotencyKey: '11111111-1111-4111-8111-111111111111',
    });
    expect(entry.formData.get('description')).toBe('Des faits suffisamment détaillés.');
  });
});

describe('bootOutbox', () => {
  it('ne charge rien sur une page sans formulaire ni envoi en attente, puis charge au premier envoi', async () => {
    const { importer, box, notices } = fakeImporter();
    bootOutbox({ import: importer, storage: { getItem: () => null } });
    await flush();
    expect(importer).not.toHaveBeenCalled();

    window.XM.outbox.add({ uuid: 'k', type: 'sos', payload: { a: 1 } });
    window.dispatchEvent(new CustomEvent('xm:outbox', { detail: { uuid: 'k', type: 'sos', payload: { a: 1 } } }));
    await flush();
    expect(importer).toHaveBeenCalledTimes(1);
    expect(notices.mountOutboxNotices).toHaveBeenCalledWith({ queued: 'En file' });
    expect(box.start).toHaveBeenCalledTimes(1);
    expect(box.add).toHaveBeenCalledTimes(1); // l'évènement de la même alerte n'ajoute pas une 2e entrée
  });

  it('charge tout de suite sur une page [data-outbox] ou quand un envoi attend', async () => {
    document.body.innerHTML = '<div data-outbox="sos"></div>';
    const first = fakeImporter();
    bootOutbox({ import: first.importer, storage: { getItem: () => null } });
    await flush();
    expect(first.importer).toHaveBeenCalledTimes(1);

    document.body.innerHTML = '';
    const second = fakeImporter();
    bootOutbox({ import: second.importer, storage: { getItem: () => '2' } });
    await flush();
    expect(second.importer).toHaveBeenCalledTimes(1);
  });

  it('met en file le formulaire annoncé par signaler.js hors ligne, sans `_token`', async () => {
    document.body.innerHTML = FORM;
    const { importer, added } = fakeImporter();
    bootOutbox({ import: importer, storage: null });
    const form = document.querySelector('form');
    const event = new CustomEvent('xm:outbox-submit', { bubbles: true, cancelable: true, detail: { form, type: 'signalement' } });
    form.dispatchEvent(event);
    await flush();
    expect(event.defaultPrevented).toBe(true);
    expect(added).toHaveLength(1);
    expect(added[0]).toMatchObject({ type: 'signalement', url: '/api/v1/signalements', idempotencyKey: '11111111-1111-4111-8111-111111111111' });
    expect(added[0].body.form.map(([name]) => name)).toEqual(['idempotency_key', 'description']);
  });

  it('détourne vers la boîte d’envoi l’envoi natif d’une copie en cache (jeton CSRF périmé)', async () => {
    document.documentElement.setAttribute('data-xm-offline-copy', '');
    document.body.innerHTML = FORM;
    const state = { sending: true, offline: false };
    window.Alpine = { $data: () => state };
    const { importer, added } = fakeImporter();
    bootOutbox({ import: importer, storage: null });
    expect(document.body.classList.contains('xm-is-offline')).toBe(true);
    const event = new Event('submit', { bubbles: true, cancelable: true });
    document.querySelector('form').dispatchEvent(event);
    await flush();
    expect(event.defaultPrevented).toBe(true);
    expect(added).toHaveLength(1);
    expect(state).toEqual({ sending: false, offline: true });
    delete window.Alpine;
  });

  it('laisse partir normalement l’envoi d’une page fraîche en ligne, ou déjà retenu par son module', async () => {
    document.body.innerHTML = FORM;
    const { importer, added } = fakeImporter();
    bootOutbox({ import: importer, storage: null });
    const form = document.querySelector('form');
    const fresh = new Event('submit', { bubbles: true, cancelable: true });
    form.dispatchEvent(fresh);
    online = false;
    const kept = new Event('submit', { bubbles: true, cancelable: true });
    kept.preventDefault();
    form.dispatchEvent(kept);
    await flush();
    expect(fresh.defaultPrevented).toBe(false);
    expect(added).toHaveLength(0);
  });

  it('affiche le bandeau #xmOfflineBar hors connexion et le retire au retour du réseau', () => {
    bootOutbox({ import: fakeImporter().importer, storage: null });
    expect(document.body.classList.contains('xm-is-offline')).toBe(false);
    online = false;
    window.dispatchEvent(new Event('offline'));
    expect(document.body.classList.contains('xm-is-offline')).toBe(true);
    online = true;
    window.dispatchEvent(new Event('online'));
    expect(document.body.classList.contains('xm-is-offline')).toBe(false);
  });

  it('copie en cache : l’envoi reste détourné après le retour du réseau (jamais de 419), le bandeau suit le réseau', async () => {
    document.documentElement.setAttribute('data-xm-offline-copy', '');
    document.body.innerHTML = FORM;
    const { importer, added } = fakeImporter();
    bootOutbox({ import: importer, storage: null });
    window.dispatchEvent(new Event('online'));
    expect(document.body.classList.contains('xm-is-offline')).toBe(false);
    const event = new Event('submit', { bubbles: true, cancelable: true });
    document.querySelector('form').dispatchEvent(event);
    await flush();
    expect(event.defaultPrevented).toBe(true);
    expect(added).toHaveLength(1);
  });

  it('copie en cache : clé neuve à chaque affichage (deux signalements = deux entrées), gardée pour le même affichage', async () => {
    document.documentElement.setAttribute('data-xm-offline-copy', '');
    const keys = [];
    for (let view = 0; view < 2; view += 1) {
      document.body.innerHTML = FORM;
      const { importer, added } = fakeImporter();
      const b = bootOutbox({ import: importer, storage: null });
      const form = document.querySelector('form');
      const send = () => form.dispatchEvent(new CustomEvent('xm:outbox-submit', { bubbles: true, cancelable: true, detail: { form, type: 'signalement' } }));
      send();
      send();
      await flush();
      expect(added).toHaveLength(2);
      expect(added[1].idempotencyKey).toBe(added[0].idempotencyKey);
      keys.push(added[0].idempotencyKey);
      b.destroy();
    }
    expect(keys[0]).toMatch(/^[0-9a-f-]{36}$/);
    expect(keys[0]).not.toBe('11111111-1111-4111-8111-111111111111');
    expect(keys[1]).not.toBe(keys[0]);
  });

  it('transmet le compte de la page (empreinte, vide sans compte) et la durée de vie à la boîte d’envoi', async () => {
    document.head.innerHTML = '<meta name="xm-offline" content="/sw.js" data-account="abc123" data-ttl-days="3" data-texts="{}">';
    document.body.innerHTML = FORM;
    const { importer, added, getOutbox } = fakeImporter();
    bootOutbox({ import: importer, storage: null });
    window.XM.outbox.add({ uuid: 'k', type: 'sos', payload: {} });
    window.XM.outbox.add({ uuid: 'm', type: 'sos', payload: {}, account: '' });
    await flush();
    expect(added.map((entry) => entry.account)).toEqual(['abc123', '']);
    const opts = getOutbox.mock.calls[0][0];
    expect(opts.ttlDays).toBe(3);
    expect(opts.account()).toBe('abc123');

    document.head.innerHTML = '<meta name="xm-offline" content="" data-texts="{}">';
    const other = fakeImporter();
    bootOutbox({ import: other.importer, storage: null });
    window.XM.outbox.add({ uuid: 'n', type: 'sos', payload: {} });
    await flush();
    expect(other.added[0]).not.toHaveProperty('account');
    expect(other.getOutbox.mock.calls[0][0]).not.toHaveProperty('account');
  });
});
