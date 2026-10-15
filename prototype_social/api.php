<?php
/** API JSON du prototype : synchronisation hors ligne, alertes. */
require __DIR__ . '/inc/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];

function api_ok($d = []) { echo json_encode(['ok' => true] + $d, JSON_UNESCAPED_UNICODE); exit; }
function api_err($m, $c = 400) { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m], JSON_UNESCAPED_UNICODE); exit; }

/** Route une alerte vers la personne de l'annuaire la plus pertinente (rôle selon besoin, puis zone). */
function route_alert(array $a) {
    $ann = array_filter(store_all('annuaire'), fn($r) => !empty($r['active']));
    $wantRole = ['soutien_juridique' => 'Juriste', 'soutien_psychologique' => 'Psychologue', 'soutien_medical' => 'Gynécologue'];
    $target = null;
    foreach ($a['needs'] ?? [] as $n) {
        if (isset($wantRole[$n])) {
            foreach ($ann as $r) if ($r['role'] === $wantRole[$n] && ($r['zone'] ?? '') === ($a['zone'] ?? '')) { $target = $r; break 2; }
        }
    }
    if (!$target) foreach ($ann as $r) if ($r['role'] === 'Point focal' && ($r['zone'] ?? '') === ($a['zone'] ?? '')) { $target = $r; break; }
    if (!$target) foreach ($ann as $r) if ($r['role'] === 'Point focal') { $target = $r; break; }
    if (!$target) foreach ($ann as $r) { $target = $r; break; }
    return $target ? (int)$target['id'] : null;
}
function save_alert(array $d, $source = 'web') {
    $type = in_array($d['type'] ?? '', ['denonciation', 'besoin', 'sos'], true) ? $d['type'] : 'denonciation';
    $needs = $d['needs'] ?? [];
    if (is_string($needs)) $needs = array_filter(explode(',', $needs));
    $anon = !empty($d['anonymous']) || !x_user();
    $row = [
        'type' => $type, 'anonymous' => $anon,
        'pseudo' => $anon ? '' : (x_user()['pseudo'] ?? ($d['pseudo'] ?? '')),
        'category' => $d['category'] ?? '', 'description' => mb_substr(trim($d['description'] ?? ''), 0, 4000),
        'needs' => array_values($needs), 'zone' => $d['zone'] ?? '', 'commune' => $d['commune'] ?? '',
        'lat' => isset($d['lat']) && $d['lat'] !== '' ? (float)$d['lat'] : null, 'lng' => isset($d['lng']) && $d['lng'] !== '' ? (float)$d['lng'] : null,
        'contact' => $anon ? '' : ($d['contact'] ?? ''), 'status' => 'nouveau', 'priority' => $type === 'sos' ? 'critique' : ($d['priority'] ?? 'normale'),
        'created' => time(), 'source' => $source,
    ];
    $row['routed_to'] = route_alert($row);
    return store_append('alertes', $row);
}

switch ($action) {
    case 'sync':
        $item = $in['item'] ?? null;
        if (!$item || empty($item['type'])) api_err('Élément invalide');
        $p = $item['payload'] ?? [];
        if (in_array($item['type'], ['signalement', 'sos'], true)) {
            if ($item['type'] === 'sos') $p['type'] = 'sos';
            $r = save_alert($p, 'sync-offline'); x_log('Synchronisation', 'Alerte #' . $r['id']);
            api_ok(['id' => $r['id']]);
        }
        if ($item['type'] === 'collecte') {
            $rows = store_all('cases'); $ids = array_column($rows, 'id'); $n = ($ids ? max($ids) : 0) + 1;
            $row = ['id' => $n, 'code' => 'VBG-' . str_pad($n, 4, '0', STR_PAD_LEFT), 'kind' => $p['kind'] ?? 'VBG', 'date' => $p['date'] ?? date('Y-m-d'), 'ts' => strtotime($p['date'] ?? 'now'),
                'zone' => $p['zone'] ?? '', 'commune' => $p['commune'] ?? '', 'population' => $p['population'] ?? '', 'sexe' => $p['sexe'] ?? '', 'age' => $p['age'] ?? '',
                'type' => $p['type'] ?? '', 'status' => 'ouvert', 'pair' => x_user()['name'] ?? 'Pair animateur', 'referral' => $p['referral'] ?? '', 'notes' => $p['notes'] ?? '', 'source' => 'sync-offline'];
            store_append('cases', $row); x_log('Synchronisation', 'Dossier ' . $row['code']);
            api_ok(['id' => $row['id'], 'code' => $row['code']]);
        }
        api_err('Type inconnu');
    case 'alert':
        $r = save_alert($in ?: $_POST); x_log('Alerte', $r['type'] . ' #' . $r['id']);
        $t = $r['routed_to'] ? store_find('annuaire', $r['routed_to']) : null;
        api_ok(['id' => $r['id'], 'routed' => $t ? ['name' => $t['name'], 'role' => $t['role'], 'phone' => $t['phone']] : null]);
    case 'ping':
        api_ok(['t' => time()]);
    default:
        api_err('Action inconnue', 404);
}
