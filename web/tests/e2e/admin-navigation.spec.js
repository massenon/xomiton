// Navigation sans rechargement de l'administration (spec Partie 1, wire:navigate) : six écrans enchaînés dont un avec
// graphique (tableau de bord), puis session expirée pendant une navigation.
// Base SQLite dédiée : E2E_DB (tests/e2e/serve.sh la recrée à chaque lancement), jamais database/database.sqlite. Exemple :
//   E2E_DB=/tmp/xomiton-nav.sqlite E2E_PORT=8124 PW_CHROMIUM=/opt/pw-browsers/chromium npx playwright test tests/e2e/admin-navigation.spec.js
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const ROOT = fileURLToPath(new URL('../..', import.meta.url));
const DB = process.env.E2E_DB || `${ROOT}database/e2e.sqlite`;
const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: DB };
const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: ROOT, env }).toString().trim().split('\n').pop();

const ADMIN = { identifier: 'admin@xomiton.bj', password: 'Admin123!' };
const OTP = '246810';

/** Écrans de la boucle : clé du menu (data-xm-nav), adresse, titre attendu dans <title>. */
const SCREENS = [
  { key: 'dashboard', path: '/admin', title: /Tableau de bord/i },
  { key: 'alertes', path: '/admin/alertes', title: /Alertes/i },
  { key: 'registres', path: '/admin/registres', title: /Registres/i },
  { key: 'indicateurs', path: '/admin/indicateurs', title: /Indicateurs/i },
  { key: 'maintenance', path: '/admin/maintenance', title: /Maintenance/i },
  { key: 'journal', path: '/admin/journal', title: /Journal/i },
];

/** Compte les écouteurs de document et de window (ajouts − retraits, doublons ignorés comme le fait le DOM). */
const COUNT_LISTENERS = () => {
  const registry = new Map();
  const key = (type, listener, capture) => `${type}|${capture ? 1 : 0}|${listener && listener.__id !== undefined ? listener.__id : String(listener)}`;
  let ids = 0;
  const patch = (target, name) => {
    const add = target.addEventListener.bind(target);
    const remove = target.removeEventListener.bind(target);
    registry.set(name, new Set());
    target.addEventListener = (type, listener, options) => {
      if (typeof listener === 'function' && listener.__id === undefined) listener.__id = ids++;
      const capture = typeof options === 'boolean' ? options : Boolean(options && options.capture);
      registry.get(name).add(key(type, listener, capture));
      return add(type, listener, options);
    };
    target.removeEventListener = (type, listener, options) => {
      const capture = typeof options === 'boolean' ? options : Boolean(options && options.capture);
      registry.get(name).delete(key(type, listener, capture));
      return remove(type, listener, options);
    };
  };
  patch(document, 'document');
  patch(window, 'window');
  window.__xmListeners = () => registry.get('document').size + registry.get('window').size;
};

/** Cookies de la première connexion, réutilisés : la route de connexion est limitée en débit (throttle:login). */
let sessionCookies = null;

async function login(page) {
  if (sessionCookies) {
    await page.context().addCookies(sessionCookies);
    await page.goto('/admin');
    await expect(page.locator('body.xm-admin')).toBeVisible();
    return;
  }
  await page.goto('/connexion');
  await page.locator('#f-identifier').fill(ADMIN.identifier);
  await page.locator('#f-password').fill(ADMIN.password);
  await page.locator('form[action$="/connexion"] button[type="submit"]').first().click();
  await page.waitForLoadState('networkidle');
  if (/code-de-verification/.test(page.url())) {
    const hash = createHash('sha256').update(OTP).digest('hex');
    tinker(`App\\Models\\OtpCode::query()->latest('id')->first()->forceFill(['code_hash' => '${hash}'])->save(); echo 'ok';`);
    await page.locator('#f-code').fill(OTP);
    await page.waitForURL((url) => !url.pathname.includes('code-de-verification'), { timeout: 8000 })
      .catch(() => page.getByRole('button', { name: 'Vérifier' }).click());
    await page.waitForLoadState('networkidle');
  }
  await page.goto('/admin');
  await expect(page.locator('body.xm-admin'), `url=${page.url()}`).toBeVisible();
  sessionCookies = await page.context().cookies();
}

const go = async (page, screen) => {
  await page.locator(`.xm-admin-sidebar a[data-xm-nav="${screen.key}"]`).click();
  await expect(page).toHaveURL(new RegExp(`${screen.path}(\\?.*)?$`));
  await expect(page.locator(`.xm-admin-sidebar a[data-xm-nav="${screen.key}"]`)).toHaveAttribute('aria-current', 'page');
  await page.waitForLoadState('networkidle');
  // Barre de progression de Livewire (NProgress, role="bar") : retirée en fin de navigation, avant toute mesure axe.
  await expect(page.locator('#nprogress, [role="bar"]')).toHaveCount(0);
};

