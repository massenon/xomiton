// @vitest-environment jsdom
// Jauge de solidité (lot auth-ecrans, P4-09 ; LOGIN §4, Q30 a) : mêmes niveaux que App\Domain\Auth\PasswordStrength,
// texte + couleur (jamais la couleur seule), règles cochées en direct, niveau neutre « empty » tant que le champ est vide.
import { describe, expect, it } from 'vitest';
import { checks, init, render, score } from './password-meter.js';

const LABELS = { empty: 'Saisis un mot de passe', too_short: 'Trop court', weak: 'Faible', medium: 'Moyen', strong: 'Fort', excellent: 'Excellent' };

const mount = () => {
  document.body.innerHTML = `
    <input id="password" type="password" data-password-meter="#pwMeter">
    <div id="pwMeter" class="xm-pw-meter" data-level="empty" data-labels='${JSON.stringify(LABELS)}'>
      <div class="xm-pw-meter__segments">${'<span class="xm-pw-meter__segment"></span>'.repeat(4)}</div>
      <p class="xm-pw-meter__label" aria-live="polite"><span class="visually-hidden">Solidité :</span><span data-password-label></span></p>
      <ul>${['length', 'uppercase', 'lowercase', 'digit', 'symbol'].map((r) => `<li data-rule="${r}"><i data-rule-icon></i>${r}</li>`).join('')}</ul>
    </div>`;
  return { input: document.getElementById('password'), meter: document.getElementById('pwMeter') };
};

const lit = (meter) => Array.from(meter.querySelectorAll('.xm-pw-meter__segment.is-on'));

describe('password-meter', () => {
  it('suit les niveaux du serveur : 10 caractères, puis majuscule + minuscule + chiffre, symbole pour « Excellent »', () => {
    expect(score('Abc1')).toBe('too_short');
    expect(score('abcdefghij')).toBe('weak');
    expect(score('abcdefghi1')).toBe('medium');
    expect(score('Xomiton2026')).toBe('strong');
    expect(score('Xomiton2026!')).toBe('excellent');
    expect(checks('Élodie2026')).toEqual({ length: true, uppercase: true, lowercase: true, digit: true, symbol: false });
  });

  it('reste neutre tant que le champ est vide : niveau « empty », aucun segment, texte d’invitation', () => {
    const { meter } = mount();
    init();

    expect(meter.dataset.level).toBe('empty');
    expect(lit(meter)).toHaveLength(0);
    expect(meter.querySelector('[data-password-label]').textContent).toBe('Saisis un mot de passe');
    // Le préfixe pour lecteur d'écran n'est jamais écrasé.
    expect(meter.querySelector('.visually-hidden').textContent).toBe('Solidité :');
  });

  it('écrit le niveau et colore les segments à la saisie, coche les règles', () => {
    const { input, meter } = mount();
    init();

    input.value = 'abc';
    input.dispatchEvent(new Event('input'));
    expect(meter.dataset.level).toBe('too_short');
    expect(meter.querySelector('[data-password-label]').textContent).toBe('Trop court');

    input.value = 'Xomiton2026';
    input.dispatchEvent(new Event('input'));
    expect(meter.dataset.level).toBe('strong');
    expect(meter.querySelector('[data-password-label]').textContent).toBe('Fort');
    expect(lit(meter)).toHaveLength(3);
    expect(lit(meter).every((s) => s.classList.contains('is-success'))).toBe(true);
    expect(meter.querySelector('[data-rule="symbol"]').dataset.checked).toBe('false');
    expect(meter.querySelector('[data-rule="digit"]').classList.contains('is-ok')).toBe(true);

    expect(render(meter, 'Xomiton2026!')).toBe('excellent');
    expect(lit(meter)).toHaveLength(4);
    expect(meter.querySelector('[data-password-label]').textContent).toBe('Excellent');

    input.value = '';
    input.dispatchEvent(new Event('input'));
    expect(meter.dataset.level).toBe('empty');
    expect(lit(meter)).toHaveLength(0);
  });

  it('ne réécrit le texte de la région aria-live que lorsque le niveau change (pas d’annonce à chaque frappe)', async () => {
    const { input, meter } = mount();
    init();
    const label = meter.querySelector('[data-password-label]');
    const mutations = [];
    const observer = new MutationObserver((records) => mutations.push(...records));
    observer.observe(label, { childList: true, characterData: true, subtree: true });
    const type = (value) => {
      input.value = value;
      input.dispatchEvent(new Event('input'));
    };

    type('a');
    type('ab');
    type('abc');
    await Promise.resolve();
    expect(label.textContent).toBe('Trop court');
    expect(mutations).toHaveLength(1);

    type('Xomiton2026');
    type('Xomiton20261');
    await Promise.resolve();
    expect(label.textContent).toBe('Fort');
    expect(mutations).toHaveLength(2);

    // Les règles et les segments suivent toujours la saisie.
    type('Xomiton2026!');
    await Promise.resolve();
    expect(label.textContent).toBe('Excellent');
    expect(meter.querySelector('[data-rule="symbol"]').dataset.checked).toBe('true');
    observer.disconnect();
  });
});
