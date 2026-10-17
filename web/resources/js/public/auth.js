// Écrans d'authentification Blade (lot ecrans-web, P4-09) : jauge de mot de passe (password-meter.js), cases du code
// OTP (collage réparti, retour arrière, compte à rebours du renvoi), comptes de démonstration (environnement local).
// Aucun texte ici : les libellés viennent de Blade (lang/fr/auth.php) via des attributs data-*.
import './password-meter.js';

const ready = (fn) => (document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', fn) : fn());

/* ---------- Code OTP : 6 cases, saisie fluide (LOGIN §5, WCAG 3.3.8 : collage autorisé) ---------- */
export function initOtp(root = document) {
  root.querySelectorAll('[data-xm-otp]').forEach((group) => {
    const digits = Array.from(group.querySelectorAll('.xm-otp__digit'));
    if (digits.length === 0) return;
    const form = group.closest('form');

    const fill = (start, text) => {
      const chars = String(text).replace(/\D/g, '').split('');
      if (chars.length === 0) return;
      let index = start;
      chars.forEach((ch) => {
        if (index >= digits.length) return;
        digits[index].value = ch;
        index += 1;
      });
      const complete = digits.every((d) => d.value.length === 1);
      (digits[Math.min(index, digits.length - 1)] || digits[digits.length - 1]).focus();
      if (complete && form) form.requestSubmit();
    };

    digits.forEach((input, i) => {
      input.addEventListener('input', () => {
        const value = input.value.replace(/\D/g, '');
        if (value.length > 1) {
          input.value = '';
          fill(i, value);
          return;
        }
        input.value = value;
        if (value && i < digits.length - 1) digits[i + 1].focus();
        if (value && i === digits.length - 1 && digits.every((d) => d.value.length === 1) && form) form.requestSubmit();
      });
      input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && input.value === '' && i > 0) {
          digits[i - 1].value = '';
          digits[i - 1].focus();
          e.preventDefault();
        } else if (e.key === 'ArrowLeft' && i > 0) {
          digits[i - 1].focus();
          e.preventDefault();
        } else if (e.key === 'ArrowRight' && i < digits.length - 1) {
          digits[i + 1].focus();
          e.preventDefault();
        }
      });
      input.addEventListener('paste', (e) => {
        const text = (e.clipboardData || window.clipboardData)?.getData('text') || '';
        if (!text) return;
        e.preventDefault();
        fill(0, text);
      });
      input.addEventListener('focus', () => input.select());
    });
  });
}

/* ---------- Renvoi du code : bouton désactivé pendant le délai, compte à rebours annoncé ---------- */
export function initResend(root = document) {
  root.querySelectorAll('[data-xm-otp-resend] [data-resend-in]').forEach((button) => {
    let left = parseInt(button.dataset.resendIn || '0', 10);
    const label = button.querySelector('[data-resend-label]');
    const live = button.parentElement.querySelector('[data-resend-live]');
    if (!(left > 0) || !label) return;
    const waiting = button.dataset.labelWait || ':seconds';
    const tick = () => {
      if (left <= 0) {
        button.disabled = false;
        label.textContent = button.dataset.label || '';
        if (live) live.textContent = button.dataset.label || '';
        return;
      }
      button.disabled = true;
      label.textContent = waiting.replace(':seconds', String(left));
      left -= 1;
      window.setTimeout(tick, 1000);
    };
    tick();
  });
}

/* ---------- Comptes de démonstration (bloc rendu seulement en environnement local) ---------- */
export function initDemoAccounts(root = document) {
  root.querySelectorAll('[data-xm-demo-accounts] [data-demo]').forEach((button) => {
    button.addEventListener('click', () => {
      const [identifier, password] = (button.dataset.demo || '').split('|');
      const form = document.querySelector('[data-xm-auth-form]');
      if (!form) return;
      const id = form.querySelector('[name="identifier"]');
      const pw = form.querySelector('[name="password"]');
      if (id) id.value = identifier || '';
      if (pw) {
        pw.value = password || '';
        pw.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  });
}

ready(() => {
  initOtp();
  initResend();
  initDemoAccounts();
});

window.XM = window.XM || {};
window.XM.auth = { initOtp, initResend, initDemoAccounts };
