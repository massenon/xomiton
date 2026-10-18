// Hors connexion web (P5-10, ADR-26, ADR-32) : critères d'acceptation de la ligne P5-10 et du Playwright hors ligne de
// P5-04. `/urgences` servie sans réseau (copie marquée, bandeau) et repli d'une page non gardée sur /urgences ;
// `/signaler` envoyé hors ligne : mis en file (IndexedDB), parti au retour du réseau par POST /api/v1/signalements, code
// de suivi affiché, et un rejeu de la même entrée (même Idempotency-Key) rend le même code sans créer de doublon ; deux
// signalements faits hors ligne depuis la copie en cache de /signaler (deux affichages, deux clés) partent tous les deux ;
// la police d'icônes (appelée avec un paramètre de version) est servie par le précache sans réseau.
// `/collecte` (Vue, P9-04) n'existe pas encore : son parcours viendra avec P9-07.
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

const ROOT = fileURLToPath(new URL('../..', import.meta.url));

/** Nombre d'alertes dans la base e2e (tests/e2e/serve.sh). */
const alertCount = () => Number(execFileSync('php', ['artisan', 'tinker', '--execute', 'echo App\\Models\\Alert::count();'], {
  cwd: ROOT,
  env: { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: `${ROOT}database/e2e.sqlite` },
}).toString().trim().split('\n').pop());

/** Entrées de la boîte d'envoi, sans créer la base si elle n'existe pas encore. */
const outboxCount = () => indexedDB.databases().then((dbs) => (dbs.some((db) => db.name === 'xomiton-outbox')
  ? new Promise((resolve) => {
    const request = indexedDB.open('xomiton-outbox');
    request.onsuccess = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains('entries')) { db.close(); resolve(0); return; }
      const count = db.transaction('entries').objectStore('entries').count();
      count.onsuccess = () => { db.close(); resolve(count.result); };
    };
    request.onerror = () => resolve(-1);
  })
  : 0));

/**
 * Réseau coupé : `setOffline` pour la page (navigator.onLine, évènements) et abandon de toute requête du contexte, y
 * compris celles du service worker, que l'émulation hors ligne de Playwright ne coupe pas toujours.
 */
async function offline(context) {
  await context.setOffline(true);
  await context.route('**/*', (route) => route.abort('internetdisconnected'));
}

async function online(context) {
  await context.unrouteAll({ behavior: 'ignoreErrors' });
  await context.setOffline(false);
}

/** Remplit les six étapes de /signaler avec un récit propre à `label`, jusqu'au bouton d'envoi. */
async function fillReport(page, label) {
  await page.locator('#f-type-0').check();
  await page.locator('#f-category').selectOption({ index: 1 });
  await next(page, 2);
  await page.locator('#f-description').fill(`Des faits décrits avec assez de détails pour le parcours hors ligne (${label}).`);
  await next(page, 3);
  await page.locator('#f-zone_id').selectOption({ index: 1 });
  await next(page, 4);
  await next(page, 5);
  await next(page, 6);
}

async function controlled(page) {
  await page.evaluate(() => navigator.serviceWorker.ready.then(() => undefined));
  await page.waitForFunction(() => navigator.serviceWorker.controller !== null);
}

/** « Continuer » de l'étape affichée, puis attente de l'étape `step`. */
async function next(page, step) {
  await page.locator('.xm-report__nav button.btn-primary:visible').click();
  await expect(page.locator(`#etape-${step}`)).toBeVisible();
}

test('/urgences est servie sans réseau, une page non gardée se replie sur /urgences', async ({ page, context }) => {
  await page.goto('/');
  await controlled(page);

  await offline(context);
  await page.goto('/urgences');
  await expect(page.locator('html')).toHaveAttribute('data-xm-offline-copy', '');
  await expect(page.locator('h1').first()).toContainText('Contacts d’urgence');
  await expect(page.locator('#xmOfflineBar')).toBeVisible();

  await page.goto('/aide');
  await expect(page.locator('h1').first()).toContainText('Contacts d’urgence');
  await expect(page.locator('html')).toHaveAttribute('data-xm-offline-copy', '');
  await online(context);
});

test('/signaler hors ligne : mis en file, envoyé au retour du réseau, sans doublon', async ({ page, context }) => {
  const before = alertCount();
  await page.goto('/signaler');
  await controlled(page);
  await page.reload();

  await fillReport(page, 'premier');

  await offline(context);
  await page.locator('#f-send').click();
  await expect(page.getByText('Tes réponses restent sur cet appareil', { exact: false })).toBeVisible();
  await expect.poll(() => page.evaluate(outboxCount)).toBe(1);
  expect(alertCount()).toBe(before);

  await online(context);
  const codes = page.locator('#xmOutboxNotices [data-xm-outbox-code]');
  await expect(codes).toHaveCount(1, { timeout: 20_000 });
  await expect(codes.first()).toHaveText(/\S{4,}/);
  await expect.poll(() => page.evaluate(outboxCount)).toBe(0);
  expect(alertCount()).toBe(before + 1);

  // Même formulaire, même clé : la réponse d'origine revient (ADR-32), aucun second signalement.
  await page.evaluate(() => {
    const form = document.querySelector('form[data-outbox]');
    form.dispatchEvent(new CustomEvent('xm:outbox-submit', { bubbles: true, cancelable: true, detail: { form, type: 'signalement' } }));
  });
  await expect(codes).toHaveCount(2, { timeout: 20_000 });
  const [first, second] = await codes.allTextContents();
  expect(second).toBe(first);
  expect(alertCount()).toBe(before + 1);
});

test('deux signalements faits hors ligne depuis la copie de /signaler : deux entrées, tous deux envoyés', async ({ page, context }) => {
  const before = alertCount();
  await page.goto('/signaler');
  await controlled(page);

  await offline(context);
  const keys = [];
  for (const label of ['premier hors ligne', 'second hors ligne']) {
    await page.goto('/signaler');
    await expect(page.locator('html')).toHaveAttribute('data-xm-offline-copy', '');
    keys.push(await page.locator('form[data-outbox] input[name="idempotency_key"]').inputValue());
    await fillReport(page, label);
    await page.locator('#f-send').click();
    await expect(page.getByText('Tes réponses restent sur cet appareil', { exact: false })).toBeVisible();
  }
  expect(keys[0]).not.toBe(keys[1]);
  await expect.poll(() => page.evaluate(outboxCount)).toBe(2);
  expect(alertCount()).toBe(before);

  await online(context);
  await expect(page.locator('#xmOutboxNotices [data-xm-outbox-code]')).toHaveCount(2, { timeout: 20_000 });
  await expect.poll(() => page.evaluate(outboxCount)).toBe(0);
  expect(alertCount()).toBe(before + 2);
});

test('la police d’icônes est servie sans réseau malgré son paramètre de version', async ({ page, context }) => {
  await page.goto('/urgences');
  await controlled(page);

  await offline(context);
  await page.goto('/urgences');
  await expect(page.locator('html')).toHaveAttribute('data-xm-offline-copy', '');
  const statuses = await page.evaluate(async () => {
    const faces = [...document.fonts].filter((face) => face.family.replace(/["']/g, '') === 'bootstrap-icons');
    await Promise.all(faces.map((face) => face.load().catch(() => undefined)));
    return faces.map((face) => face.status);
  });
  expect(statuses.length).toBeGreaterThan(0);
  expect(statuses.every((status) => status === 'loaded')).toBe(true);
  await online(context);
});
