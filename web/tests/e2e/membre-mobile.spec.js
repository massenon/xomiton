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

let dernierCode = 0; // instant de la dernière demande de code : l'envoi d'un nouveau code attend 30 s (otp.resend_after_seconds)
async function seConnecter(page) {
  await page.waitForTimeout(Math.max(0, 31_000 - (Date.now() - dernierCode)));
  await page.goto('/connexion');
  await page.locator('#f-identifier').fill('membre@xomiton.app');
  await page.locator('#f-password').fill('Membre123!');
  await page.keyboard.press('Enter');
  dernierCode = Date.now();
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

// Bouton SOS flottant (fonction de sécurité, jamais masqué ni retiré) : il ne recouvre jamais la barre de saisie de
// /assistant ni celle de /messages (conversation ouverte), à tous les défilements et clavier virtuel ouvert (viewport
// de 400 px de haut) ; la carte de l'assistant respecte la gouttière du gabarit. Espace de défilement final sous le contenu.
const chevauche = (a, b) => a.left < b.right && a.right > b.left && a.top < b.bottom && a.bottom > b.top;

/** Ouvre (ou crée) une conversation avec le second compte de démonstration, puis la sélectionne. */
async function ouvrirConversation(page) {
  await page.goto('/messages', { waitUntil: 'networkidle' });
  await page.evaluate(async () => {
    const xsrf = () => decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    const headers = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() });
    const recipients = await (await fetch('/api/v1/conversations/recipients?q=fatou', { headers: headers() })).json().catch(() => ({}));
    const id = recipients.data?.[0]?.id;
    if (!id) return;
    const created = await (await fetch('/api/v1/conversations', { method: 'POST', headers: headers(), body: JSON.stringify({ kind: 'private', user_id: id }) })).json();
    const conv = created.data?.id;
    if (conv) await fetch(`/api/v1/conversations/${conv}/messages`, { method: 'POST', headers: headers(), body: JSON.stringify({ body: 'Un message assez long pour vérifier le retour à la ligne dans une bulle sur petit écran.', client_uuid: crypto.randomUUID() }) });
  });
  await page.goto('/messages', { waitUntil: 'networkidle' });
  await page.locator('.xm-conv').first().click();
  await page.locator('#chatInput').waitFor();
}

/** Rectangles de la barre de saisie (champ + boutons) et du bouton SOS, au défilement courant. */
const rectangles = (selecteur) => {
  const r = (el) => { const b = el.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom }; };
  const sos = document.querySelector('.xm-sos-fab');
  const barre = [...document.querySelectorAll(selecteur)].filter((el) => el.getBoundingClientRect().width > 0).map(r);
  return { sos: r(sos), taille: Math.min(sos.getBoundingClientRect().width, sos.getBoundingClientRect().height), couleur: getComputedStyle(sos.querySelector('button, a') ?? sos).backgroundColor, barre, hauteur: innerHeight };
};

const BARRES = [
  { uri: '/assistant', selecteur: '#assistant-input, form[autocomplete=off] button[type=submit], .xm-assistant-chips .btn' },
  { uri: '/messages', selecteur: '#chatInput, .xm-chat-compose .btn' },
];

