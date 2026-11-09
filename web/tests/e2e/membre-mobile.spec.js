// Rendu mobile de l'espace membre : aucun débordement horizontal à 360 et 390 px sur les pages membres (les masques
// `overflow-x` du gabarit sont retirés pour mesurer le vrai débordement), en-tête sur une seule ligne (boutons ronds
// dans la hauteur de la barre), cibles tactiles de 44 px, aucun <img> d'avatar de personne (initiales neutres) et aucune
// requête vers un service externe d'avatars. Compte de démonstration membre@xomiton.app (mot de passe : docs/GUIDE_RECETTE.md).
import { expect, test } from '@playwright/test';
import { lastOtp } from './inscription.js';

const PAGES = ['/fil', '/assistant', '/messages', '/groupes', '/evenements', '/forums', '/profil', '/parametres', '/notifications', '/recherche', '/annuaire', '/qui-suivre'];
const WIDTHS = [{ width: 360, height: 800 }, { width: 390, height: 844 }];

// Écran tactile (pointeur grossier) : les règles de cibles de 44 px de member.css s'appliquent.
test.use({ hasTouch: true, isMobile: true });

async function seConnecter(page) {
  await page.goto('/connexion');
  await page.locator('#f-identifier').fill('membre@xomiton.app');
  await page.locator('#f-password').fill('Membre123!');
  await page.keyboard.press('Enter');
  await page.waitForURL(/code-de-verification/);
  await expect.poll(() => lastOtp('membre@xomiton.app'), { timeout: 10_000 }).not.toBeNull();
  await page.locator('#f-code').fill(lastOtp('membre@xomiton.app'));
  await page.waitForURL((url) => !url.pathname.includes('code-de-verification'), { timeout: 8000 })
    .catch(() => page.getByRole('button', { name: 'Vérifier' }).click());
  await page.waitForLoadState('networkidle');
}

/** Mesures dans la page : débordement réel, en-tête, cibles tactiles, images d'avatar. */
const mesurer = () => {
  const style = document.createElement('style');
  style.textContent = 'html, body, main { overflow-x: visible !important; }';
  document.head.appendChild(style);
  const largeur = document.documentElement.clientWidth;
  const ignore = '.offcanvas, .modal, [hidden], .collapse:not(.show), .visually-hidden, .xm-skip-link, .dropdown-menu:not(.show), .xm-sos-fab';
  const debordent = [];
  document.querySelectorAll('body *').forEach((el) => {
    if (el.closest(ignore)) return;
    const cs = getComputedStyle(el);
    if (cs.position === 'fixed' || cs.display === 'none' || cs.visibility === 'hidden') return;
    const r = el.getBoundingClientRect();
    if (!r.width || r.right <= largeur + 1) return;
    for (let a = el.parentElement; a && a.tagName !== 'MAIN' && a !== document.body; a = a.parentElement) {
      const o = getComputedStyle(a).overflowX;
      if (['auto', 'scroll', 'hidden', 'clip'].includes(o) && a.getBoundingClientRect().right <= largeur + 1) return; // défilement voulu (puces, onglets)
    }
    debordent.push(`${el.tagName.toLowerCase()}.${String(el.className?.baseVal ?? el.className).split(' ').slice(0, 2).join('.')} → ${Math.round(r.right)}`);
  });
  const header = document.querySelector('#memberHeader');
  const boutons = header?.querySelector('.container > ul.nav')?.getBoundingClientRect();
  const barre = header?.getBoundingClientRect();
  const petits = [...document.querySelectorAll('main a[href], main button, main input:not([type=hidden]), main select, main textarea')].filter((e) => {
    if (e.closest('.offcanvas, .collapse:not(.show), .dropdown-menu:not(.show), .visually-hidden, [hidden]')) return false;
    const cs = getComputedStyle(e);
    const r = e.getBoundingClientRect();
    if (!r.width || cs.visibility === 'hidden' || (cs.display === 'inline' && e.tagName === 'A')) return false;
    if (e.matches('.form-check-input')) return r.width < 23.5 || r.height < 23.5;
    return r.width < 43.5 || r.height < 43.5;
  }).map((e) => `${e.tagName.toLowerCase()}:${(e.textContent || e.getAttribute('aria-label') || '').trim().slice(0, 20)}`);
  return {
    scrollWidth: document.documentElement.scrollWidth,
    largeur,
    debordent: [...new Set(debordent)].slice(0, 5),
    entetePropre: Boolean(boutons && barre && boutons.bottom <= barre.bottom + 1),
    petits: petits.slice(0, 8),
    avatarsImg: document.querySelectorAll('.avatar img, img.avatar-img').length,
  };
};

test('espace membre · aucun débordement horizontal, en-tête propre, cibles de 44 px, avatars neutres · 360 et 390 px', async ({ page }) => {
  const externes = [];
  page.on('request', (request) => {
    const { hostname } = new URL(request.url());
    if (!['127.0.0.1', 'localhost'].includes(hostname)) externes.push(request.url());
  });
  await seConnecter(page);

  const problemes = [];
  for (const size of WIDTHS) {
    await page.setViewportSize(size);
    for (const uri of PAGES) {
      await page.goto(uri, { waitUntil: 'networkidle' });
      const m = await page.evaluate(mesurer);
      if (m.scrollWidth > m.largeur) problemes.push(`${size.width} ${uri} : scrollWidth ${m.scrollWidth} > ${m.largeur}`);
      if (m.debordent.length) problemes.push(`${size.width} ${uri} : éléments hors de l'écran ${m.debordent.join(' ; ')}`);
      if (!m.entetePropre) problemes.push(`${size.width} ${uri} : boutons de l'en-tête hors de la barre`);
      // Le lien d'avatar du gabarit (aria-hidden, tabindex -1) et les liens « Voir tout » en ligne ne comptent pas.
      for (const nom of m.petits) if (!/^a:(A|Voir tout|Tout voir|Mot de passe oublié)?/.test(nom)) problemes.push(`${size.width} ${uri} : cible < 44 px ${nom}`);
      if (m.avatarsImg > 0) problemes.push(`${size.width} ${uri} : ${m.avatarsImg} <img> d'avatar (initiales attendues)`);
    }
  }
  expect(problemes).toEqual([]);
  expect(externes).toEqual([]);
});
