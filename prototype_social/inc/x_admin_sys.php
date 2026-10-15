<?php
require_once __DIR__ . '/x_admin_ui.php';
/** Aides communes pour les pages d'administration "plateforme & système" (annuaire, utilisateurs, ...). */

function xa_is_super() { $u = x_user(); return $u && ($u['role'] ?? '') === 'superadmin'; }

/** Redirige vers la page courante avec un message flash. */
function xa_back($type, $msg, $qs = '') {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    header('Location: ' . basename($_SERVER['SCRIPT_NAME']) . ($qs !== '' ? '?' . ltrim($qs, '?') : ''));
    exit;
}
/** Contrôle CSRF pour tout POST. */
function xa_guard() {
    if (!csrf_ok()) xa_back('danger', 'Session expirée, veuillez réessayer.');
}
function xa_post($k, $d = '') { return trim((string)($_POST[$k] ?? $d)); }

/** Envoie un CSV (UTF-8 avec BOM, séparateur point-virgule pour Excel FR) et termine. */
function xa_csv($filename, array $headers, array $rows) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, $headers, ';');
    foreach ($rows as $r) fputcsv($o, array_values($r), ';');
    fclose($o);
    exit;
}

function xa_status_badge($s) {
    $map = ['actif' => 'success', 'en_attente' => 'warning text-dark', 'suspendu' => 'danger', 'publié' => 'success', 'brouillon' => 'secondary',
        'déployée' => 'success', 'planifiée' => 'info', 'ouvert' => 'primary', 'clos' => 'secondary'];
    return '<span class="badge bg-' . ($map[$s] ?? 'secondary') . '">' . e(str_replace('_', ' ', $s)) . '</span>';
}

function xa_fr_date($d) { $t = is_numeric($d) ? (int)$d : strtotime($d); return $t ? date('d/m/Y', $t) : ''; }

/** Script commun : remplissage des modales (data-fill='{json}') + confirmations (data-confirm). */
function xa_common_js() {
    return '<script>window.XM=window.XM||{};XM.zones=XM.zones||' . json_encode(x_zones(), JSON_UNESCAPED_UNICODE) . ';</script>' . <<<'JS'
<script>
document.addEventListener('show.bs.modal', function (ev) {
  var m = ev.target, btn = ev.relatedTarget, f = m.querySelector('form[data-fill-form]');
  if (!f) return;
  f.reset();
  f.querySelectorAll('.is-invalid,.is-valid').forEach(function (x) { x.classList.remove('is-invalid', 'is-valid'); });
  f.querySelectorAll('.xm-validation-msg').forEach(function (x) { x.textContent = ''; });
  var data = {};
  try { data = btn && btn.dataset.fill ? JSON.parse(btn.dataset.fill) : {}; } catch (e) {}
  var t = m.querySelector('[data-title-new]');
  if (t) t.textContent = data.id ? t.dataset.titleEdit : t.dataset.titleNew;
  Object.keys(data).forEach(function (k) {
    var el = f.elements[k]; if (!el) return;
    if (el.type === 'checkbox') el.checked = !!data[k] && data[k] !== '0';
    else el.value = data[k];
  });
  if (!data.id && f.elements.id) f.elements.id.value = '';
  var z = f.querySelector('[data-zone-select]'); if (z) z.dispatchEvent(new Event('change', { bubbles: true }));
  if (data.commune) { var c = f.elements.commune; if (c) setTimeout(function () { c.value = data.commune; }, 30); }
});
document.addEventListener('submit', function (e) {
  var c = e.target.getAttribute && e.target.getAttribute('data-confirm');
  if (c && !confirm(c)) e.preventDefault();
});
</script>
JS;
}

/** Petite fonction d'affichage d'un en-tête de section (titre + actions). */
function xa_head($title, $sub = '', $actions = '') {
    return '<div class="d-flex flex-wrap align-items-center gap-2 mb-3"><div class="flex-grow-1"><h2 class="h5 fw-bold mb-0">' . e($title) . '</h2>' .
        ($sub ? '<div class="text-secondary small">' . e($sub) . '</div>' : '') . '</div>' . $actions . '</div>';
}

function xa_kpi($label, $value, $icon, $cls = '', $sub = '') {
    return '<div class="col-6 col-xl-3"><div class="card xm-stat ' . $cls . ' h-100"><div class="card-body py-3"><div class="d-flex justify-content-between align-items-start">' .
        '<div><div class="text-secondary small">' . e($label) . '</div><div class="kpi-value">' . $value . '</div>' . ($sub ? '<div class="small text-secondary">' . e($sub) . '</div>' : '') . '</div>' .
        '<i class="bi ' . $icon . ' fs-3 text-secondary"></i></div></div></div></div>';
}

/** Liste des départements -> options. */
function xa_zone_options($sel = '', $all = false) {
    $h = $all ? '<option value="">Tous les départements</option>' : '<option value="">—</option>';
    foreach (array_keys(x_zones()) as $z) $h .= '<option value="' . e($z) . '"' . ($z === $sel ? ' selected' : '') . '>' . e($z) . '</option>';
    return $h;
}