test('espace membre · le bouton SOS ne recouvre jamais la barre de saisie (assistant, messages) · 360 et 390 px, clavier ouvert compris', async ({ page }) => {
  await seConnecter(page);
  const problemes = [];
  for (const size of WIDTHS) {
    for (const hauteur of [size.height, 400]) {
      await page.setViewportSize({ width: size.width, height: hauteur });
      for (const { uri, selecteur } of BARRES) {
        if (uri === '/messages') await ouvrirConversation(page); else await page.goto(uri, { waitUntil: 'networkidle' });
        if (!page.url().includes(uri)) problemes.push(`${size.width}x${hauteur} ${uri} : redirigé vers ${page.url()}`);
        await page.locator('.xm-sos-fab').waitFor({ timeout: 5000 }).catch(() => problemes.push(`${size.width}x${hauteur} ${uri} : pas de bouton SOS (${page.url()})`));
        const total = await page.evaluate(() => document.documentElement.scrollHeight - innerHeight);
        // Clavier ouvert (400 px) : la page tient en un écran ou deux, seuls le haut et le bas comptent (un défilement manuel peut
        // toujours faire passer la carte dans la zone du bouton, le champ mis au point est ramené au-dessus par scroll-padding).
        for (const fraction of (hauteur === 400 ? [0, 1] : [0, 0.25, 0.5, 0.75, 1])) {
          await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), Math.round(total * fraction));
          const m = await page.evaluate(rectangles, selecteur);
          // Bouton conservé : taille tactile, couleur, présent dans l'écran.
          if (m.taille < 44) problemes.push(`${size.width}x${hauteur} ${uri} : bouton SOS ${m.taille} px < 44`);
          if (m.couleur === 'rgba(0, 0, 0, 0)') problemes.push(`${size.width}x${hauteur} ${uri} : bouton SOS sans couleur`);
          if (m.sos.bottom > m.hauteur || m.sos.right > size.width) problemes.push(`${size.width}x${hauteur} ${uri} : bouton SOS hors de l'écran`);
          const visibles = m.barre.filter((b) => b.bottom > 0 && b.top < m.hauteur);
          for (const b of visibles) if (chevauche(b, m.sos)) problemes.push(`${size.width}x${hauteur} ${uri} @${fraction} : barre de saisie recouverte par le SOS`);
        }
        // Clavier virtuel (viewport réduit) : le champ mis au point reste visible, hors de la zone du bouton SOS.
        if (hauteur === 400) {
          const champ = page.locator(uri === '/assistant' ? '#assistant-input' : '#chatInput');
          await page.evaluate(() => document.activeElement?.blur());
          await champ.focus();
          await page.waitForTimeout(900); // défilement doux de la page (scroll-behavior: smooth)
          const c = await champ.boundingBox();
          const sos = await page.locator('.xm-sos-fab').boundingBox();
          const enTete = await page.locator('#memberHeader').boundingBox();
          if (!c || c.y < (enTete?.y ?? 0) + (enTete?.height ?? 0) || c.y + c.height > hauteur) problemes.push(`${size.width}x${hauteur} ${uri} : champ de saisie hors du viewport après focus`);
          else if (chevauche({ left: c.x, right: c.x + c.width, top: c.y, bottom: c.y + c.height }, { left: sos.x, right: sos.x + sos.width, top: sos.y, bottom: sos.y + sos.height })) problemes.push(`${size.width}x${hauteur} ${uri} : champ de saisie recouvert par le SOS après focus`);
        }
      }
    }
  }
  expect(problemes).toEqual([]);
});

test('espace membre · espace de défilement final sous le contenu et carte de l\'assistant à la gouttière · 360 et 390 px', async ({ page }) => {
  await seConnecter(page);
  const problemes = [];
  for (const size of WIDTHS) {
    await page.setViewportSize(size);
    for (const uri of ['/fil', '/groupes', '/forums', '/profil', '/parametres', '/notifications', '/assistant']) {
      await page.goto(uri, { waitUntil: 'networkidle' });
      await page.evaluate(() => window.scrollTo({ top: 1e6, behavior: 'instant' }));
      const m = await page.evaluate(() => {
        const sos = document.querySelector('.xm-sos-fab').getBoundingClientRect();
        const main = document.querySelector('main');
        const fin = main.getBoundingClientRect().bottom - parseFloat(getComputedStyle(main).paddingBottom);
        return { reserve: parseFloat(getComputedStyle(main).paddingBottom), dernier: fin, hautSos: sos.top };
      });
      if (m.reserve < 64 + 18 + 8) problemes.push(`${size.width} ${uri} : réserve basse ${m.reserve} px < bouton + marge`);
      if (m.dernier > m.hautSos) problemes.push(`${size.width} ${uri} : fin du contenu (${Math.round(m.dernier)}) sous le haut du SOS (${Math.round(m.hautSos)})`);
    }
    await page.goto('/assistant', { waitUntil: 'networkidle' });
    const g = await page.evaluate(() => {
      const carte = document.querySelector('.xm-assistant-chat').getBoundingClientRect();
      const conteneur = document.querySelector('main .container');
      const demi = parseFloat(getComputedStyle(conteneur).paddingLeft);
      const points = document.querySelector('.xm-assistant-chat .card-header .dropdown button').getBoundingClientRect();
      const largeur = document.documentElement.clientWidth;
      return { gauche: carte.left, droite: largeur - carte.right, demi, bord: carte.right - points.right, debord: document.documentElement.scrollWidth - largeur };
    });
    if (Math.abs(g.droite - g.gauche) > 1) problemes.push(`${size.width} assistant : marges ${g.gauche} / ${g.droite} inégales`);
    if (Math.abs(g.droite - g.demi) > 1) problemes.push(`${size.width} assistant : marge droite ${g.droite} ≠ gouttière ${g.demi}`);
    if (g.bord < 16) problemes.push(`${size.width} assistant : bouton « … » à ${g.bord} px du bord de la carte`);
    if (g.debord > 0) problemes.push(`${size.width} assistant : débordement horizontal ${g.debord} px`);
  }
  expect(problemes).toEqual([]);
});
