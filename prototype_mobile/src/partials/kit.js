/* Noyau commun des maquettes (Espace pair, Admin mobile) : routage par ancre, thème, réseau, feuilles, notifications.
   Dépend de l'objet de chaînes T défini en tête du fichier de la maquette. */
(function () {
  'use strict';
  const Kit = window.Kit = {};
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const t = (key, v = {}) => {
    let o = key.split('.').reduce((a, k) => (a == null ? a : a[k]), T);
    if (o == null) return key;
    if (typeof o === 'object') o = v.count === 0 && o.zero !== undefined ? o.zero : v.count === 1 ? o.one : o.other;
    return String(o).replace(/:(\w+)/g, (m, k) => (v[k] !== undefined ? v[k] : m));
  };
  const P = {
    home: '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
    bell: '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
    alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
    folder: '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
    'file-plus': '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
    sync: '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
    clock: '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
    'wifi-off': '<line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
    wifi: '<path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/>',
    search: '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    back: '<polyline points="15 18 9 12 15 6"/>',
    next: '<polyline points="9 18 15 12 9 6"/>',
    check: '<polyline points="20 6 9 17 4 12"/>',
    x: '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    pin: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
    users: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'user-check': '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/>',
    tool: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    send: '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>',
    cloud: '<polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/>',
    info: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    ext: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    dash: '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    lock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    note: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    book: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    list: '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
    swap: '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
    plus: '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
  };
  const ic = (n, c = '') => `<svg class="ico ${c}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${P[n] || ''}</svg>`;
  Object.assign(Kit, { esc, t, ic });

  const q = new URLSearchParams(location.search);
  const hq = new URLSearchParams(location.hash.split('?')[1] || '');
  ['theme', 'net'].forEach(k => { if (hq.get(k)) q.set(k, hq.get(k)); });
  const S = Kit.S = {
    theme: q.get('theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
    net: q.get('net') === 'off' ? 'off' : 'on',
    route: { name: '', args: [], q: {} },
  };
  let cfg, app, frame, ov, live, lastKey = '', toastTimer, current = null;
  const $ = (s, r = document) => r.querySelector(s);

  function parse() {
    const h = location.hash.replace(/^#\/?/, '');
    const [p, qs = ''] = h.split('?');
    const parts = p.split('/').filter(Boolean).map(decodeURIComponent);
    return { name: parts[0] || cfg.home, args: parts.slice(1), q: Object.fromEntries(new URLSearchParams(qs)) };
  }

  Kit.setTheme = th => {
    S.theme = th; app.dataset.theme = th; document.documentElement.dataset.pageTheme = th;
    document.querySelectorAll('[data-theme-set]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.themeSet === th)));
  };
  Kit.setNet = (n, silent) => {
    const was = S.net; S.net = n;
    document.querySelectorAll('[data-net-set]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.netSet === n)));
    if (!silent && was !== n) { if (cfg.onNet) cfg.onNet(n, was); Kit.render(); Kit.say(n === 'off' ? t('net.went_off') : t('net.went_on')); }
  };
  Kit.say = msg => { live.innerHTML = `<div class="toast" role="status">${esc(msg)}</div>`; clearTimeout(toastTimer); toastTimer = setTimeout(() => { live.innerHTML = ''; }, 4200); };
  Kit.toast = Kit.say;
  Kit.go = h => { location.hash = h; };

  Kit.render = (opts = {}) => {
    const r = S.route; const d = cfg.screen(r) || cfg.screen({ name: cfg.home, args: [], q: {} });
    const key = r.name + '/' + r.args.join('/');
    const old = $('.pane', frame); const keep = !opts.top && key === lastKey && old ? old.scrollTop : 0;
    const tabs = d.noTabs ? '' : `<nav class="tb" aria-label="${esc(t('nav.label'))}">${cfg.tabs.map(tb => `<a href="#/${tb.href}" ${d.tab === tb.id ? 'aria-current="page"' : ''} class="${tb.sos ? 'sos' : ''}">${tb.sos ? `<span class="b" aria-hidden="true">SOS</span><span class="vh">${esc(tb.label)}</span>` : `${ic(tb.icon)}<span>${esc(tb.label)}</span>`}${tb.count && tb.count() ? `<span class="cnt" aria-hidden="true">${esc(tb.count())}</span>` : ''}</a>`).join('')}</nav>`;
    const chip = cfg.chip && !d.back ? cfg.chip(r) : '';
    const banner = cfg.banner ? cfg.banner() : '';
    frame.innerHTML = `<header class="hdr">${d.back ? `<a class="icon-btn" href="#/${d.back}" aria-label="${esc(d.backLabel || t('nav.back'))}">${ic('back')}</a>` : ''}<h1 class="t" id="ttl" tabindex="-1">${d.title}</h1>${chip}</header>${banner}<main class="pane" id="pane">${d.html}</main>${tabs}`;
    const pane = $('.pane', frame); pane.scrollTop = keep;
    if (key !== lastKey && !opts.noFocus) { const tt = $('#ttl', frame); if (tt) tt.focus({ preventScroll: true }); }
    lastKey = key;
    document.title = (d.plain || '') + (d.plain ? ' · ' : '') + cfg.title;
    const links = [...document.querySelectorAll('.rail a')]; const cur = location.hash.replace(/^#\//, '');
    let hit = links.find(a => a.getAttribute('href') === '#/' + cur) || links.find(a => a.dataset.name === r.name);
    links.forEach(a => a.toggleAttribute('aria-current', a === hit)); if (hit) hit.setAttribute('aria-current', 'page');
    const cap = $('#caption'); if (cap) cap.innerHTML = d.caption || '';
    if (d.mount) d.mount(pane);
  };

  Kit.sheet = (html, o = {}) => {
    const opener = document.activeElement;
    ov.innerHTML = `<div class="scrim" data-close></div><div class="sheet" role="dialog" aria-modal="true" aria-label="${esc(o.label || '')}"><div class="grab" aria-hidden="true"></div>${html}</div>`;
    const sh = $('.sheet', ov);
    const close = () => { current = null; ov.innerHTML = ''; document.removeEventListener('keydown', key, true); if (opener && opener.focus && document.contains(opener)) opener.focus({ preventScroll: true }); if (o.onClose) o.onClose(); };
    const focusables = () => [...sh.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])')];
    const key = e => {
      if (e.key === 'Escape') { e.stopPropagation(); close(); }
      if (e.key === 'Tab') { const f = focusables(); if (!f.length) return; const a = f[0], z = f[f.length - 1]; if (e.shiftKey && document.activeElement === a) { e.preventDefault(); z.focus(); } else if (!e.shiftKey && document.activeElement === z) { e.preventDefault(); a.focus(); } }
    };
    document.addEventListener('keydown', key, true); current = close;
    ov.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', close));
    const f = focusables().find(x => !x.matches('[data-close]')) || sh; (o.focus ? $(o.focus, sh) || f : f).focus({ preventScroll: true });
    if (o.onMount) o.onMount(sh, close);
    return close;
  };
  Kit.confirm = o => Kit.sheet(`<h2>${esc(o.title)}</h2><p style="margin:0">${o.text || ''}</p>${o.body || ''}<div class="formnav"><button class="btn btn-outline" data-close>${esc(o.cancel || t('common.cancel'))}</button><button class="btn btn-primary" id="okBtn" ${o.disabled ? 'disabled' : ''}>${esc(o.ok)}</button></div>`, {
    label: o.title,
    focus: o.focus,
    onMount: (sh, close) => { o.mount && o.mount(sh); $('#okBtn', sh).addEventListener('click', () => { if (o.validate && o.validate(sh) === false) return; close(); o.onOk && o.onOk(sh); }); },
  });

  Kit.start = c => {
    cfg = c;
    const rail = c.rail.map(g => `<div class="rail-group"><h2>${esc(g.title)}</h2>${g.items.map(i => `<a href="#/${i.href}" data-route="${esc(i.route || i.href.split('?')[0])}" ${i.exact ? 'data-exact="1"' : ''} data-name="${esc(i.href.split('?')[0].split('/')[0])}">${esc(i.label)}</a>`).join('')}</div>`).join('');
    const opts = c.rail.map(g => `<optgroup label="${esc(g.title)}">${g.items.map(i => `<option value="${esc(i.href)}">${esc(i.label)}</option>`).join('')}</optgroup>`).join('');
    document.body.innerHTML = `<a class="skip" href="#app">${esc(t('page.skip'))}</a>
<div class="sim">
  <header class="topbar">
    <div class="brand"><img class="logo" src="${c.logo}" alt="Xomiton"><div><h1>${esc(c.title)}</h1><div class="sub">${esc(c.sub)}</div></div></div>
    <label class="vh" for="railSel">${esc(t('page.screens'))}</label><select class="rail-select input" id="railSel" style="width:auto">${opts}</select>
    <div class="ctl"><span id="lblTheme">${esc(t('page.theme'))}</span><div class="seg" role="group" aria-labelledby="lblTheme"><button type="button" data-theme-set="light" aria-pressed="false">${esc(t('page.light'))}</button><button type="button" data-theme-set="dark" aria-pressed="false">${esc(t('page.dark'))}</button></div></div>
    <div class="ctl"><span id="lblNet">${esc(t('page.network'))}</span><div class="seg" role="group" aria-labelledby="lblNet"><button type="button" data-net-set="on" aria-pressed="false">${esc(t('page.online'))}</button><button type="button" data-net-set="off" aria-pressed="false">${esc(t('page.offline'))}</button></div></div>
  </header>
  <nav class="rail" aria-label="${esc(t('page.screens'))}">${rail}<p class="note">${esc(t('page.demo_note'))}</p></nav>
  <main class="stage">
    <div class="device-wrap" id="deviceWrap"><div class="device" data-model="iphone14"><div class="screen" id="screen">
      <div class="notch" aria-hidden="true"></div>
      <div class="statusbar" aria-hidden="true"><span>9:41</span><span class="icons"><svg viewBox="0 0 18 12"><rect x="0" y="8" width="3" height="4" rx="1"/><rect x="5" y="5.5" width="3" height="6.5" rx="1"/><rect x="10" y="3" width="3" height="9" rx="1"/><rect x="15" y="0" width="3" height="12" rx="1"/></svg><svg viewBox="0 0 26 12" style="width:26px"><rect x=".5" y=".5" width="22" height="11" rx="3" fill="none" stroke="currentColor" opacity=".5"/><rect x="2" y="2" width="16" height="8" rx="2"/><rect x="23.5" y="4" width="2" height="4" rx="1" opacity=".5"/></svg></span></div>
      <div class="app" id="app" data-theme="light"><div id="frame" style="display:flex;flex-direction:column;height:100%"></div><div id="ov"></div><div class="live" id="live" role="status" aria-live="polite"></div></div>
      <div class="homebar" aria-hidden="true"></div>
    </div></div></div>
    <p class="stage-caption" id="caption"></p>
  </main>
</div>`;
    app = $('#app'); frame = $('#frame'); ov = $('#ov'); live = $('#live');
    document.querySelectorAll('[data-theme-set]').forEach(b => b.addEventListener('click', () => Kit.setTheme(b.dataset.themeSet)));
    document.querySelectorAll('[data-net-set]').forEach(b => b.addEventListener('click', () => Kit.setNet(b.dataset.netSet)));
    $('#railSel').addEventListener('change', e => Kit.go('#/' + e.target.value));
    app.addEventListener('click', e => { const b = e.target.closest('[data-act]'); if (b && cfg.actions[b.dataset.act]) cfg.actions[b.dataset.act](b.dataset, b, e); });
    Kit.setTheme(S.theme); Kit.setNet(S.net, true);
    const fit = () => {
      const w = $('#deviceWrap'), stage = $('.stage'); if (!w || !stage) return;
      if (matchMedia('(max-width: 480px)').matches) { w.style.width = w.style.height = w.style.transform = ''; return; }
      const dev = $('.device'); const dw = dev.offsetWidth, dh = dev.offsetHeight;
      const k = Math.min(1, (stage.clientHeight - 56) / dh, (stage.clientWidth - 24) / dw);
      w.style.transform = `scale(${k})`; w.style.width = dw + 'px'; w.style.height = (dh * k) + 'px';
    };
    addEventListener('resize', fit);
    const onHash = () => {
      if (current) current();
      S.route = parse(); const r = S.route;
      if (r.q.theme && r.q.theme !== S.theme) Kit.setTheme(r.q.theme);
      if (r.q.net && r.q.net !== S.net) { const was = S.net; S.net = r.q.net; Kit.setNet(r.q.net, true); if (cfg.onNet) cfg.onNet(r.q.net, was, true); }
      const rs = $('#railSel'); const cur = location.hash.replace(/^#\//, ''); if ([...rs.options].some(o => o.value === cur)) rs.value = cur;
      if (cfg.onRoute) cfg.onRoute(r);
      Kit.render({ top: true }); fit();
    };
    addEventListener('hashchange', onHash);
    if (cfg.init) cfg.init();
    if (!location.hash) history.replaceState(null, '', '#/' + c.home);
    onHash();
    document.fonts && document.fonts.ready.then(fit);
  };
})();
