// @vitest-environment jsdom
// Vote « Cet article vous a-t-il aidé·e ? » (P4-05) : envoi JSON avec le jeton du formulaire, remerciement à la place des
// boutons qui reçoit le focus (un seul canal d'annonce : pas de toast en succès) ; échec et session expirée annoncés
// sans masquer les boutons, focus rendu au bouton ; envoi classique sans `submitter`.
import { describe, expect, it, vi } from 'vitest';
import { initFeedback, sendFeedback } from './aide.js';

const form = () => {
  document.body.innerHTML = `
    <form method="post" action="https://xomiton.test/aide/abus-a-signaler/avis" data-xm-faq-feedback
          data-feedback-failed="Envoi impossible." data-feedback-expired="Session expirée.">
      <input type="hidden" name="_token" value="jeton">
      <div data-feedback-actions>
        <button type="submit" name="helpful" value="1">Oui</button>
        <button type="submit" name="helpful" value="0">Non</button>
      </div>
      <p tabindex="-1" data-feedback-done></p>
    </form>`;
  return document.querySelector('form');
};

const json = (status, payload) => ({ ok: status >= 200 && status < 300, status, json: async () => payload });

describe('aide : vote', () => {
  it('envoie le vote en JSON avec le jeton CSRF, puis remercie à la place des boutons', async () => {
    const el = form();
    const fetch = vi.fn().mockResolvedValue(json(200, { message: 'Merci pour votre retour !', helpful: true }));
    const toast = vi.fn();

    expect(await sendFeedback(el, true, { fetch, toast })).toBe('sent');

    const [url, options] = fetch.mock.calls[0];
    expect(url).toBe('https://xomiton.test/aide/abus-a-signaler/avis');
    expect(options.method).toBe('POST');
    expect(options.headers.Accept).toBe('application/json');
    expect(options.body.get('_token')).toBe('jeton');
    expect(options.body.get('helpful')).toBe('1');
    expect(el.querySelector('[data-feedback-actions]').hidden).toBe(true);
    expect(el.querySelector('[data-feedback-done]').textContent).toBe('Merci pour votre retour !');
    expect(document.activeElement).toBe(el.querySelector('[data-feedback-done]'));
    expect(toast).not.toHaveBeenCalled();
  });

  it('envoie « Non » et rend le remerciement focalisable même sans tabindex dans le gabarit', async () => {
    const el = form();
    el.querySelector('[data-feedback-done]').removeAttribute('tabindex');
    const fetch = vi.fn().mockResolvedValue(json(200, { message: 'Merci, nous allons améliorer cet article.' }));

    await sendFeedback(el, false, { fetch, toast: vi.fn() });

    expect(fetch.mock.calls[0][1].body.get('helpful')).toBe('0');
    expect(el.querySelector('[data-feedback-done]').getAttribute('tabindex')).toBe('-1');
    expect(document.activeElement.textContent).toBe('Merci, nous allons améliorer cet article.');
  });

  it('annonce l’échec et la session expirée sans masquer les boutons', async () => {
    const el = form();
    const toast = vi.fn();

    expect(await sendFeedback(el, true, { fetch: vi.fn().mockResolvedValue(json(419, {})), toast })).toBe('failed');
    expect(toast).toHaveBeenLastCalledWith('Session expirée.', 'warning');
    expect(await sendFeedback(el, true, { fetch: vi.fn().mockResolvedValue(json(429, {})), toast })).toBe('failed');
    expect(toast).toHaveBeenLastCalledWith('Envoi impossible.', 'warning');
    expect(await sendFeedback(el, true, { fetch: vi.fn().mockRejectedValue(new TypeError('réseau')), toast })).toBe('failed');
    expect(toast).toHaveBeenLastCalledWith('Envoi impossible.', 'warning');
    expect(el.querySelector('[data-feedback-actions]').hidden).toBe(false);
    expect(el.querySelector('[data-feedback-done]').textContent).toBe('');
  });

  it('intercepte la soumission par un bouton de vote et laisse partir un envoi sans bouton', async () => {
    const el = form();
    const fetch = vi.fn().mockResolvedValue(json(200, { message: 'Merci pour votre retour !' }));
    initFeedback(document, { fetch, toast: vi.fn() });
    initFeedback(document, { fetch, toast: vi.fn() });

    const voted = new Event('submit', { cancelable: true });
    Object.defineProperty(voted, 'submitter', { value: el.querySelector('button[value="0"]') });
    el.dispatchEvent(voted);
    await vi.waitFor(() => expect(fetch).toHaveBeenCalledTimes(1));
    expect(voted.defaultPrevented).toBe(true);
    expect(fetch.mock.calls[0][1].body.get('helpful')).toBe('0');

    const failing = vi.fn().mockResolvedValue(json(500, {}));
    form();
    const again = document.querySelector('form');
    initFeedback(document, { fetch: failing, toast: vi.fn() });
    const no = again.querySelector('button[value="0"]');
    const retry = new Event('submit', { cancelable: true });
    Object.defineProperty(retry, 'submitter', { value: no });
    again.dispatchEvent(retry);
    await vi.waitFor(() => expect(document.activeElement).toBe(no));
    expect(no.disabled).toBe(false);

    const plain = new Event('submit', { cancelable: true });
    el.dispatchEvent(plain);
    expect(plain.defaultPrevented).toBe(false);
    expect(fetch).toHaveBeenCalledTimes(1);
  });
});
