(function () {
  // Plan de sécurité de /urgences (P4-06 ; prototype urgences.php l.135-150). JavaScript seul, sans Blade ni
  // dépendance : inclus en ligne par public/urgences/plan (nonce CSP) et testé par resources/js/public/urgences-plan.test.js.
  // État { "0": true, … } sous data-key (clés 0 à 7, comme le prototype) ; stockage indisponible (navigation privée, quota) : cases non mémorisées.
  var fill = function (template, done, total) {
    return String(template || '').replace(':done', String(done)).replace(':total', String(total));
  };
  var init = function (root) {
    if (!root || root.getAttribute('data-plan-ready') === '1') return;
    root.setAttribute('data-plan-ready', '1');
    var key = root.getAttribute('data-key') || 'xm_plan_v1';
    var boxes = Array.prototype.slice.call(root.querySelectorAll('[data-plan-item]'));
    var count = root.querySelector('[data-plan-count]');
    var bar = root.querySelector('[data-plan-bar]');
    var live = root.querySelector('[data-plan-live]');
    var state = {};
    try {
      var saved = JSON.parse(window.localStorage.getItem(key) || '{}');
      if (saved && typeof saved === 'object' && !Array.isArray(saved)) state = saved;
    } catch (e) { state = {}; }
    var save = function () {
      try { window.localStorage.setItem(key, JSON.stringify(state)); } catch (e) { /* stockage indisponible */ }
    };
    var refresh = function (announce) {
      var done = boxes.filter(function (box) { return box.checked; }).length;
      var total = boxes.length;
      if (count) count.textContent = fill(root.getAttribute('data-progress'), done, total);
      if (bar) bar.style.width = (total ? Math.round(100 * done / total) : 0) + '%';
      if (live && announce) live.textContent = announce === true ? fill(root.getAttribute('data-live'), done, total) : announce;
    };
    boxes.forEach(function (box) {
      box.checked = state[box.getAttribute('data-plan-item')] === true;
      box.addEventListener('change', function () {
        state[box.getAttribute('data-plan-item')] = box.checked;
        save();
        refresh(true);
      });
    });
    var reset = root.querySelector('[data-plan-reset]');
    if (reset) {
      reset.addEventListener('click', function () {
        state = {};
        try { window.localStorage.removeItem(key); } catch (e) { /* stockage indisponible */ }
        boxes.forEach(function (box) { box.checked = false; });
        refresh(root.getAttribute('data-reset-done') || true);
      });
    }
    var print = root.querySelector('[data-plan-print]');
    if (print) print.addEventListener('click', function () { window.print(); });
    Array.prototype.forEach.call(root.querySelectorAll('[data-plan-tools], [data-plan-progress]'), function (el) { el.hidden = false; });
    refresh(false);
  };
  Array.prototype.forEach.call(document.querySelectorAll('[data-xm-safety-plan]'), init);
})();
