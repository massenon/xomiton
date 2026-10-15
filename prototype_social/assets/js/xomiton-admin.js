/* Xomiton – administration : tableaux paginés, notifications, plein écran, recherche globale. */
(function () {
  'use strict';
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));

  /* ---------- Tableaux : « Affichage de 1 à 10 sur 57 entrées » + Précédent / 1 … 11 / Suivant ---------- */
  const SIZES = [10, 25, 50, 100];
  const windowOf = (n, p) => {
    const set = [...new Set([1, 2, p - 1, p, p + 1, n - 1, n].filter(i => i >= 1 && i <= n))].sort((a, b) => a - b);
    const out = []; let prev = 0;
    set.forEach(i => { if (i - prev > 1) out.push('…'); out.push(i); prev = i; });
    return out;
  };
  function enhance(t) {
    if (t.dataset.xmDt || t.hasAttribute('data-xm-nopage') || t.closest('[data-xm-nopage], .modal, .d-none, .xm-print-only')) return;
    const tb = t.tBodies[0];
    if (!tb || !t.tHead) return;
    const rows = () => Array.from(tb.rows).filter(r => !(r.cells.length === 1 && r.cells[0].colSpan > 1));
    if (!rows().length) return;
    t.dataset.xmDt = '1';
    const host = t.closest('.xm-table-wrap') || t;
    const inCard = host.parentElement && host.parentElement.classList.contains('card');
    const bar = document.createElement('div');
    bar.className = 'xm-dt-bar d-flex flex-wrap justify-content-between align-items-center gap-2 ' + (inCard ? 'card-footer' : 'px-1 py-2');
    host.insertAdjacentElement('afterend', bar);
    let page = 1, per = 10;
    const render = () => {
      const all = rows(), total = all.length, n = Math.max(1, Math.ceil(total / per));
      page = Math.min(Math.max(1, page), n);
      all.forEach((r, i) => { r.hidden = !(i >= (page - 1) * per && i < page * per); });
      const from = total ? (page - 1) * per + 1 : 0, to = Math.min(total, page * per);
      const id = 'dt' + Math.random().toString(36).slice(2, 7);
      bar.innerHTML =
        '<div class="d-flex flex-wrap align-items-center gap-3 small text-secondary"><span role="status">Affichage de ' + from + ' à ' + to + ' sur ' + total + ' entrée' + (total > 1 ? 's' : '') + '</span>' +
        '<label class="d-flex align-items-center gap-1 mb-0" for="' + id + '">Afficher <select class="form-select form-select-sm w-auto" id="' + id + '">' +
        SIZES.map(s => '<option' + (s === per ? ' selected' : '') + '>' + s + '</option>').join('') + '</select> entrées</label></div>' +
        '<nav aria-label="Pagination du tableau"><ul class="pagination pagination-sm mb-0 flex-wrap xm-dt-pager">' +
        '<li class="page-item' + (page <= 1 ? ' disabled' : '') + '"><button type="button" class="page-link" data-p="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>Précédent</button></li>' +
        windowOf(n, page).map(i => i === '…' ? '<li class="page-item disabled"><span class="page-link">…</span></li>'
          : '<li class="page-item' + (i === page ? ' active' : '') + '"><button type="button" class="page-link" data-p="' + i + '"' + (i === page ? ' aria-current="page"' : '') + '>' + i + '</button></li>').join('') +
        '<li class="page-item' + (page >= n ? ' disabled' : '') + '"><button type="button" class="page-link" data-p="' + (page + 1) + '"' + (page >= n ? ' disabled' : '') + '>Suivant</button></li></ul></nav>';
      $('select', bar).addEventListener('change', e => { per = +e.target.value; page = 1; render(); $('select', bar).focus(); });
      $$('button[data-p]', bar).forEach(b => b.addEventListener('click', () => { page = +b.dataset.p; render(); const a = $('.page-item.active button', bar); if (a) a.focus(); }));
    };
    new MutationObserver(() => render()).observe(tb, { childList: true });
    render();
  }
  const scan = () => $$('.xm-admin table.table').forEach(enhance);
  let timer;
  new MutationObserver(() => { clearTimeout(timer); timer = setTimeout(scan, 80); }).observe(document.body, { childList: true, subtree: true });
  document.addEventListener('DOMContentLoaded', scan);
  if (document.readyState !== 'loading') scan();

  /* ---------- Notifications : Non lus / Lues / Toutes (état de lecture conservé sur l'appareil) ---------- */
  const KEY = 'xm_admin_notif_read';
  const loadRead = () => { try { return new Set(JSON.parse(localStorage.getItem(KEY) || '[]')); } catch (e) { return new Set(); } };
  const saveRead = s => { try { localStorage.setItem(KEY, JSON.stringify([...s])); } catch (e) { /* stockage indisponible */ } };
  const list = $('#xmNotifList');
  if (list) {
    let tab = 'unread';
    const items = () => $$('[data-notif-id]', list);
    const isRead = (a, set) => a.dataset.notifRead === '1' || set.has(a.dataset.notifId);
    const refresh = () => {
      const set = loadRead(); let unread = 0, shown = 0;
      items().forEach(a => {
        const r = isRead(a, set); if (!r) unread++;
        a.classList.toggle('xm-notif-unread', !r);
        const show = tab === 'all' || (tab === 'unread' ? !r : r);
        a.classList.toggle('d-none', !show); if (show) shown++;
      });
      $('#xmNotifEmpty').classList.toggle('d-none', shown > 0);
      const c = $('#xmNotifCount');
      if (c) { c.firstChild.nodeValue = unread; c.classList.toggle('d-none', unread === 0); }
    };
    $$('[data-notif-tab]').forEach(b => b.addEventListener('click', () => {
      tab = b.dataset.notifTab;
      $$('[data-notif-tab]').forEach(x => { const on = x === b; x.classList.toggle('active', on); x.setAttribute('aria-selected', on); });
      refresh();
    }));
    items().forEach(a => a.addEventListener('click', () => { const s = loadRead(); s.add(a.dataset.notifId); saveRead(s); }));
    $('#xmNotifReadAll').addEventListener('click', () => { const s = loadRead(); items().forEach(a => s.add(a.dataset.notifId)); saveRead(s); refresh(); });
    refresh();
  }

  /* ---------- Plein écran ---------- */
  const fs = $('#xmFullscreen');
  if (fs) {
    if (!document.documentElement.requestFullscreen) fs.classList.add('d-none');
    const sync = () => {
      const on = !!document.fullscreenElement;
      fs.innerHTML = '<i class="bi ' + (on ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen') + '" aria-hidden="true"></i>';
      fs.setAttribute('aria-label', on ? 'Quitter le plein écran' : 'Plein écran'); fs.title = fs.getAttribute('aria-label');
    };
    fs.addEventListener('click', () => { (document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()).catch(() => {}); });
    document.addEventListener('fullscreenchange', sync);
  }

  /* ---------- Recherche globale : Ctrl/⌘ + K ---------- */
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
      const i = $('#xmGlobalSearch');
      if (i && i.offsetParent !== null) { e.preventDefault(); i.focus(); i.select(); }
    }
  });
})();
