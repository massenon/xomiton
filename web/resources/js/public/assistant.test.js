// @vitest-environment jsdom
// Composant Alpine xmAssistant (P4-01) testé sans Alpine : la fabrique renvoie un objet simple, fetch est simulé.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { xmAssistant } from './assistant.js';

const texts = {
  loading: 'Chargement…',
  error: 'Erreur.',
  offline: 'Hors connexion.',
  offlineLink: 'Contacts d’urgence',
  rateLimited: 'Réessaie dans :seconds secondes.',
  retry: 'Réessayer',
};

const make = () => {
  const c = xmAssistant({ url: '/api/v1/assistant/question', csrfUrl: '/sanctum/csrf-cookie', offlineUrl: '/urgences', welcome: 'Bonjour.', texts });
  c.init();
  return c;
};

const reply = (status, body, headers = {}) => ({
  ok: status >= 200 && status < 300,
  status,
  headers: { get: (name) => headers[name] ?? null },
  json: async () => body,
});

describe('xmAssistant', () => {
  beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=abc%3D';
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('accueille, ignore une question vide et remplace la bulle de chargement par la réponse', async () => {
    const fetch = vi.fn().mockResolvedValue(reply(200, { data: { text: 'Réponse', kind: 'crisis', emergency: true, suggestions: ['x'], links: [{ label: 'SOS', url: '/sos', variant: 'danger' }] } }));
    vi.stubGlobal('fetch', fetch);
    const c = make();

    expect(c.messages).toHaveLength(1);
    expect(c.messages[0]).toMatchObject({ who: 'bot', text: 'Bonjour.' });

    await c.ask('');
    await c.ask('   ');
    expect(fetch).not.toHaveBeenCalled();

    c.input = 'Comment porter plainte ?';
    await c.send();

    expect(fetch).toHaveBeenCalledTimes(1);
    const [url, init] = fetch.mock.calls[0];
    expect(url).toBe('/api/v1/assistant/question');
    expect(init.method).toBe('POST');
    expect(init.headers['X-XSRF-TOKEN']).toBe('abc=');
    expect(init.body).toBe(JSON.stringify({ question: 'Comment porter plainte ?' }));
    expect(c.input).toBe('');
    expect(c.busy).toBe(false);
    expect(c.messages).toHaveLength(3);
    expect(c.messages[1]).toMatchObject({ who: 'me', text: 'Comment porter plainte ?' });
    expect(c.messages[2]).toMatchObject({ who: 'bot', text: 'Réponse', links: [{ label: 'SOS', url: '/sos', variant: 'danger' }], suggestions: [] });
    expect(c.messages.some((m) => m.loading)).toBe(false);
  });

  it('ajoute la réponse avec une nouvelle id (annoncée par le journal) au lieu de réutiliser la bulle de chargement', async () => {
    let resolve;
    vi.stubGlobal('fetch', vi.fn().mockReturnValue(new Promise((r) => { resolve = r; })));
    const c = make();

    const pending = c.ask('consentement');
    const loading = c.messages.find((m) => m.loading);
    expect(loading).toBeDefined();

    resolve(reply(200, { data: { text: 'Réponse', kind: 'kb', suggestions: [], links: [] } }));
    await pending;

    const answer = c.messages.at(-1);
    expect(answer).toMatchObject({ who: 'bot', text: 'Réponse' });
    expect(answer.id).not.toBe(loading.id);
    expect(c.messages.some((m) => m.id === loading.id)).toBe(false);
  });

  it('redonne le focus au champ après une question tapée, mais le laisse sur la puce cliquée', async () => {
    vi.stubGlobal('fetch', vi.fn().mockImplementation(async () => reply(200, { data: { text: 'Réponse', kind: 'kb', suggestions: [], links: [] } })));
    const input = document.createElement('input');
    const chip = document.createElement('button');
    document.body.append(input, chip);
    const c = make();
    c.$refs = { input };

    c.input = 'Comment porter plainte ?';
    await c.send();
    expect(document.activeElement).toBe(input);

    input.blur();
    chip.focus();
    await c.ask('Contraception d’urgence', chip);
    expect(document.activeElement).toBe(chip);

    // Puce désactivée pendant la requête : le focus perdu lui revient, jamais au champ.
    chip.blur();
    await c.ask('Dépistage', chip);
    expect(document.activeElement).toBe(chip);
    expect(document.activeElement).not.toBe(input);

    input.remove();
    chip.remove();
  });

  it('propose les suggestions du repli', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(reply(200, { data: { text: 'Je n’ai pas trouvé.', kind: 'fallback', suggestions: ['A', 'B', 'C'], links: [] } })));
    const c = make();
    await c.ask('bonjour');

    expect(c.messages.at(-1).suggestions).toEqual(['A', 'B', 'C']);
  });

  it('annonce le délai sur 429 et le message de validation sur 422', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce(reply(429, { message: 'Trop.', retry_after: 12 }, { 'Retry-After': '30' }))
      .mockResolvedValueOnce(reply(422, { message: 'Invalide.', errors: { question: ['Le champ question est obligatoire.'] } }));
    vi.stubGlobal('fetch', fetch);
    const c = make();

    await c.ask('a?');
    expect(c.messages.at(-1)).toMatchObject({ who: 'bot', text: 'Réessaie dans 30 secondes.', error: true });

    await c.ask('b?');
    expect(c.messages.at(-1).text).toBe('Le champ question est obligatoire.');
    expect(c.busy).toBe(false);
  });

  it('sur 419, recharge le cookie CSRF puis rejoue une seule fois', async () => {
    const fetch = vi.fn()
      .mockResolvedValueOnce(reply(419, {}))
      .mockResolvedValueOnce(reply(204, {}))
      .mockResolvedValueOnce(reply(419, {}));
    vi.stubGlobal('fetch', fetch);
    const c = make();

    await c.ask('question');

    expect(fetch).toHaveBeenCalledTimes(3);
    expect(fetch.mock.calls[1][0]).toBe('/sanctum/csrf-cookie');
    expect(fetch.mock.calls[2][1].method).toBe('POST');
    expect(c.messages.at(-1)).toMatchObject({ text: 'Erreur.', retry: 'question' });
    expect(c.busy).toBe(false);
  });

  it('hors réseau, propose d’appeler le premier numéro configuré puis les contacts d’urgence', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    const c = xmAssistant({ offlineUrl: '/urgences', offlineCall: { label: 'Appeler le 117 (Police secours)', url: 'tel:117' }, texts });
    c.init();

    await c.ask('question');
    expect(c.messages.at(-1).links).toEqual([
      { label: 'Appeler le 117 (Police secours)', url: 'tel:117', variant: 'primary-soft' },
      { label: 'Contacts d’urgence', url: '/urgences', variant: 'primary-soft' },
    ]);
  });

  it('hors réseau, renvoie vers les contacts d’urgence ; « Réessayer » rejoue la question', async () => {
    const fetch = vi.fn()
      .mockRejectedValueOnce(new TypeError('Failed to fetch'))
      .mockResolvedValueOnce(reply(500, {}))
      .mockResolvedValueOnce(reply(200, { data: { text: 'Enfin.', kind: 'kb', suggestions: [], links: [] } }));
    vi.stubGlobal('fetch', fetch);
    const c = make();

    await c.ask('question');
    expect(c.messages.at(-1)).toMatchObject({ text: 'Hors connexion.', links: [{ label: 'Contacts d’urgence', url: '/urgences', variant: 'primary-soft' }] });
    expect(c.busy).toBe(false);

    await c.ask('encore');
    const failed = c.messages.at(-1);
    expect(failed.retry).toBe('encore');

    const count = c.messages.length;
    await c.retry(failed);
    expect(c.messages).toHaveLength(count);
    expect(c.messages.at(-1)).toMatchObject({ who: 'bot', text: 'Enfin.' });
    expect(c.messages.filter((m) => m.who === 'me')).toHaveLength(2);
  });
});
