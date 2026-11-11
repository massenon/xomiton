// Mesure de la navigation accueil → page légale en Chromium (4G lente, CPU ×4), AVEC et SANS les Speculation Rules
// (variante « sans » : second mandataire `STRIP_SPECULATION=1 node scripts/perf/proxy.mjs 8097 8098`, option --off=URL). Affiche le délai entre le clic et le 1er rendu
// de la page d'arrivée, si la page était préchargée / préparée, et si une transition de vue a eu lieu.
// Usage : PW_CHROMIUM=/opt/pw-browsers/chromium node scripts/perf/navigation.mjs [baseUrl] [chemin]
import { chromium } from '@playwright/test';

const base = process.argv.slice(2).find((a) => a.startsWith('http')) || 'http://127.0.0.1:8099';
const target = process.argv.slice(2).find((a) => a.startsWith('/')) || '/cookies';
const baseOff = process.argv.slice(2).find((a) => a.startsWith('--off='))?.slice(6) || 'http://127.0.0.1:8097';
const browser = await chromium.launch({ executablePath: process.env.PW_CHROMIUM });

async function run(withRules, dwell, hold = 0) {
  const ctx = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  await page.addInitScript(() => addEventListener('pagereveal', (e) => { window.__vt = Boolean(e.viewTransition); }));
  const origin = withRules ? base : baseOff;
  await page.goto(origin + '/', { waitUntil: 'load' });
  const cdp = await ctx.newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
  const link = page.locator(`footer a[href$="${target}"]`).first();
  await link.scrollIntoViewIfNeeded();
  await link.hover();
  await page.waitForTimeout(dwell);
  const t0 = Date.now();
  const nav = page.waitForURL('**' + target);
  if (hold > 0) { await page.mouse.down(); await page.waitForTimeout(hold); await page.mouse.up(); } else { await link.click(); }
  await nav;
  await page.waitForLoadState('domcontentloaded');
  const m = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    const fcp = performance.getEntriesByName('first-contentful-paint')[0];
    return { activationStart: Math.round(n.activationStart), responseStart: Math.round(n.responseStart), fcp: fcp ? Math.round(fcp.startTime) : null, prerendered: n.activationStart > 0 };
  });
  const vt = await page.evaluate(() => window.__vt ?? null);
  const elapsed = Date.now() - t0;
  await ctx.close();
  return { ...m, clickToDomContentLoadedMs: elapsed, vt };
}

for (const [label, rules, dwell, hold] of [['sans règles (survol 600 ms)', false, 600, 0], ['avec règles (survol 600 ms)', true, 600, 0], ['avec règles (clic immédiat)', true, 0, 0], ['avec règles (appui maintenu 1,2 s)', true, 300, 1200]]) {
  console.log(label.padEnd(36), JSON.stringify(await run(rules, dwell, hold)));
}
await browser.close();
