// @vitest-environment jsdom
// Écran du code OTP (lot auth-ecrans, P4-09 ; LOGIN §5, WCAG 3.3.8) : 6 cases, collage du code complet réparti
// (séparateurs ignorés), saisie qui avance, retour arrière qui recule, envoi automatique une fois complet ; renvoi après délai.
import { afterEach, describe, expect, it, vi } from 'vitest';
import { initOtp, initResend } from './auth.js';

const mountOtp = () => {
  document.body.innerHTML = `
    <form data-xm-otp-form><fieldset data-xm-otp>
      ${Array.from({ length: 6 }, (_, i) => `<input class="xm-otp__digit" name="code[]" maxlength="${i === 0 ? 12 : 1}">`).join('')}
    </fieldset></form>`;
  const form = document.querySelector('form');
  form.requestSubmit = vi.fn();
  initOtp();
  return { form, digits: Array.from(document.querySelectorAll('.xm-otp__digit')) };
};

const paste = (input, text) => {
  const event = new Event('paste', { bubbles: true, cancelable: true });
  event.clipboardData = { getData: () => text };
  input.dispatchEvent(event);
  return event;
};

afterEach(() => vi.useRealTimers());

describe('auth — code OTP', () => {
  it('répartit un code collé dans les 6 cases, ignore espaces et texte, puis envoie', () => {
    const { form, digits } = mountOtp();

    const event = paste(digits[2], 'Ton code : 482 913');

    expect(event.defaultPrevented).toBe(true);
    expect(digits.map((d) => d.value).join('')).toBe('482913');
    expect(form.requestSubmit).toHaveBeenCalledTimes(1);
  });

  it('répartit aussi un code rempli d’un bloc par le navigateur (autocomplete="one-time-code")', () => {
    const { form, digits } = mountOtp();

    digits[0].value = '135790';
    digits[0].dispatchEvent(new Event('input'));

    expect(digits.map((d) => d.value)).toEqual(['1', '3', '5', '7', '9', '0']);
    expect(form.requestSubmit).toHaveBeenCalledTimes(1);
  });

  it('avance à la saisie, refuse une lettre et recule au retour arrière sur une case vide', () => {
    const { form, digits } = mountOtp();

    digits[0].value = '4';
    digits[0].dispatchEvent(new Event('input'));
    expect(document.activeElement).toBe(digits[1]);

    digits[1].value = 'a';
    digits[1].dispatchEvent(new Event('input'));
    expect(digits[1].value).toBe('');

    digits[1].dispatchEvent(new KeyboardEvent('keydown', { key: 'Backspace', cancelable: true }));
    expect(digits[0].value).toBe('');
    expect(document.activeElement).toBe(digits[0]);
    expect(form.requestSubmit).not.toHaveBeenCalled();
  });

  it('désactive « Renvoyer le code » pendant le délai et l’annonce une fois disponible', () => {
    vi.useFakeTimers();
    document.body.innerHTML = `
      <form data-xm-otp-resend>
        <button data-resend-in="2" data-label="Renvoyer le code" data-label-wait="Renvoyer le code dans :seconds s"><span data-resend-label></span></button>
        <span data-resend-live></span>
      </form>`;
    const button = document.querySelector('button');
    initResend();

    expect(button.disabled).toBe(true);
    expect(button.textContent).toBe('Renvoyer le code dans 2 s');

    vi.advanceTimersByTime(2000);
    expect(button.disabled).toBe(false);
    expect(button.textContent).toBe('Renvoyer le code');
    expect(document.querySelector('[data-resend-live]').textContent).toBe('Renvoyer le code');
  });
});
