// P16-04 : trois parcours de bout en bout sur le serveur e2e (tests/e2e/serve.sh, base SQLite dédiée).
//  1. inscription → code (OTP) → écrans de fin d'inscription → fil. Le code n'est lisible nulle part (SHA-256 en base) :
//     comme les tests Pest, le parcours le remplace dans la base e2e par un code connu avant de le saisir.
//  2. signalement anonyme : six étapes, envoi, code de suivi affiché, un signalement de plus en base.
//  3. SOS annulable : maintien 2 s, décompte local, « Annuler l'alerte » ; aucune alerte n'est créée.
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

const ROOT = fileURLToPath(new URL('../..', import.meta.url));
const env = { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: `${ROOT}database/e2e.sqlite` };

const tinker = (code) => execFileSync('php', ['artisan', 'tinker', '--execute', code], { cwd: ROOT, env }).toString().trim().split('\n').pop();
const alertCount = () => Number(tinker('echo App\\Models\\Alert::count();'));

test('inscription → code de vérification → fil', async ({ page }) => {
  const email = `parcours${Date.now()}@exemple.bj`;
  const code = '135790';

  await page.goto('/inscription');
  await page.locator('#f-pseudo').fill(`parcours${Date.now() % 100000}`);
  await page.locator('#f-email').fill(email);
  await page.locator('#f-password').fill('Mot-de-passe-solide-2026!');
  await page.locator('#f-password_confirmation').fill('Mot-de-passe-solide-2026!');
  await page.locator('#f-cgu').check();
  await page.locator('#f-privacy').check();
  await page.getByRole('button', { name: 'Créer mon compte' }).click();

  // Vérification différée (Q12 c) : le compte arrive d'abord sur les étapes facultatives de fin d'inscription.
  await expect(page).toHaveURL(/\/inscription\/profil/);
  for (const step of ['profil', 'securite', 'consentements']) {
    await expect(page).toHaveURL(new RegExp(`/inscription/${step}`));
    await page.getByRole('button', { name: /^Passer/ }).first().click();
  }
  await page.waitForLoadState('networkidle');

  // Code connu : seul son SHA-256 est stocké (OtpCode::hash).
  const hash = createHash('sha256').update(code).digest('hex');
  tinker(`App\\Models\\OtpCode::query()->latest('id')->first()->forceFill(['code_hash' => '${hash}'])->save(); echo 'ok';`);
  await page.goto('/code-de-verification');
  await page.locator('#f-code').fill(code);
  // La saisie du dernier chiffre envoie le formulaire d'elle-même (data-xm-otp-form) ; sinon bouton « Vérifier ».
  await page.waitForURL((url) => !url.pathname.includes('code-de-verification'), { timeout: 8000 })
    .catch(() => page.getByRole('button', { name: 'Vérifier' }).click());
  await page.waitForLoadState('networkidle');

  await page.goto('/fil');
  await expect(page).toHaveURL(/\/fil/);
  await expect(page.locator('main, [role="main"]').first()).toBeVisible();
  expect(tinker(`echo App\\Models\\User::where('email', '${email}')->whereNotNull('email_verified_at')->count();`)).toBe('1');
});

test('signalement anonyme : code de suivi affiché, une alerte de plus', async ({ page }) => {
  const before = alertCount();
  await page.goto('/signaler');
  await page.locator('#f-type-0').check();
  await page.locator('#f-category').selectOption({ index: 1 });
  const next = async (step) => {
    await page.locator('.xm-report__nav button.btn-primary:visible').click();
    await expect(page.locator(`#etape-${step}`)).toBeVisible();
  };
  await next(2);
  await page.locator('#f-description').fill('Des faits décrits avec assez de détails pour le parcours de bout en bout (signalement anonyme).');
  await next(3);
  await page.locator('#f-zone_id').selectOption({ index: 1 });
  await next(4);
  await next(5);
  await next(6);
  await page.locator('#f-send').click();
  await expect(page.locator('h1, h2').filter({ hasText: /suivi|reçu|envoy/i }).first()).toBeVisible({ timeout: 20_000 });
  expect(alertCount()).toBe(before + 1);
});

test('SOS : le décompte est annulable et aucune alerte ne part', async ({ page }) => {
  const before = alertCount();
  await page.goto('/sos');
  await page.locator('#f-share_location').uncheck().catch(() => undefined);

  const button = page.locator('#sosButton');
  const box = await button.boundingBox();
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.mouse.down();
  await page.waitForTimeout(2400);
  await page.mouse.up();

  const cancel = page.locator('button:visible', { hasText: 'Annuler l’alerte' }).first();
  await expect(cancel).toBeVisible({ timeout: 5000 });
  await cancel.dispatchEvent('click');
  await expect(page.getByText('Alerte annulée', { exact: false }).first()).toBeVisible();
  await page.waitForTimeout(6000);
  expect(alertCount()).toBe(before);
});
