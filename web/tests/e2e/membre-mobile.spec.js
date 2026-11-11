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

// Bouton SOS (fonction de sécurité, jamais masqué, retiré ni dupliqué) : sur /assistant et /messages (pages à zone de saisie)
// il est DOCKÉ dans une bande réservée sous <main> (html.xm-dock, member.css) et ne recouvre JAMAIS un élément interactif,
// un texte ni la carte, à aucune position de défilement (balayage par pas de 40 px de haut en bas), avec ou sans clavier
// virtuel (viewport réduit à 400 px de haut), à 360, 390, 430 et 768 px, thèmes clair et sombre. La carte de l'assistant
// respecte la gouttière du gabarit.
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

/**
 * Dans la page, au défilement courant : tout ce que le bouton SOS recouvre réellement (élément interactif ou nœud de texte
 * visible sous le bouton, via elementsFromPoint : ce qui est rogné par une zone défilante n'est pas « recouvert »), et la
 * carte (assistant, messagerie) rognée à sa zone visible. Rectangles : getBoundingClientRect.
 */
const recouvrements = () => {
  const fab = document.querySelector('.xm-sos-fab');
  const fr = fab.getBoundingClientRect();
  const sos = { left: fr.left, right: fr.right, top: fr.top, bottom: fr.bottom };
  const dansFab = (n) => fab.contains(n);
  const inter = (b) => {
    const l = Math.max(b.left, sos.left), r = Math.min(b.right, sos.right), h = Math.max(b.top, sos.top), bas = Math.min(b.bottom, sos.bottom);
    return r - l > 1 && bas - h > 1 ? { x: (l + r) / 2, y: (h + bas) / 2 } : null;
  };
  const visibleEn = (pt, el) => document.elementsFromPoint(pt.x, pt.y).filter((n) => !dansFab(n)).some((n) => n === el || el.contains(n));
  const trouves = [];
  document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, summary, [tabindex]:not([tabindex="-1"]), [role=button], [role=switch]').forEach((el) => {
    if (dansFab(el)) return;
    const b = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    if (b.width <= 0 || b.height <= 0 || cs.visibility === 'hidden' || cs.display === 'none') return;
    const pt = inter(b);
    if (pt && visibleEn(pt, el)) trouves.push(`interactif « ${(el.getAttribute('aria-label') || el.textContent || el.id || el.tagName).trim().slice(0, 30)} »`);
  });
  const marche = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  for (let n = marche.nextNode(); n; n = marche.nextNode()) {
    const p = n.parentElement;
    if (!n.nodeValue.trim() || !p || dansFab(p) || p.closest('script, style, noscript, [hidden], .visually-hidden')) continue;
    const cs = getComputedStyle(p);
    if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    const plage = document.createRange();
    plage.selectNodeContents(n);
    for (const r of plage.getClientRects()) {
      const pt = r.width > 0 && r.height > 0 ? inter(r) : null;
      if (pt && visibleEn(pt, p)) { trouves.push(`texte « ${n.nodeValue.trim().slice(0, 30)} »`); break; }
    }
  }
  for (const carte of document.querySelectorAll('.xm-assistant-chat, .xm-chat')) {
    const r = carte.getBoundingClientRect();
    let b = { left: r.left, right: r.right, top: r.top, bottom: r.bottom };
    for (let a = carte.parentElement; a && a !== document.documentElement; a = a.parentElement) {
      if (getComputedStyle(a).overflowY === 'visible') continue;
      const z = a.getBoundingClientRect();
      b = { left: Math.max(b.left, z.left), right: Math.min(b.right, z.right), top: Math.max(b.top, z.top), bottom: Math.min(b.bottom, z.bottom) };
    }
    if (b.right > b.left && b.bottom > b.top && inter(b)) trouves.push('carte');
  }
  const bouton = fab.querySelector('button, a') ?? fab;
  return {
    trouves: [...new Set(trouves)],
    taille: Math.min(fr.width, fr.height),
    couleur: getComputedStyle(bouton).backgroundColor,
    boutons: document.querySelectorAll('#xmSosFab').length,
    dansEcran: fr.bottom <= (window.visualViewport?.height ?? innerHeight) + 0.5 && fr.right <= innerWidth + 0.5 && fr.top >= 0,
  };
};

/** Zone qui défile (en dock, <main> ; sinon la fenêtre) : hauteur à parcourir. */
const defilable = () => {
  const main = document.getElementById('contenu');
  const dock = getComputedStyle(main).overflowY === 'auto';
  return { dock, total: dock ? main.scrollHeight - main.clientHeight : document.documentElement.scrollHeight - innerHeight };
};
const defiler = ([y, dock]) => (dock ? document.getElementById('contenu') : window).scrollTo({ top: y, behavior: 'instant' });

const PAGES_SAISIE = ['/assistant', '/messages'];
const TAILLES = [{ width: 360, height: 800 }, { width: 390, height: 844 }, { width: 430, height: 932 }, { width: 768, height: 1024 }];

