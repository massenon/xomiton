// @vitest-environment jsdom
// Plan de sécurité de /urgences (P4-06) : script en ligne autonome resources/views/public/urgences/plan-script.blade.php
// (JavaScript seul, inclus avec un nonce CSP) — lecture et écriture de localStorage `xm_plan_v1`, compteur, barre,
// annonce polie, réinitialisation, outils révélés, stockage indisponible ou corrompu, initialisation unique.
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// Vitest tourne depuis web/ (vite.config.js).
const source = readFileSync(resolve(process.cwd(), 'resources/views/public/urgences/plan-script.blade.php'), 'utf8');
const KEY = 'xm_plan_v1';

const mount = (items = 3) => {
  document.body.innerHTML = `
    <section data-xm-safety-plan data-key="${KEY}" data-progress=":done/:total" data-live="Étapes prêtes : :done sur :total." data-reset-done="Réinitialisé.">
      <div data-plan-tools hidden><span data-plan-count>0/${items}</span><button type="button" data-plan-print></button><button type="button" data-plan-reset></button></div>
      <div data-plan-progress hidden><div data-plan-bar></div></div>
      ${Array.from({ length: items }, (_, i) => `<input type="checkbox" data-plan-item="${i + 1}">`).join('')}
      <p data-plan-live></p>
    </section>`;
  // eslint-disable-next-line no-new-func
  new Function(source)();
  const root = document.querySelector('[data-xm-safety-plan]');
  return {
    root,
    boxes: [...root.querySelectorAll('[data-plan-item]')],
    count: root.querySelector('[data-plan-count]'),
    bar: root.querySelector('[data-plan-bar]'),
    live: root.querySelector('[data-plan-live]'),
  };
};
const tick = (box) => {
  box.checked = !box.checked;
  box.dispatchEvent(new Event('change'));
};

describe('plan de sécurité /urgences', () => {
  beforeEach(() => localStorage.clear());
  afterEach(() => vi.restoreAllMocks());

  it('reste du JavaScript pur, sans syntaxe Blade ni appel réseau', () => {
    expect(source).not.toMatch(/\{\{|\{!!|@[a-z]/);
    expect(source).not.toMatch(/fetch\(|XMLHttpRequest|sendBeacon/);
  });

  it('révèle les outils et restaure les cases mémorisées sur l’appareil', () => {
    localStorage.setItem(KEY, JSON.stringify({ 2: true, 3: false }));
    const plan = mount();

    expect(plan.root.querySelector('[data-plan-tools]').hidden).toBe(false);
    expect(plan.root.querySelector('[data-plan-progress]').hidden).toBe(false);
    expect(plan.boxes.map((box) => box.checked)).toEqual([false, true, false]);
    expect(plan.count.textContent).toBe('1/3');
    expect(plan.bar.style.width).toBe('33%');
    expect(plan.live.textContent).toBe('');
  });

  it('mémorise chaque case cochée, met à jour le compteur et l’annonce', () => {
    const plan = mount();
    tick(plan.boxes[0]);
    tick(plan.boxes[2]);

    expect(JSON.parse(localStorage.getItem(KEY))).toEqual({ 1: true, 3: true });
    expect(plan.count.textContent).toBe('2/3');
    expect(plan.bar.style.width).toBe('67%');
    expect(plan.live.textContent).toBe('Étapes prêtes : 2 sur 3.');
  });

  it('réinitialise les cases et le stockage', () => {
    localStorage.setItem(KEY, JSON.stringify({ 1: true }));
    const plan = mount();
    plan.root.querySelector('[data-plan-reset]').click();

    expect(localStorage.getItem(KEY)).toBeNull();
    expect(plan.boxes.every((box) => !box.checked)).toBe(true);
    expect(plan.count.textContent).toBe('0/3');
    expect(plan.live.textContent).toBe('Réinitialisé.');
  });

  it('imprime à la demande', () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => {});
    mount().root.querySelector('[data-plan-print]').click();

    expect(print).toHaveBeenCalledOnce();
  });

  it('ignore un stockage corrompu ou indisponible sans casser les cases', () => {
    localStorage.setItem(KEY, '[1,2');
    let plan = mount();
    expect(plan.boxes.every((box) => !box.checked)).toBe(true);

    vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('SecurityError'); });
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('QuotaExceededError'); });
    plan = mount();
    tick(plan.boxes[1]);

    expect(plan.boxes[1].checked).toBe(true);
    expect(plan.count.textContent).toBe('1/3');
  });

  it('ne s’initialise qu’une fois par plan', () => {
    const plan = mount();
    // eslint-disable-next-line no-new-func
    new Function(source)();
    tick(plan.boxes[0]);

    expect(plan.live.textContent).toBe('Étapes prêtes : 1 sur 3.');
    expect(JSON.parse(localStorage.getItem(KEY))).toEqual({ 1: true });
  });
});
