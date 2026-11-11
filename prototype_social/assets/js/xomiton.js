/* Xomiton – comportements communs : hors connexion + synchro, validation, GPS, toasts, assistant. */
(function () {
  'use strict';
  const XM = window.XM = window.XM || {};
  const Q_KEY = 'xm_queue_v1';
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

  /* ---------- Toast ---------- */
  XM.toast = function (msg, type) {
    let wrap = $('#xmToastWrap');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.id = 'xmToastWrap';
      wrap.className = 'toast-container position-fixed top-0 end-0 p-3';
      wrap.style.zIndex = 2000;
      document.body.appendChild(wrap);
    }
    const el = document.createElement('div');
    el.className = 'toast align-items-center text-bg-' + (type || 'primary') + ' border-0';
    el.setAttribute('role', 'status');
    el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button></div>';
    el.querySelector('.toast-body').textContent = msg;
    wrap.appendChild(el);
    if (window.bootstrap) { const t = new bootstrap.Toast(el, { delay: 4500 }); t.show(); el.addEventListener('hidden.bs.toast', () => el.remove()); }
    else { setTimeout(() => el.remove(), 4500); }
  };

  /* ---------- File d'attente hors connexion ---------- */
  const readQ = () => { try { return JSON.parse(localStorage.getItem(Q_KEY) || '[]'); } catch (e) { return []; } };
  const writeQ = q => { try { localStorage.setItem(Q_KEY, JSON.stringify(q)); } catch (e) {} XM.refreshSyncUI && XM.refreshSyncUI(); };
  XM.queue = {
    all: readQ,
    add(type, payload) {
      const q = readQ();
      q.push({ id: Date.now() + '-' + Math.random().toString(36).slice(2, 7), type, payload, at: new Date().toISOString(), tries: 0 });
      writeQ(q);
    },
    clear() { writeQ([]); }
  };

  XM.sync = async function (silent) {
    const q = readQ();
    if (!q.length) { if (!silent) XM.toast('Rien à synchroniser.', 'secondary'); return { sent: 0, left: 0 }; }
    if (!navigator.onLine) { if (!silent) XM.toast('Toujours hors connexion.', 'warning'); return { sent: 0, left: q.length }; }
    let sent = 0; const left = [];
    for (const item of q) {
      try {
        const r = await fetch(XM.base + 'api.php?action=sync', {
          method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'xmlhttprequest' },
          body: JSON.stringify({ item, csrf: XM.csrf }), credentials: 'same-origin'
        });
        const j = await r.json();
        if (j.ok) sent++; else { item.tries++; left.push(item); }
      } catch (e) { item.tries++; left.push(item); }
    }
    writeQ(left);
    XM.toast(sent + ' élément(s) synchronisé(s) de façon sécurisée' + (left.length ? ' — ' + left.length + ' en attente' : ''), left.length ? 'warning' : 'success');
    document.dispatchEvent(new CustomEvent('xm:synced', { detail: { sent, left: left.length } }));
    return { sent, left: left.length };
  };

  XM.refreshSyncUI = function () {
    const n = readQ().length;
    $$('[data-xm-queue-count]').forEach(el => el.textContent = n);
    $$('[data-xm-queue-badge]').forEach(el => { el.classList.toggle('d-none', n === 0); });
  };

  function setOnlineState() {
    document.body.classList.toggle('xm-is-offline', !navigator.onLine);
    $$('[data-xm-online-state]').forEach(el => {
      el.textContent = navigator.onLine ? 'En ligne' : 'Hors connexion';
      el.className = 'badge ' + (navigator.onLine ? 'bg-success' : 'bg-warning text-dark');
    });
    XM.refreshSyncUI();
  }
  window.addEventListener('online', () => { setOnlineState(); XM.toast('Connexion rétablie — synchronisation…', 'success'); XM.sync(true); });
  window.addEventListener('offline', () => { setOnlineState(); XM.toast('Vous êtes hors connexion. Vos saisies seront conservées.', 'warning'); });

  /* ---------- Géolocalisation ---------- */
  XM.getPosition = function () {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) return reject(new Error('Géolocalisation indisponible'));
      navigator.geolocation.getCurrentPosition(
        p => resolve({ lat: +p.coords.latitude.toFixed(6), lng: +p.coords.longitude.toFixed(6), acc: Math.round(p.coords.accuracy) }),
        err => reject(err), { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
    });
  };

  /* ---------- Règles de validation internes ---------- */
  const DEFAULT_RULES = {
    required: { label: 'Champ obligatoire', test: v => String(v).trim() !== '', msg: 'Ce champ est obligatoire.' },
    phone_bj: { label: 'Téléphone béninois (+229 01 XX XX XX XX)', test: v => v === '' || /^(\+229|00229)?\s?0?1\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}$/.test(v.trim()) || /^(\+229)?\s?\d{8,10}$/.test(v.replace(/\s/g, '')), msg: 'Numéro invalide (ex. +229 01 97 38 88 47).' },
    email: { label: 'Adresse e-mail valide', test: v => v === '' || /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v), msg: 'Adresse e-mail invalide.' },
    date_not_future: { label: 'Date non postérieure à aujourd’hui', test: v => v === '' || new Date(v) <= new Date(), msg: 'La date ne peut pas être dans le futur.' },
    age_range: { label: 'Âge entre 10 et 99 ans', test: v => v === '' || (+v >= 10 && +v <= 99), msg: 'L’âge doit être compris entre 10 et 99.' },
    min_len_20: { label: 'Description d’au moins 20 caractères', test: v => v === '' || v.trim().length >= 20, msg: 'Décrivez avec au moins 20 caractères.' },
    positive_int: { label: 'Entier positif', test: v => v === '' || /^\d+$/.test(v), msg: 'Entier positif attendu.' },
    commune_in_zone: { label: 'Commune cohérente avec le département', test: () => true, msg: 'Commune incohérente avec le département.' },
    case_code: { label: 'Code dossier VBG-0000', test: v => v === '' || /^VBG-\d{4}$/.test(v), msg: 'Format attendu : VBG-0000.' },
    password_strong: { label: 'Mot de passe fort (8+, majuscule, chiffre)', test: v => v === '' || (v.length >= 8 && /[A-Z]/.test(v) && /\d/.test(v)), msg: '8 caractères min., une majuscule et un chiffre.' }
  };
  XM.rules = DEFAULT_RULES;
  XM.disabledRules = (() => { try { return JSON.parse(localStorage.getItem('xm_rules_off') || '[]'); } catch (e) { return []; } })();

  XM.validateField = function (el) {
    const rules = (el.dataset.rule || '').split(/[\s,|]+/).filter(Boolean);
    if (el.required && !rules.includes('required')) rules.unshift('required');
    let err = '';
    for (const r of rules) {
      const def = XM.rules[r]; if (!def || XM.disabledRules.includes(r)) continue;
      if (!def.test(el.value || '')) { err = def.msg; break; }
    }
    let fb = el.parentElement.querySelector('.xm-validation-msg');
    if (!fb) { fb = document.createElement('div'); fb.className = 'xm-validation-msg'; el.insertAdjacentElement('afterend', fb); }
    fb.textContent = err;
    el.classList.toggle('is-invalid', !!err);
    el.classList.toggle('is-valid', !err && (el.value || '') !== '' && rules.length > 0);
    return !err;
  };
  XM.validateForm = function (form) {
    let ok = true;
    $$('[data-rule], [required]', form).forEach(el => { if (!XM.validateField(el)) ok = false; });
    if (!ok) { const f = $('.is-invalid', form); f && f.focus(); }
    return ok;
  };
  document.addEventListener('blur', e => { if (e.target.matches && e.target.matches('[data-rule]')) XM.validateField(e.target); }, true);
  document.addEventListener('input', e => { if (e.target.matches && e.target.matches('.is-invalid[data-rule]')) XM.validateField(e.target); });

  /* Commune ↔ département */
  document.addEventListener('change', e => {
    const dep = e.target.closest && e.target.closest('[data-zone-select]');
    if (!dep || !XM.zones) return;
    const target = $(dep.dataset.zoneSelect);
    if (!target) return;
    target.innerHTML = '<option value="">—</option>' + (XM.zones[dep.value] || []).map(c => '<option>' + c + '</option>').join('');
  });

  /* Formulaires : validation + file d'attente hors ligne */
  document.addEventListener('submit', async function (e) {
    const f = e.target;
    if (!(f instanceof HTMLFormElement)) return;
    if (f.hasAttribute('data-xm-validate') && !XM.validateForm(f)) { e.preventDefault(); XM.toast('Veuillez corriger les champs signalés.', 'danger'); return; }
    const kind = f.dataset.xmOffline;
    if (kind && !navigator.onLine) {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(f).entries());
      delete data._csrf;
      XM.queue.add(kind, data);
      XM.toast('Enregistré hors connexion — synchronisation automatique dès le retour du réseau.', 'warning');
      f.reset();
      document.dispatchEvent(new CustomEvent('xm:queued', { detail: { kind } }));
    }
  });

  /* ---------- Assistant (démonstration d'un LLM spécialisé VBG / sexualité) ---------- */
  XM.assistant = {
    kb: [
      { k: /(danger|urgence|menace|tue|frapp|sos|aide vite|je ne suis pas en s[eé]curit)/i, a: "Si vous êtes en danger immédiat, appuyez sur le bouton rouge SOS : votre position est envoyée aux personnes ciblées de l’annuaire. Éloignez-vous de la personne dangereuse si vous le pouvez et rejoignez un lieu public ou une personne de confiance. Voulez-vous que je vous affiche les contacts d’urgence ?", cta: ['SOS', 'sos.php'] },
      { k: /(viol|agress|attouch|forc[eé]e?)/i, a: "Je suis désolé·e que vous viviez cela. Ce n’est pas votre faute. Quelques gestes utiles : ne pas vous laver ni changer de vêtements si possible, consulter un centre de santé dans les 72 h (prophylaxie post-exposition PEP et contraception d’urgence), et noter ce dont vous vous souvenez. Vous choisissez ensuite de porter plainte ou non — un·e juriste de l’annuaire peut vous accompagner.", cta: ['Voir l’annuaire', 'annuaire.php'] },
      { k: /(plainte|police|justice|loi|droit|tribunal)/i, a: "Au Bénin, la loi n°2011-26 du 9 janvier 2012 réprime les violences faites aux femmes (harcèlement sexuel, violences conjugales, viol, mariage forcé). Vous pouvez porter plainte auprès d’un commissariat/gendarmerie, du procureur ou de l’OCPM pour les mineur·es. Xomiton propose un guide « Porter plainte, étape par étape » et un accompagnement juridique gratuit.", cta: ['Lois et codes', 'lois-benin.php'] },
      { k: /(lgbt|lbtq|lesbi|bi\b|trans|homophob|outing|orientation)/i, a: "Vous avez le droit d’être en sécurité, quelle que soit votre orientation ou identité. En cas de chantage à l’outing ou de menaces, conservez des captures d’écran, ne répondez pas à la provocation et utilisez le signalement anonyme. Je peux aussi vous orienter vers un·e psychologue formé·e à l’accueil des personnes LBTQ+.", cta: ['Signalement anonyme', 'signalement.php'] },
      { k: /(contracep|pilule|implant|grossesse|enceinte|pr[eé]servatif|capote)/i, a: "Plusieurs méthodes existent : préservatifs (protègent aussi des IST), pilule, implant, DIU, injectable, contraception d’urgence (à prendre le plus tôt possible, idéalement dans les 72 h). Le choix dépend de votre santé et de vos besoins : un·e professionnel·le de santé peut vous conseiller gratuitement. Voir le guide complet dans les thématiques « Éducation complète à la sexualité ».", cta: ['Thématiques', 'thematiques.php'] },
      { k: /(vih|ist\b|sida|d[eé]pistage|test)/i, a: "Le dépistage VIH/IST est confidentiel et gratuit dans de nombreux centres partenaires. Après une prise de risque, la PEP doit débuter dans les 72 h. Les traitements antirétroviraux permettent de vivre longtemps et en bonne santé. Je peux vous aider à trouver un centre près de chez vous.", cta: ['Annuaire', 'annuaire.php'] },
      { k: /(r[eè]gles|menstru|douleur)/i, a: "Les douleurs de règles sont fréquentes mais ne doivent pas être ignorées si elles sont invalidantes. Chaleur sur le ventre, mouvement doux, tisanes peuvent soulager. Consultez si les douleurs vous empêchent de vivre normalement, ou si les saignements sont très abondants.", cta: ['Hygiène menstruelle', 'thematiques.php'] },
      { k: /(angoisse|stress|d[eé]prim|triste|suicid|panique|anxi)/i, a: "Merci de m’en parler. Essayons une respiration carrée : inspirez 4 s, retenez 4 s, expirez 4 s, retenez 4 s — répétez 5 fois. Si vous pensez à vous faire du mal, parlez immédiatement à une personne de confiance ou appelez un contact d’urgence : vous n’êtes pas seul·e. Un·e psychologue de l’annuaire peut vous rappeler.", cta: ['Contacts d’urgence', 'urgences.php'] },
      { k: /(harc[eè]l|cyber|photo|chantage|menace en ligne)/i, a: "Ne répondez pas, ne payez pas, faites des captures d’écran avec date/heure, bloquez puis signalez le compte à la plateforme. Vous pouvez nous transmettre un signalement anonyme ; un·e juriste vous indiquera comment faire retirer les contenus.", cta: ['Signaler', 'signalement.php'] },
      { k: /(consentement|consent)/i, a: "Le consentement doit être libre, éclairé, enthousiaste, spécifique et réversible : on peut changer d’avis à tout moment. Le silence, la peur ou l’alcool ne valent jamais consentement." }
    ],
    fallback: "Je suis un assistant spécialisé sur les violences basées sur le genre, la santé sexuelle et les droits. Posez-moi votre question en quelques mots (ex. « comment porter plainte ? », « contraception d’urgence », « chantage à l’outing »). Je ne remplace pas un·e professionnel·le : en cas de danger, utilisez SOS.",
    reply(text) {
      for (const e of this.kb) if (e.k.test(text)) return { a: e.a, cta: e.cta };
      return { a: this.fallback };
    }
  };

  /* ---------- Service worker (mode hors connexion) ---------- */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', () => navigator.serviceWorker.register(XM.base + 'sw.js').catch(() => {}));
  }

  /* ---------- Cartes retournables (boîtes à images) ---------- */
  document.addEventListener('click', e => { const f = e.target.closest && e.target.closest('.xm-flip'); if (f) f.classList.toggle('flipped'); });

  document.addEventListener('DOMContentLoaded', () => { setOnlineState(); XM.refreshSyncUI(); if (navigator.onLine && readQ().length) XM.sync(true); });
})();

