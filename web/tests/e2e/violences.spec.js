// Auto-évaluation confidentielle de /violences (P4-04) : critère d'acceptation « aucune requête réseau pendant
// l'auto-évaluation », vérifié sur la vraie page (layout public complet : share.js, boîte d'envoi, service worker,
// assistant, modules chargés à la demande) et pas seulement sur le module isolé (violences.test.js). Toute requête
// émise par la page pendant les réponses, « Voir mon résultat », le changement d'une réponse, « Recommencer » et le
// départ simulé (pagehide) fait échouer le parcours. Les requêtes propres au service worker (précache en arrière-plan)
// ne sont pas émises par la page : elles ne sont pas comptées.
import { expect, test } from '@playwright/test';

test('/violences : l’auto-évaluation ne fait aucune requête réseau', async ({ page }) => {
  await page.goto('/violences', { waitUntil: 'networkidle' });
  const check = page.locator('#auto-evaluation');
  await expect(check.locator('[data-check-tools]')).toBeVisible();
  // Modules chargés à la demande et appels de démarrage terminés avant le début de l'écoute.
  await page.waitForLoadState('networkidle');

  const requests = [];
  page.on('request', (request) => requests.push(`${request.method()} ${request.url()}`));

  const questions = check.locator('[data-check-question]');
  const total = await questions.count();
  expect(total).toBe(8);
  for (let i = 0; i < total; i += 1) {
    await check.locator(`label[for="check-q${i}-${i < 5 ? 'oui' : 'non'}"]`).click();
  }
  await check.getByRole('button', { name: 'Voir mon résultat' }).click();
  await expect(check.locator('[data-check-result="high"]')).toBeVisible();

  // Une réponse change : l'ancien résultat disparaît, puis un nouveau calcul.
  await check.locator('label[for="check-q0-non"]').click();
  await expect(check.locator('[data-check-result]:visible')).toHaveCount(0);
  await check.getByRole('button', { name: 'Voir mon résultat' }).click();
  await expect(check.locator('[data-check-result="medium"]')).toBeVisible();

  await check.getByRole('button', { name: 'Recommencer' }).click();
  await expect(check.locator('input:checked')).toHaveCount(0);
  await expect(check.locator('[data-check-result]:visible')).toHaveCount(0);

  await check.locator('label[for="check-q1-oui"]').click();
  await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
  await expect(check.locator('input:checked')).toHaveCount(0);

  // Laisse partir une éventuelle requête différée (minuterie, beacon) avant de conclure.
  await page.waitForTimeout(1500);
  expect(requests).toEqual([]);
});
