<?php
/** Mini-stockage JSON (prototype) : data/<collection>.json, liste d'objets avec champ id. */
function store_path($c) { return X_DATA . '/' . preg_replace('/[^a-z0-9_-]/i', '', $c) . '.json'; }
function store_all($c) {
    $p = store_path($c);
    if (!file_exists($p)) {
        $seed = X_DATA . '/seed/' . basename($p);
        if (file_exists($seed)) { copy($seed, $p); } else { return []; }
    }
    $j = json_decode(file_get_contents($p), true);
    return is_array($j) ? $j : [];
}
function store_save($c, $rows) {
    file_put_contents(store_path($c), json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function store_append($c, $row) {
    $rows = store_all($c);
    if (!isset($row['id'])) { $ids = array_column($rows, 'id'); $row['id'] = $ids ? max(array_map('intval', $ids)) + 1 : 1; }
    $rows[] = $row; store_save($c, $rows); return $row;
}
function store_find($c, $id) { foreach (store_all($c) as $r) if ((string)$r['id'] === (string)$id) return $r; return null; }
function store_update($c, $id, $patch) {
    $rows = store_all($c);
    foreach ($rows as &$r) if ((string)$r['id'] === (string)$id) { $r = array_merge($r, $patch); }
    unset($r); store_save($c, $rows);
}
function store_delete($c, $id) { store_save($c, array_filter(store_all($c), fn($r) => (string)$r['id'] !== (string)$id)); }

/* Paramètres plateforme (objet clé/valeur) */
function setting($k, $default = null) { $s = store_all('settings'); return $s[0][$k] ?? $default; }
function setting_set($k, $v) { $s = store_all('settings'); $s[0] = $s[0] ?? []; $s[0][$k] = $v; store_save('settings', $s); }