/* « Charger plus » : récupère la page suivante sans recharger (repli : lien classique). */
document.addEventListener('click', async (ev) => {
	const a = ev.target.closest('[data-xm-more] a');
	if (!a || ev.metaKey || ev.ctrlKey || ev.shiftKey) return;
	const list = document.getElementById('liste');
	if (!list) return;
	ev.preventDefault();
	a.setAttribute('aria-busy', 'true');
	try {
		const html = await (await fetch(a.href, { credentials: 'same-origin' })).text();
		const doc = new DOMParser().parseFromString(html, 'text/html');
		const nl = doc.getElementById('liste');
		if (!nl) { location.href = a.href; return; }
		const before = list.children.length;
		list.innerHTML = nl.innerHTML;
		const nb = doc.querySelector('[data-xm-more]');
		const ob = document.querySelector('[data-xm-more]');
		if (ob) { nb ? ob.replaceWith(nb) : ob.remove(); }
		history.replaceState(null, '', a.href.split('#')[0]);
		const first = list.children[before];
		if (first) { const f = first.querySelector('a,button'); if (f) f.focus({ preventScroll: true }); }
	} catch (e) { location.href = a.href; }
});

/* ---------- Sondages : création (réponses dynamiques, aperçu image) et vote sans rechargement ---------- */
(function () {
	const renumber = (box) => {
		const rows = box.querySelectorAll('.xm-poll-option-row');
		rows.forEach((r, i) => {
			r.querySelector('.input-group-text').textContent = i + 1;
			const inp = r.querySelector('input'); inp.placeholder = 'Réponse ' + (i + 1); inp.setAttribute('aria-label', 'Réponse ' + (i + 1)); inp.required = i < 2;
			const rm = r.querySelector('[data-poll-remove]'); rm.disabled = rows.length <= 2; rm.setAttribute('aria-label', 'Retirer la réponse ' + (i + 1));
		});
		const add = box.parentElement.querySelector('[data-poll-add]'); if (add) add.disabled = rows.length >= 6;
	};
	document.addEventListener('click', (e) => {
		const add = e.target.closest('[data-poll-add]');
		if (add) {
			const box = add.parentElement.querySelector('[data-poll-options]'), rows = box.querySelectorAll('.xm-poll-option-row');
			if (rows.length >= 6) return;
			const row = rows[rows.length - 1].cloneNode(true); row.querySelector('input').value = ''; box.appendChild(row); renumber(box); row.querySelector('input').focus();
			return;
		}
		const rm = e.target.closest('[data-poll-remove]');
		if (rm) { const box = rm.closest('[data-poll-options]'); if (box.children.length > 2) { rm.closest('.xm-poll-option-row').remove(); renumber(box); } }
	});
	document.addEventListener('input', (e) => {
		const t = e.target, c = t.id && document.querySelector('[data-count-for="' + t.id + '"]');
		if (c) c.textContent = t.value.length + ' / ' + t.maxLength;
		const pf = t.closest && t.closest('[data-poll-create].was-validated');
		if (pf && t.name === 'options[]') {
			const vals = [...pf.querySelectorAll('[name="options[]"]')].map(i => i.value.trim().toLowerCase()).filter(Boolean);
			pf.querySelector('[data-poll-options-error]').classList.toggle('d-none', vals.length >= 2 && new Set(vals).size === vals.length);
		}
	});
	document.addEventListener('change', (e) => {
		const t = e.target; if (!t.matches || !t.matches('[data-img-preview]')) return;
		const img = document.querySelector(t.dataset.imgPreview), f = t.files && t.files[0];
		if (!img) return;
		if (f && f.size > 5 * 1024 * 1024) { t.value = ''; XM.toast && XM.toast('Image trop lourde (5 Mo maximum).', 'danger'); }
		if (t.files && t.files[0]) { img.src = URL.createObjectURL(t.files[0]); img.classList.remove('d-none'); } else { img.classList.add('d-none'); img.removeAttribute('src'); }
	});
	document.addEventListener('submit', async (e) => {
		const f = e.target;
		if (f.matches && f.matches('[data-poll-create]')) {
			const vals = [...f.querySelectorAll('[name="options[]"]')].map(i => i.value.trim().toLowerCase()).filter(Boolean);
			const optOk = vals.length >= 2 && new Set(vals).size === vals.length;
			f.querySelector('[data-poll-options-error]').classList.toggle('d-none', optOk);
			if (!f.checkValidity() || !optOk) { e.preventDefault(); f.classList.add('was-validated'); const inv = f.querySelector(':invalid'); if (inv) inv.focus(); }
			return;
		}
		if (!f.matches || !f.matches('[data-poll-form]')) return;
		e.preventDefault();
		if (!f.querySelector('[name=undo]') && !f.querySelector('[name="choice[]"]:checked')) { XM.toast && XM.toast('Choisissez au moins une réponse.', 'warning'); return; }
		const btn = f.querySelector('button[type=submit]'); if (btn) btn.disabled = true;
		try {
			const r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
			const d = await r.json();
			if (d.html) { const box = f.closest('[data-poll]'), tmp = document.createElement('div'); tmp.innerHTML = d.html; box.replaceWith(tmp.firstElementChild); }
			XM.toast && XM.toast(d.message, d.ok ? 'success' : 'danger');
		} catch (err) { f.submit(); }
	});
	document.addEventListener('hidden.bs.modal', (e) => {
		const f = e.target.querySelector('[data-poll-create]'); if (!f) return;
		f.reset(); f.classList.remove('was-validated');
		const box = f.querySelector('[data-poll-options]'); [...box.children].slice(2).forEach(r => r.remove()); renumber(box);
		const img = f.querySelector('#pollPhotoPreview'); if (img) img.classList.add('d-none');
	});
})();
