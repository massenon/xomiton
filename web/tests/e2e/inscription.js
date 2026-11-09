// Parcours d'inscription web (étape 1 « Compte » compacte, puis Profil / Sécurité / Consentements facultatifs),
// partagé par a11y.spec.js et parcours.spec.js. Sélecteurs accessibles (libellés, rôles) : les identifiants #f-* ne
// servent plus qu'aux champs sans rôle dédié.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';

export const MOT_DE_PASSE = 'Mot-de-passe-solide-2026!';
const LOG = fileURLToPath(new URL('../../storage/logs/laravel.log', import.meta.url));

/** Dernier code OTP écrit dans les journaux (OtpService : « [otp:log] Code 123456 pour e-mail (objet) »). */
export function lastOtp(email) {
  const lines = readFileSync(LOG, 'utf8').split('\n');
  for (let i = lines.length - 1; i >= 0; i--) {
    const m = lines[i].match(/\[otp:log\] Code (\d{4,10}) pour (\S+) /);
    if (m && m[2] === email) return m[1];
  }
  return null;
}

/** Étape 1 : champs, trois cases (CGU, confidentialité, âge) puis « Continuer ». Le compte est créé à cette étape. */
export async function remplirCompte(page, { pseudo, email }) {
  await page.goto('/inscription');
  await page.getByLabel('Pseudonyme').fill(pseudo);
  await page.getByLabel('Adresse e-mail').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(MOT_DE_PASSE);
  await page.getByLabel('Confirmation').fill(MOT_DE_PASSE);
  await page.getByRole('checkbox', { name: /J’accepte les/ }).check();
  await page.getByRole('checkbox', { name: /J’ai lu la/ }).check();
  await page.getByRole('checkbox', { name: /ans ou plus/ }).check();
  await page.getByRole('button', { name: 'Continuer' }).click();
}

/** Étapes 2 à 4 passées avec « Passer cette étape » (le bouton « Continuer » ou « Terminer » les validerait). */
export async function passerEtapes(page) {
  for (const step of ['profil', 'securite', 'consentements']) {
    await expect(page).toHaveURL(new RegExp(`/inscription/${step}`));
    await page.getByRole('button', { name: 'Passer cette étape' }).click();
  }
}
