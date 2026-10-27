// P16-01 : axe-core (@axe-core/playwright) sur les pages publiques principales et trois pages membre, thèmes clair et
// sombre, largeurs 360 et 1280 px. Échec à toute violation « serious » ou « critical » non listée dans KNOWN
// (violations connues, à traiter : voir la grille d'accessibilité de la PR). Rapport complet (une ligne JSON par violation) : A11Y_REPORT=chemin.jsonl.
import { appendFileSync } from 'node:fs';
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const PUBLIC_PAGES = ['/', '/thematiques', '/ressources', '/violences', '/lois', '/aide', '/urgences', '/signaler', '/sos', '/connexion', '/inscription', '/confidentialite'];
const MEMBER_PAGES = ['/fil', '/groupes', '/parametres'];
const SIZES = [{ width: 360, height: 800 }, { width: 1280, height: 900 }];
const THEMES = ['light', 'dark'];

/** Violations connues « règle@page » tolérées en attendant leur correction (vide = tout est bloquant). */
const KNOWN = new Set([]);

async function scan(page, uri, theme, size) {
  await page.setViewportSize(size);
  await page.addInitScript((value) => localStorage.setItem('theme', value), theme);
  await page.goto(uri, { waitUntil: 'networkidle' });
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
  const blocking = violations.filter((v) => ['serious', 'critical'].includes(v.impact));
  for (const v of violations) {
    if (process.env.A11Y_REPORT) appendFileSync(process.env.A11Y_REPORT, `${JSON.stringify({ uri, theme, width: size.width, rule: v.id, impact: v.impact, nodes: v.nodes.length, target: v.nodes.slice(0, 3).map((n) => n.target.join(' ')), help: v.help, data: v.nodes.slice(0, 3).map((n) => n.any[0]?.data && { fg: n.any[0].data.fgColor, bg: n.any[0].data.bgColor, ratio: n.any[0].data.contrastRatio, size: n.any[0].data.fontSize }) })}\n`);
  }
  return blocking.filter((v) => !KNOWN.has(`${v.id}@${uri}`)).map((v) => `${v.id} (${v.impact}, ${v.nodes.length} nœud(s)) : ${v.nodes[0].target.join(' ')}`);
}

for (const theme of THEMES) {
  for (const size of SIZES) {
    test(`axe · pages publiques · ${theme} · ${size.width} px`, async ({ page }) => {
      const failures = [];
      for (const uri of PUBLIC_PAGES) {
        for (const message of await scan(page, uri, theme, size)) failures.push(`${uri} → ${message}`);
      }
      expect(failures).toEqual([]);
    });

    test(`axe · pages membre · ${theme} · ${size.width} px`, async ({ page }) => {
      await page.goto('/inscription');
      await page.locator('#f-pseudo').fill(`axe${Date.now() % 1000000}`);
      await page.locator('#f-email').fill(`axe${Date.now()}@exemple.bj`);
      await page.locator('#f-password').fill('Mot-de-passe-solide-2026!');
      await page.locator('#f-cgu').check();
      await page.locator('#f-privacy').check();
      await page.getByRole('button', { name: 'Créer mon compte' }).click();
      for (const step of ['profil', 'securite', 'consentements']) {
        await expect(page).toHaveURL(new RegExp(`/inscription/${step}`));
        await page.getByRole('button', { name: /^Passer/ }).first().click();
      }
      await expect(page).toHaveURL(/\/fil/);

      const failures = [];
      for (const uri of MEMBER_PAGES) {
        for (const message of await scan(page, uri, theme, size)) failures.push(`${uri} → ${message}`);
      }
      expect(failures).toEqual([]);
    });
  }
}
