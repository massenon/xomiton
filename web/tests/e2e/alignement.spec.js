// Alignement horizontal : le contenu de l'en-tête, du corps (main) et du pied de page partage les mêmes bords gauche et droit
// (jetons --xm-container-max et --xm-gutter, D-ALIGN-1), à toutes les largeurs. Tolérance : 1 px.
import { expect, test } from '@playwright/test';

const PAGES = ['/', '/thematiques', '/ressources', '/aide', '/politique-de-confidentialite'];
const WIDTHS = [1440, 1280, 1024, 768, 390];

const edges = () => {
  const box = (el) => {
    const r = el.getBoundingClientRect(), cs = getComputedStyle(el);
    return [r.left + parseFloat(cs.paddingLeft), r.right - parseFloat(cs.paddingRight)];
  };
  const pick = (root) => { const c = root?.querySelector('.container'); return c ? box(c) : null; };
  const main = document.querySelector('main');
  const first = [...main.querySelectorAll('.container')].find((c) => c.offsetParent && c.getBoundingClientRect().height > 0);
  return { header: pick(document.querySelector('header')), main: first ? box(first) : null, footer: pick(document.querySelector('footer')), overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth };
};

for (const uri of PAGES) {
  for (const width of WIDTHS) {
    test(`alignement en-tête / corps / pied · ${uri} · ${width} px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(uri, { waitUntil: 'networkidle' });
      const e = await page.evaluate(edges);
      for (const part of ['main', 'footer']) {
        expect(e[part], `${part} présent`).not.toBeNull();
        expect(Math.abs(e[part][0] - e.header[0]), `${part} bord gauche`).toBeLessThanOrEqual(1);
        expect(Math.abs(e[part][1] - e.header[1]), `${part} bord droit`).toBeLessThanOrEqual(1);
      }
      expect(e.overflow, 'pas de défilement horizontal').toBeLessThanOrEqual(0);
    });
  }
}
