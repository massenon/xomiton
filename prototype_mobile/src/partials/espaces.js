/* Session et sélecteur d'espace partagés (simulateur, espace pair, admin mobile) — décision client du 29/10/2026 :
   UNE SEULE application, un seul compte ; un compte peut cumuler Membre + Pair éducateur·rice et/ou Admin.
   Après connexion, un sélecteur d'espace s'affiche selon les rôles ; un commutateur reste dans l'en-tête et dans le profil.
   La session de démonstration est partagée entre les trois pages par localStorage (clé xm-session-v1) ;
   sans stockage disponible (file://, navigation privée), chaque page retombe sur des rôles de démonstration. */
(function () {
  'use strict';
  const KEY = 'xm-session-v1';
  const ORDER = ['membre', 'pair', 'admin'];
  const DEST = { membre: 'index.html#/dashboard', pair: 'espace-pair.html#/accueil', admin: 'admin-mobile.html#/tableau' };
  const LAB = { membre: 'Membre', pair: 'Pair éducateur·rice', admin: 'Administration' };
  const SHORT = { membre: 'Membre', pair: 'Pair', admin: 'Admin' };
  const DESC = {
    membre: 'Réseau social, messages, SOS, ressources et événements.',
    pair: 'Alertes routées vers toi, dossiers, collecte hors ligne, file de synchronisation.',
    admin: 'Astreinte, inscriptions, rôles et invitations, modération, maintenance.',
  };
  const ICO = {
    membre: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    pair: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    admin: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
  };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const svg = (n, c) => `<svg class="${c || 'xmsp-i'}" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${ICO[n] || ''}</svg>`;
  const clean = r => { const s = new Set(['membre', ...(Array.isArray(r) ? r : [])]); return ORDER.filter(x => s.has(x)); };
  const read = () => { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } };
  let mem = null, created = false;
  const state = () => mem || (mem = read() || (created = true, { roles: ['membre'], space: 'membre' }));
  const write = () => { try { localStorage.setItem(KEY, JSON.stringify(mem)); } catch (e) { /* stockage indisponible */ } };

  const X = window.XmSpace = {
    ORDER, DEST, LAB, SHORT, DESC, esc, svg,
    isNew: () => { state(); return created; },
    reset: () => { mem = { roles: ['membre'], space: 'membre' }; created = true; write(); },
    roles: () => clean(state().roles),
    space: () => state().space || 'membre',
    multi: () => X.roles().length > 1,
    has: r => X.roles().includes(r),
    setRoles: r => { state().roles = clean(r); if (!X.has(state().space)) state().space = 'membre'; write(); },
    setSpace: s => { state().space = s; write(); },
    /* Chaque page déclare son espace. En mode recette (menu ☰), le rôle manquant est ajouté au compte de démonstration. */
    enter: space => { const s = state(); const r = new Set(clean(s.roles)); r.add(space); s.roles = clean([...r]); s.space = space; write(); },
    /* Bouton de l'en-tête : espace courant + chevron ; n'existe que pour un compte à plusieurs rôles. */
    pill: (cur, cls) => (X.multi() ? `<button type="button" class="xmsp-pill ${cls || ''}" data-xmsp-open aria-haspopup="dialog" aria-label="Espace actuel : ${esc(LAB[cur])}. Changer d’espace">${svg(cur)}<span>${esc(SHORT[cur])}</span><svg class="xmsp-c" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></button>` : ''),
    /* Liste des espaces accessibles (écran de choix et feuille). */
    options: cur => X.roles().map(r => `<a class="xmsp-opt" href="${DEST[r]}" data-xmsp-go="${r}" ${r === cur ? 'aria-current="page"' : ''}><span class="xmsp-ic">${svg(r)}</span><span class="xmsp-tx"><b>${esc(LAB[r])}</b><small>${esc(DESC[r])}</small></span>${r === cur ? '<span class="xmsp-here">Ici</span>' : '<svg class="xmsp-c2" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>'}</a>`).join(''),
    /* Feuille « Choisir mon espace » dans l'écran du téléphone. */
    open: (host, cur) => {
      host = host || document.getElementById('app'); if (!host) return;
      cur = cur || X.space();
      const opener = document.activeElement;
      const el = document.createElement('div'); el.className = 'xmsp-ov';
      el.innerHTML = `<div class="xmsp-scrim" data-xmsp-close></div><div class="xmsp-sheet" role="dialog" aria-modal="true" aria-labelledby="xmspT"><div class="xmsp-grab" aria-hidden="true"></div>
        <h2 id="xmspT">Choisir mon espace</h2><p class="xmsp-p">Un seul compte, une seule connexion. Tes rôles : ${X.roles().map(r => `<b>${esc(LAB[r])}</b>`).join(', ')}.</p>
        <div class="xmsp-list">${X.options(cur)}</div><button type="button" class="xmsp-x" data-xmsp-close>Fermer</button></div>`;
      host.appendChild(el);
      const close = () => { document.removeEventListener('keydown', key, true); el.remove(); if (opener && opener.focus && document.contains(opener)) opener.focus({ preventScroll: true }); };
      const key = e => { if (e.key === 'Escape') { e.stopPropagation(); close(); } };
      document.addEventListener('keydown', key, true);
      el.addEventListener('click', e => { if (e.target.closest('[data-xmsp-close]')) close(); });
      const a = el.querySelector('.xmsp-opt:not([aria-current])') || el.querySelector('.xmsp-opt'); if (a) a.focus({ preventScroll: true });
    },
  };

  document.addEventListener('click', e => {
    const o = e.target.closest('[data-xmsp-open]'); if (o) { e.preventDefault(); X.open(document.getElementById('app'), o.dataset.xmspCur); return; }
    const g = e.target.closest('[data-xmsp-go]'); if (g) { X.setSpace(g.dataset.xmspGo); /* la navigation suit le lien */ }
  });

  const css = document.createElement('style'); css.id = 'xmspCss';
  css.textContent = `
.xmsp-pill { display: inline-flex; align-items: center; gap: 6px; min-height: 44px; padding: 0 10px 0 12px; border-radius: 999px; border: 1.5px solid var(--app-primary, #0433C3); background: var(--app-primary-subtle, #EBEFFA); color: var(--app-primary-subtle-text, #021C6B); font: 700 13px var(--xm-font-body, Arial, sans-serif); cursor: pointer; white-space: nowrap; }
.xmsp-pill:hover { background: var(--app-surface, #fff); }
.xmsp-pill .xmsp-i { width: 18px; height: 18px; } .xmsp-pill .xmsp-c { width: 16px; height: 16px; }
.xmsp-pill:focus-visible { outline: 3px solid var(--app-primary, #0433C3); outline-offset: 2px; }
.xmsp-ov { position: absolute; inset: 0; z-index: 60; }
.xmsp-scrim { position: absolute; inset: 0; background: rgb(10 12 28 / .6); }
.xmsp-sheet { position: absolute; left: 0; right: 0; bottom: 0; max-height: 92%; overflow-y: auto; background: var(--app-surface, #fff); color: var(--app-text, #1B1630); border-radius: 20px 20px 0 0; padding: 12px 16px calc(var(--safe-bottom, 34px) + 14px); display: grid; gap: 12px; font-family: var(--xm-font-body, Arial, sans-serif); }
.xmsp-grab { width: 40px; height: 4px; border-radius: 2px; background: var(--app-border, #E4DCE6); justify-self: center; }
.xmsp-sheet h2 { font: 700 19px/1.25 var(--xm-font-heading, Montserrat, sans-serif); margin: 0; }
.xmsp-p { margin: 0; font-size: 14px; color: var(--app-muted, #5B5670); }
.xmsp-list { display: grid; gap: 10px; }
.xmsp-opt { display: grid; grid-template-columns: 44px 1fr auto; align-items: center; gap: 12px; min-height: 64px; padding: 10px 12px; border: 1.5px solid var(--app-border, #E4DCE6); border-radius: 16px; color: inherit; text-decoration: none; background: var(--app-surface, #fff); }
.xmsp-opt[aria-current="page"] { border-color: var(--app-primary, #0433C3); background: var(--app-primary-subtle, #EBEFFA); }
.xmsp-opt:focus-visible { outline: 3px solid var(--app-primary, #0433C3); outline-offset: 2px; }
.xmsp-ic { width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center; background: var(--app-primary, #0433C3); color: var(--app-on-primary, #fff); }
.xmsp-ic .xmsp-i { width: 24px; height: 24px; }
.xmsp-tx { display: grid; gap: 2px; min-width: 0; } .xmsp-tx b { font-size: 16px; } .xmsp-tx small { font-size: 13px; color: var(--app-muted, #5B5670); line-height: 1.35; }
.xmsp-here { font-size: 12px; font-weight: 700; color: var(--app-primary-subtle-text, #021C6B); }
.xmsp-c2 { width: 20px; height: 20px; color: var(--app-muted, #5B5670); }
.xmsp-x { min-height: 48px; border-radius: 12px; border: 1px solid var(--app-border, #E4DCE6); background: transparent; color: inherit; font: 700 15px var(--xm-font-body, Arial, sans-serif); cursor: pointer; }
.xmsp-strip { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-bottom: -4px; }
`;
  document.head.appendChild(css);
})();
