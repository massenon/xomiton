// Mesures navigateur (docs/PERFORMANCE.md §11) : Chromium + Playwright, 4G lente (1,6 Mb/s, 150 ms) et CPU ×4.
// TTFB, FCP, LCP, CLS, requêtes et octets transférés, en froid (cache vide) puis en chaud (2e visite, cache HTTP).
// Usage : PW_CHROMIUM=/opt/pw-browsers/chromium node scripts/perf/measure.mjs [baseUrl] [--json] [chemin ...]
import { chromium } from '@playwright/test';

const args = process.argv.slice(2);
const base = args.find((a) => a.startsWith('http')) || 'http://127.0.0.1:8099';
const json = args.includes('--json');
const custom = args.filter((a) => a.startsWith('/'));
const paths = custom.length ? custom : ['/', '/politique-de-confidentialite', '/conditions-utilisation', '/mentions-legales', '/cookies', '/supprimer-mon-compte', '/connexion'];

const browser = await chromium.launch({ executablePath: process.env.PW_CHROMIUM, args: ['--disable-features=SpeculationRulesPrefetchProxy'] });

async function visit(context, path) {
  const page = await context.newPage();
  const cdp = await context.newCDPSession(page);
  await cdp.send('Network.enable');
  await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
  await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
  let requests = 0; let bytes = 0; let fromCache = 0;
  const sizes = new Map();
  cdp.on('Network.requestWillBeSent', () => { requests++; });
  cdp.on('Network.requestServedFromCache', () => { fromCache++; });
  cdp.on('Network.loadingFinished', (e) => { bytes += e.encodedDataLength; sizes.set(e.requestId, e.encodedDataLength); });
  await page.addInitScript(() => {
    window.__m = { fcp: null, lcp: null, cls: 0 };
    new PerformanceObserver((l) => { for (const e of l.getEntries()) if (e.name === 'first-contentful-paint') window.__m.fcp = e.startTime; }).observe({ type: 'paint', buffered: true });
    new PerformanceObserver((l) => { const e = l.getEntries().at(-1); window.__m.lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__m.cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
  });
  const response = await page.goto(base + path, { waitUntil: 'load' });
  await page.waitForTimeout(800);
  const m = await page.evaluate(() => {
    const n = performance.getEntriesByType('navigation')[0];
    return { ...window.__m, ttfb: n.responseStart - n.startTime, load: n.loadEventEnd, dcl: n.domContentLoadedEventEnd };
  });
  const headers = response.headers();
  const out = { path, status: response.status(), ttfb: Math.round(m.ttfb), fcp: Math.round(m.fcp ?? 0), lcp: Math.round(m.lcp ?? 0), cls: Number(m.cls.toFixed(3)), load: Math.round(m.load), requests, fromCache, kb: Math.round(bytes / 1024), cookies: headers['set-cookie'] ? 'oui' : 'non', cache: headers['cache-control'] || '-' };
  await page.close();
  return out;
}

const rows = [];
for (const path of paths) {
  const cold = await browser.newContext({ serviceWorkers: 'block' });
  const c = await visit(cold, path);
  const w = await visit(cold, path); // même contexte : cache HTTP et cookies conservés
  await cold.close();
  rows.push({ cold: c, warm: w });
}
await browser.close();

if (json) { console.log(JSON.stringify(rows, null, 1)); process.exit(0); }
const f = (r) => `${r.path.padEnd(31)} TTFB ${String(r.ttfb).padStart(4)} FCP ${String(r.fcp).padStart(5)} LCP ${String(r.lcp).padStart(5)} CLS ${r.cls} load ${String(r.load).padStart(5)} req ${String(r.requests).padStart(2)} (cache ${r.fromCache}) ${String(r.kb).padStart(5)} Ko cookie:${r.cookies} ${r.cache}`;
console.log('--- FROID'); rows.forEach((r) => console.log(f(r.cold)));
console.log('--- CHAUD'); rows.forEach((r) => console.log(f(r.warm)));
