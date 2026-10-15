<?php
/** Helpers admin (partie 1 : pilotage & données). */

function xa_status_alert() { return ['nouveau' => ['Nouveau', 'danger'], 'en_cours' => ['En cours', 'warning'], 'traite' => ['Traité', 'success']]; }
function xa_prio() { return ['critique' => ['Critique', 'danger'], 'haute' => ['Haute', 'warning'], 'normale' => ['Normale', 'secondary']]; }
function xa_types_alert() { return ['denonciation' => ['Dénonciation', 'bi-megaphone'], 'besoin' => ['Besoin', 'bi-life-preserver'], 'sos' => ['SOS', 'bi-geo-alt-fill']]; }
function xa_status_case() { return ['ouvert' => ['Ouvert', 'primary'], 'en_cours' => ['En cours', 'warning'], 'clos' => ['Clos', 'success'], 'archive' => ['Archivé', 'secondary']]; }
function xa_viol_label($id) { foreach (x_violence_categories() as $c) if ($c[0] === $id) return $c[1]; return $id; }
function xa_viol_map() { $m = []; foreach (x_violence_categories() as $c) $m[$c[0]] = $c[1]; return $m; }

function xa_badge($map, $key) {
    $m = $map[$key] ?? [$key, 'secondary'];
    $txt = in_array($m[1], ['warning'], true) ? 'text-dark' : '';
    return '<span class="badge bg-' . e($m[1]) . ' ' . $txt . '">' . e($m[0]) . '</span>';
}
function xa_annuaire_map() { $m = []; foreach (store_all('annuaire') as $a) $m[$a['id']] = $a; return $m; }
function xa_routed_label($id, $map = null) {
    $map = $map ?? xa_annuaire_map();
    return isset($map[$id]) ? $map[$id]['name'] . ' (' . $map[$id]['role'] . ')' : '—';
}
function xa_maps_link($lat, $lng) { return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng); }

function xa_count_by($rows, $key, $labels = null) {
    $o = [];
    foreach ($rows as $r) { $k = $r[$key] ?? '—'; $o[$k] = ($o[$k] ?? 0) + 1; }
    arsort($o);
    return $o;
}
/** 12 derniers mois : [ 'YYYY-MM' => 'janv. 26' ] */
function xa_last_months($n = 12) {
    $mois = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $o = []; $t = strtotime(date('Y-m-01'));
    for ($i = $n - 1; $i >= 0; $i--) { $d = strtotime("-$i month", $t); $o[date('Y-m', $d)] = $mois[(int)date('n', $d) - 1] . ' ' . date('y', $d); }
    return $o;
}
function xa_csv($filename, $headers, $rows) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $r) fputcsv($out, $r, ';');
    fclose($out); exit;
}
function xa_redirect($page, $qs = '') { header('Location: ' . $page . ($qs ? '?' . $qs : '')); exit; }
function xa_flash($type, $msg) { $_SESSION['flash'] = ['type' => $type, 'msg' => $msg]; }
/** Intersection sûre des paramètres GET */
function xa_get($k, $d = '') { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function xa_qs($over = []) { $q = array_merge($_GET, $over); foreach ($q as $k => $v) if ($v === '' || $v === null) unset($q[$k]); return http_build_query($q); }
/** Pseudonymisation d'un code dossier */
function xa_next_case_code() {
    $max = 0;
    foreach (store_all('cases') as $c) if (preg_match('/^VBG-(\d+)$/', $c['code'], $m)) $max = max($max, (int)$m[1]);
    return sprintf('VBG-%04d', $max + 1);
}
function xa_commune_ok($zone, $commune) { $z = x_zones(); return isset($z[$zone]) && in_array($commune, $z[$zone], true); }
function xa_date_ok($d) { $t = strtotime($d); return $t && date('Y-m-d', $t) === $d; }