test('espace membre · le bouton SOS ne recouvre jamais rien sur /assistant et /messages · 360, 390, 430, 768 px, clair et sombre, clavier ouvert compris, balayage complet du défilement', async ({ page }) => {
  test.setTimeout(600_000);
  await seConnecter(page);
  const problemes = [];
  let positions = 0;
  for (const theme of ['light', 'dark']) {
    for (const taille of TAILLES) {
      for (const hauteur of [taille.height, 400]) { // 400 px : clavier virtuel ouvert (visualViewport réduit)
        await page.setViewportSize({ width: taille.width, height: hauteur });
        for (const uri of PAGES_SAISIE) {
          const cle = `${theme} ${taille.width}x${hauteur} ${uri}`;
          if (uri === '/messages') await ouvrirConversation(page); else await page.goto(uri, { waitUntil: 'networkidle' });
          if (!page.url().includes(uri)) { problemes.push(`${cle} : redirigé vers ${page.url()}`); continue; }
          await page.evaluate((t) => document.documentElement.setAttribute('data-bs-theme', t), theme);
          await page.locator('.xm-sos-fab').waitFor({ timeout: 5000 }).catch(() => problemes.push(`${cle} : pas de bouton SOS`));
          const { dock, total } = await page.evaluate(defilable);
          const ys = []; for (let y = 0; y < total; y += 40) ys.push(y); ys.push(Math.max(total, 0));
          for (const y of ys) { // balayage par pas de 40 px, de haut en bas, jusqu'à la fin
            await page.evaluate(defiler, [y, dock]);
            await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));
            const m = await page.evaluate(recouvrements);
            positions += 1;
            if (m.trouves.length) problemes.push(`${cle} @${y}/${total} : SOS recouvre ${m.trouves.join(', ')}`);
            // Bouton conservé : un seul, taille tactile, couleur rouge d'origine, dans l'écran.
            if (m.boutons !== 1) problemes.push(`${cle} : ${m.boutons} boutons SOS`);
            if (m.taille < 44) problemes.push(`${cle} : bouton SOS ${m.taille} px < 44`);
            if (m.couleur === 'rgba(0, 0, 0, 0)') problemes.push(`${cle} : bouton SOS sans couleur`);
            if (!m.dansEcran) problemes.push(`${cle} @${y} : bouton SOS hors de l'écran`);
          }
          // Clavier virtuel : le champ mis au point reste visible (zone défilante bornée), hors de la zone du bouton.
          if (hauteur === 400) {
            const champ = page.locator(uri === '/assistant' ? '#assistant-input' : '#chatInput');
            await page.evaluate(() => document.activeElement?.blur());
            await champ.focus();
            await page.waitForTimeout(900); // défilement doux
            const c = await champ.boundingBox();
            const sos = await page.locator('.xm-sos-fab').boundingBox();
            const enTete = await page.locator('#memberHeader').boundingBox();
            if (!c || c.y < (enTete?.y ?? 0) + (enTete?.height ?? 0) - 1 || c.y + c.height > hauteur) problemes.push(`${cle} : champ de saisie hors du viewport après focus`);
            else if (chevauche({ left: c.x, right: c.x + c.width, top: c.y, bottom: c.y + c.height }, { left: sos.x, right: sos.x + sos.width, top: sos.y, bottom: sos.y + sos.height })) problemes.push(`${cle} : champ de saisie recouvert par le SOS après focus`);
          }
        }
      }
    }
  }
  expect(positions).toBeGreaterThan(300); // le balayage a bien eu lieu
  expect(problemes).toEqual([]);
});