test.describe('administration : navigation sans rechargement', () => {
  test('six écrans enchaînés, un seul graphique, 0 erreur console, 0 violation axe, 20 allers-retours sans fuite', async ({ page }) => {
    const problems = [];
    page.on('console', (message) => { if (message.type() === 'error') problems.push(`console: ${message.text()}`); });
    page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`));
    await page.addInitScript(COUNT_LISTENERS);
    await page.setViewportSize({ width: 1280, height: 900 });

    await login(page);
    await page.evaluate(() => {
      window.__nav = 'conserve';
      document.getElementById('xmSidebar').__marque = 'persistee';
      document.getElementById('xmNotif').__marque = 'persistee';
      localStorage.setItem('xm-admin-sidebar', 'collapsed');
      document.documentElement.classList.add('xm-sidebar-collapsed');
    });

    const violations = [];
    for (const screen of [...SCREENS.slice(1), SCREENS[0]]) {
      await go(page, screen);
      // Même document : le marqueur, la barre latérale et la cloche (persistées) n'ont pas été recréés.
      expect(await page.evaluate(() => ({
        nav: window.__nav,
        sidebar: document.getElementById('xmSidebar').__marque,
        bell: document.getElementById('xmNotif').__marque,
        collapsed: document.documentElement.classList.contains('xm-sidebar-collapsed'),
        theme: document.documentElement.getAttribute('data-bs-theme'),
      }))).toEqual({ nav: 'conserve', sidebar: 'persistee', bell: 'persistee', collapsed: true, theme: 'light' });

      // Titre, un seul élément courant, focus sur le h1 et annonce vocale.
      await expect(page).toHaveTitle(screen.title);
      await expect(page.locator('.xm-admin-sidebar [aria-current="page"]')).toHaveCount(1);
      await expect(page.locator('.xm-admin-topbar h1')).toBeFocused();
      await expect(page.locator('#xmRouteAnnouncer')).toHaveText(/^Page\s*: .+/);
      expect(await page.locator('#xmRouteAnnouncer').textContent()).toContain((await page.locator('.xm-admin-topbar h1').textContent()).trim());

      const axe = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
      for (const v of axe.violations) violations.push(`${screen.path} · ${v.id} (${v.impact}) : ${v.nodes[0].target.join(' ')}`);
    }
    expect(violations).toEqual([]);

    // Le tableau de bord : un graphique par canevas, jamais de doublon après un aller-retour.
    const charts = () => page.evaluate(() => ({
      canvases: document.querySelectorAll('[data-xm-dashboard-charts] canvas').length,
      instances: window.Chart ? Object.keys(window.Chart.instances).length : 0,
    }));
    await expect.poll(async () => (await charts()).canvases).toBeGreaterThan(0);
    await expect.poll(async () => { const c = await charts(); return c.instances === c.canvases; }).toBe(true);
    const before = await charts();

    // 20 allers-retours tableau de bord ↔ indicateurs (graphique à la demande) : écouteurs, nœuds et <head> stables.
    const measure = () => page.evaluate(() => ({ listeners: window.__xmListeners(), head: document.head.children.length, nodes: document.querySelectorAll('*').length }));
    await go(page, SCREENS[3]);
    await go(page, SCREENS[0]);
    const baseline = await measure();
    for (let i = 0; i < 20; i += 1) {
      await go(page, SCREENS[3]);
      await go(page, SCREENS[0]);
    }
    const after = await measure();
    const end = await charts();
    expect(end).toEqual(before);
    expect(after.listeners).toBeLessThanOrEqual(baseline.listeners + 2);
    expect(after.head).toBeLessThanOrEqual(baseline.head);
    expect(Math.abs(after.nodes - baseline.nodes)).toBeLessThan(40);
    expect(await page.evaluate(() => window.__nav)).toBe('conserve');

    expect(problems).toEqual([]);
  });

  test('session expirée pendant une navigation : chargement complet sur /connexion, sans écran blanc', async ({ page, context }) => {
    await login(page);
    await page.evaluate(() => { window.__nav = 'conserve'; });
    await context.clearCookies();

    await page.locator('.xm-admin-sidebar a[data-xm-nav="alertes"]').click();
    await page.waitForURL(/\/connexion/, { timeout: 15_000 });
    await expect(page.locator('form[action$="/connexion"]')).toBeVisible();
    // Chargement complet : le marqueur du document précédent a disparu.
    expect(await page.evaluate(() => window.__nav)).toBeUndefined();
    await expect(page.locator('body.xm-admin')).toHaveCount(0);
  });

  test('419 sur une navigation (réponse d\'erreur) : chargement complet', async ({ page }) => {
    await login(page);
    await page.evaluate(() => { window.__nav = 'conserve'; });
    await page.route('**/admin/journal', (route) => route.fulfill({ status: 419, contentType: 'text/html', body: '<!doctype html><html><body><h1>Page expirée</h1></body></html>' }), { times: 1 });

    await page.locator('.xm-admin-sidebar a[data-xm-nav="journal"]').click();
    // Après le relais, la requête de chargement complet n'est plus interceptée (times: 1) : la vraie page s'affiche.
    await page.waitForURL(/\/admin\/journal/, { timeout: 15_000 });
    await expect(page.locator('body.xm-admin')).toBeVisible();
    await expect.poll(() => page.evaluate(() => window.__nav)).toBeUndefined();
    await expect(page.locator('.xm-admin-topbar h1')).toBeVisible();
  });
});
