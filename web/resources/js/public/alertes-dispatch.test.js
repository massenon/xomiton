// @vitest-environment jsdom
// Composant Alpine xmNotified (P5-03) testé sans Alpine : la fabrique renvoie un objet simple, fetch est simulé.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { backoff, formatTime, initials, isFinal, newlySeen, nextInterval, xmNotified } from './alertes-dispatch.js';

const person = (key, state, extra = {}) => ({ key, label: 'Mireille TOGNIFODE', role: 'Point focal · Ouémé', state, state_label: state, phone: null, ...extra });

const reply = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });

describe('fonctions pures', () => {
  it('calcule les initiales des deux premiers mots', () => {
    expect(initials('Chantal Hounkpatin')).toBe('CH');
    expect(initials('Équipe Xomiton Bénin')).toBe('ÉX');
    expect(initials('')).toBe('');
  });

  it('double l’intervalle sur 429, sans descendre sous 10 s ni dépasser 60 s', () => {
    expect(nextInterval(10, 429)).toBe(20);
    expect(nextInterval(40, 429)).toBe(60);
    expect(nextInterval(10, 200)).toBe(10);
    expect(backoff(0)).toBe(10);
    expect(backoff(60)).toBe(60);
  });

  it('formate l’heure selon le gabarit fourni par Blade', () => {
    const d = new Date(2026, 9, 18, 9, 5);
    expect(formatTime(d, '{H}\u00A0h\u00A0{mm}')).toBe('9\u00A0h\u00A005');
    expect(formatTime(d, '{HH}:{mm}')).toBe('09:05');
  });

  it('reconnaît un état final et les personnes qui viennent de voir l’alerte', () => {
    expect(isFinal({ acknowledged: true, safe: true })).toBe(true);
    expect(isFinal({ acknowledged: true, safe: false })).toBe(false);
    expect(isFinal({ cancelled: true })).toBe(true);
    expect(newlySeen([person('contact:4', 'sent')], [person('contact:4', 'seen')]).map((p) => p.key)).toEqual(['contact:4']);
    expect(newlySeen([person('contact:4', 'seen')], [person('contact:4', 'seen')])).toEqual([]);
  });
});

describe('xmNotified', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
  });

  const make = (opts = {}) => {
    const c = xmNotified({ people: [person('contact:4', 'sent')], endpoint: '/api/v1/sos/01J', poll: 10, texts: { acknowledged: ':name a bien reçu ton alerte et va t’appeler.', updated: 'Mis à jour à :time', time: '{H}\u00A0h\u00A0{mm}' }, ...opts });
    c.init();
    return c;
  };

  it('met à jour la liste, annonce la personne qui a vu l’alerte et s’arrête sur un état final', async () => {
    globalThis.fetch = vi.fn(async () => reply(200, { data: { notified: [person('contact:4', 'seen')], acknowledged: true, safe: true } }));
    const c = make();

    await c.tick();

    expect(c.people[0].state).toBe('seen');
    expect(c.announcement).toBe('Mireille TOGNIFODE a bien reçu ton alerte et va t’appeler.');
    expect(c.stopped).toBe(true);
    expect(c.updatedLabel()).toMatch(/^Mis à jour à \d{1,2} h \d{2}$/);
  });

  it('s’arrête sur 404 et recule sur 429', async () => {
    globalThis.fetch = vi.fn(async () => reply(429, {}));
    const c = make();
    await c.tick();
    expect(c.interval).toBe(20);
    expect(c.stopped).toBe(false);

    globalThis.fetch = vi.fn(async () => reply(404, {}));
    await c.tick();
    expect(c.stopped).toBe(true);
  });

  it('passe hors ligne sur une erreur réseau, réessaie avec recul et reprend au premier succès', async () => {
    globalThis.fetch = vi.fn(async () => { throw new TypeError('Failed to fetch'); });
    const c = make();
    await c.tick();
    expect(c.offline).toBe(true);
    expect(c.retryDelay).toBe(20);
    expect(c._timer).not.toBeNull();

    await c.tick();
    expect(globalThis.fetch).toHaveBeenCalledTimes(2);
    expect(c.retryDelay).toBe(40);

    globalThis.fetch = vi.fn(async () => reply(200, { data: { notified: [person('contact:4', 'seen')] } }));
    await vi.advanceTimersByTimeAsync(40_000);
    expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    expect(c.offline).toBe(false);
    expect(c.retryDelay).toBe(0);
    expect(c.people[0].state).toBe('seen');
    expect(c.stopped).toBe(false);
    expect(c._timer).not.toBeNull();
  });

  it('ne sonde pas quand le navigateur est hors ligne, ni sans point d’accès', async () => {
    vi.spyOn(navigator, 'onLine', 'get').mockReturnValue(false);
    globalThis.fetch = vi.fn();
    const c = make();
    await c.tick();
    expect(globalThis.fetch).not.toHaveBeenCalled();
    expect(c.offline).toBe(true);
    expect(c._timer).toBeNull();

    const silent = xmNotified({ people: [], endpoint: null });
    silent.init();
    await silent.tick();
    expect(globalThis.fetch).not.toHaveBeenCalled();
  });

  it('envoie le jeton X-Sos-Token sans l’exposer dans l’état, et s’arrête sur 403 ou 401', async () => {
    globalThis.fetch = vi.fn(async () => reply(403, {}));
    const c = make({ token: 'jeton-secret' });
    expect(JSON.stringify(c)).not.toContain('jeton-secret');

    await c.tick();
    expect(globalThis.fetch.mock.calls[0][1].headers['X-Sos-Token']).toBe('jeton-secret');
    expect(c.stopped).toBe(true);
    expect(c._timer).toBeNull();

    globalThis.fetch = vi.fn(async () => reply(401, {}));
    const anonymous = make();
    await anonymous.tick();
    expect(globalThis.fetch.mock.calls[0][1].headers).not.toHaveProperty('X-Sos-Token');
    expect(anonymous.stopped).toBe(true);
  });

  it('cesse de sonder au-delà de pollUntil et expose le contact principal', async () => {
    globalThis.fetch = vi.fn();
    const c = make({ pollUntil: new Date(Date.now() - 1000).toISOString(), people: [person('team', 'sent'), person('trusted:3', 'sms_sent', { phone: '+229 01 97 38 88 47' })] });
    await c.tick();
    expect(globalThis.fetch).not.toHaveBeenCalled();
    expect(c.stopped).toBe(true);
    expect(c.primary().key).toBe('trusted:3');
    expect(c.telHref('+229 01 97 38 88 47')).toBe('+2290197388847');
  });
});