test('espace membre · espace de défilement final sous le contenu et carte de l\'assistant à la gouttière · 360 et 390 px', async ({ page }) => {
  await seConnecter(page);
  const problemes = [];
  for (const size of WIDTHS) {
    await page.setViewportSize(size);
    // Pages à zone de saisie (/assistant, /messages) : bande réservée, couvertes par le balayage complet ci-dessus.
    for (const uri of ['/fil', '/groupes', '/forums', '/profil', '/parametres', '/notifications']) {
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

// Clavier virtuel ouvert (visualViewport réduit à 480, 400 ou 320 px de haut, champ au focus) : toute la hauteur revient à la
// saisie et au fil (D-SOS-2). En-tête du site et barre « Menu » repliés, bande SOS à 56 px avec un bouton de 48 px (≥ 44),
// unique, rouge, jamais masqué ni recouvert ; la saisie est visible, au moins 120 px de messages restent visibles, sans
// défilement de la page ; à la fermeture du clavier (hauteur rendue) tout revient à l'état normal (en-tête, SOS 64 px).
const MESURES_CLAVIER = (sel) => {
  const vh = window.visualViewport?.height ?? innerHeight;
  const main = document.getElementById('contenu');
  const mr = main.getBoundingClientRect();
  const fab = document.querySelector('.xm-sos-fab').getBoundingClientRect();
  const champ = document.querySelector(sel.champ);
  const cr = champ.getBoundingClientRect();
  const liste = document.querySelector(sel.liste).getBoundingClientRect();
  const visible = Math.max(0, Math.min(liste.bottom, mr.bottom, vh) - Math.max(liste.top, mr.top, 0));
  const enTete = document.getElementById('memberHeader');
  const barreMenu = document.querySelector('#memberSidenav > .d-lg-none');
  const bouton = document.querySelector('.xm-sos-fab').querySelector('button, a') ?? document.querySelector('.xm-sos-fab');
  const dessus = document.elementFromPoint(cr.left + cr.width / 2, cr.top + cr.height / 2);
  return {
    champVisible: cr.top >= mr.top - 1 && cr.bottom <= Math.min(mr.bottom, vh) + 1 && dessus === champ,
    messages: Math.round(visible),
    defilement: Math.round(main.scrollTop) + Math.round(scrollY),
    taille: Math.min(fab.width, fab.height),
    couleur: getComputedStyle(bouton).backgroundColor,
    boutons: document.querySelectorAll('#xmSosFab').length,
    sosVisible: fab.top >= 0 && fab.bottom <= vh + 0.5 && fab.right <= innerWidth + 0.5,
    mainSousSos: mr.bottom > fab.top + 0.5,
    champSousSos: cr.left < fab.right && cr.right > fab.left && cr.top < fab.bottom && cr.bottom > fab.top,
    enTeteVisible: getComputedStyle(enTete).display !== 'none' && enTete.getBoundingClientRect().height > 0,
    barreMenuVisible: barreMenu ? getComputedStyle(barreMenu).display !== 'none' : false,
    classe: document.documentElement.classList.contains('xm-kbd-open'),
  };
};

test('espace membre · clavier ouvert : saisie visible, ≥ 120 px de messages, SOS ≥ 44 px sans chevauchement, retour à la normale · 360 et 390 px, 320 à 480 px de haut, clair et sombre', async ({ page }) => {
  test.setTimeout(300_000);
  await seConnecter(page);
  const problemes = [];
  for (const theme of ['light', 'dark']) {
    for (const largeur of [360, 390]) {
      for (const hauteur of [320, 400, 480]) {
        for (const uri of PAGES_SAISIE) {
          const cle = `${theme} ${largeur}x${hauteur} ${uri}`;
          await page.setViewportSize({ width: largeur, height: 844 });
          if (uri === '/messages') await ouvrirConversation(page); else await page.goto(uri, { waitUntil: 'networkidle' });
          await page.evaluate((t) => document.documentElement.setAttribute('data-bs-theme', t), theme);
          const sel = uri === '/assistant' ? { champ: '#assistant-input', liste: '.xm-assistant-log' } : { champ: '#chatInput', liste: '.xm-chat-msgs' };
          const normal = await page.evaluate(MESURES_CLAVIER, sel);
          if (normal.classe || !normal.enTeteVisible || normal.taille < 63) problemes.push(`${cle} : état normal altéré avant le clavier (${JSON.stringify(normal)})`);
          await page.setViewportSize({ width: largeur, height: hauteur }); // le clavier s'ouvre : visualViewport réduit
          await page.locator(sel.champ).focus();
          await page.waitForTimeout(700);
          const m = await page.evaluate(MESURES_CLAVIER, sel);
          if (!m.classe) problemes.push(`${cle} : mode clavier non activé`);
          if (!m.champVisible) problemes.push(`${cle} : saisie non visible`);
          if (m.messages < 120) problemes.push(`${cle} : ${m.messages} px de messages visibles < 120`);
          if (m.defilement > 0) problemes.push(`${cle} : défilement ${m.defilement} px nécessaire`);
          if (m.taille < 44) problemes.push(`${cle} : bouton SOS ${m.taille} px < 44`);
          if (m.boutons !== 1) problemes.push(`${cle} : ${m.boutons} boutons SOS`);
          if (m.couleur === 'rgba(0, 0, 0, 0)') problemes.push(`${cle} : bouton SOS sans couleur`);
          if (!m.sosVisible) problemes.push(`${cle} : bouton SOS hors de l'écran`);
          if (m.mainSousSos || m.champSousSos) problemes.push(`${cle} : SOS recouvre la zone de page ou la saisie`);
          if (m.enTeteVisible || m.barreMenuVisible) problemes.push(`${cle} : en-tête ou barre « Menu » encore affichés`);
          // Fermeture du clavier : la hauteur est rendue, le champ garde le focus (cas courant sur Android).
          await page.setViewportSize({ width: largeur, height: 844 });
          await page.waitForTimeout(400);
          const fin = await page.evaluate(MESURES_CLAVIER, sel);
          if (fin.classe || !fin.enTeteVisible || fin.taille < 63) problemes.push(`${cle} : pas de retour à la normale (${JSON.stringify(fin)})`);
        }
      }
    }
  }
  expect(problemes).toEqual([]);
});
