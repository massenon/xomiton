<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Hors connexion & synchro'; $adminPage = 'synchronisation';
$now = time();
if (!file_exists(store_path('sync_devices'))) {
    $d = [['Esther K. — Parakou', 'Android 13 · Redmi 10', 2, 0, 0], ['Chantal H. — Porto-Novo', 'Android 12 · Tecno Spark', 26, 7, 1], ['Rodrigue S. — Cotonou', 'iPhone 12 · Safari', 5, 0, 0], ['Aïcha B. — Natitingou', 'Android 11 · Itel', 130, 14, 2], ['Poste Point focal — Djougou', 'Windows · Chrome', 1, 0, 0]];
    store_save('sync_devices', array_map(fn($x, $i) => ['id' => $i + 1, 'name' => $x[0], 'device' => $x[1], 'last' => $now - $x[2] * 3600, 'pending' => $x[3], 'conflicts' => $x[4]], $d, array_keys($d)));
}
if (!file_exists(store_path('sync_conflicts'))) {
    $c = [['VBG-0047', 'Statut du dossier', 'ouvert', 'clos', 'Chantal H. — Porto-Novo'], ['VBG-0052', 'Orientation', 'Psychologue', 'Juriste', 'Aïcha B. — Natitingou'], ['VBG-0058', 'Notes de suivi', 'Premier contact effectué.', 'Premier contact effectué ; rappel prévu jeudi.', 'Aïcha B. — Natitingou']];
    store_save('sync_conflicts', array_map(fn($x, $i) => ['id' => $i + 1, 'code' => $x[0], 'field' => $x[1], 'server' => $x[2], 'device' => $x[3], 'from' => $x[4], 'status' => 'à résoudre', 'resolution' => '', 'at' => date('Y-m-d H:i', time() - ($i + 3) * 3600)], $c, array_keys($c)));
}
$cfg = setting('sync_settings', ['interval' => 15, 'encrypt' => true, 'max_mb' => 5, 'wifi_only' => false]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'settings') {
        $iv = (int)xa_post('interval', 15); $mb = (int)xa_post('max_mb', 5);
        if (!in_array($iv, [5, 15, 30, 60, 240], true) || $mb < 1 || $mb > 50) xa_back('danger', 'Intervalle ou taille maximale invalide (1 à 50 Mo).');
        setting_set('sync_settings', ['interval' => $iv, 'encrypt' => !empty($_POST['encrypt']), 'max_mb' => $mb, 'wifi_only' => !empty($_POST['wifi_only'])]);
        setting_set('offline_sync', !empty($_POST['offline_sync'])); x_log('Synchronisation', 'Paramètres modifiés'); xa_back('success', 'Paramètres de synchronisation enregistrés.');
    }
    if ($do === 'resolve') {
        $c = store_find('sync_conflicts', xa_post('id')); $w = xa_post('keep') === 'device' ? 'appareil' : 'serveur';
        if (!$c) xa_back('danger', 'Conflit introuvable.');
        store_update('sync_conflicts', $c['id'], ['status' => 'résolu', 'resolution' => 'version ' . $w . ' conservée', 'by' => x_user()['name'], 'at' => date('Y-m-d H:i')]);
        foreach (store_all('sync_devices') as $d) if ($d['name'] === $c['from'] && $d['conflicts'] > 0) store_update('sync_devices', $d['id'], ['conflicts' => $d['conflicts'] - 1]);
        x_log('Synchronisation', 'Conflit ' . $c['code'] . ' résolu : version ' . $w); xa_back('success', 'Conflit résolu : version ' . $w . ' conservée.');
    }
    if ($do === 'flush') {
        $d = store_find('sync_devices', xa_post('id')); if (!$d) xa_back('danger', 'Appareil introuvable.');
        store_update('sync_devices', $d['id'], ['pending' => 0, 'last' => time()]); x_log('Synchronisation', 'Synchronisation forcée : ' . $d['name'] . ' (' . $d['pending'] . ' éléments)');
        xa_back('success', $d['pending'] . ' élément(s) synchronisé(s) pour ' . $d['name'] . ' (simulation).');
    }
    xa_back('danger', 'Action inconnue.');
}

$devices = store_all('sync_devices'); $conf = store_all('sync_conflicts');
$pendingTotal = array_sum(array_column($devices, 'pending')); $open = array_filter($conf, fn($c) => $c['status'] === 'à résoudre');
$log = array_values(array_filter(array_reverse(store_all('journal')), fn($j) => in_array($j['action'], ['Synchronisation', 'Collecte'], true)));
$stale = count(array_filter($devices, fn($d) => $d['last'] < $now - 86400));
$adminActions = '<button class="btn btn-primary btn-sm" id="btnForce"><i class="bi bi-arrow-repeat me-1"></i>Sync</button>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="card mb-4"><div class="card-body d-flex gap-3 align-items-start">
	<i class="bi bi-cloud-arrow-up fs-1 text-primary"></i>
	<div><h2 class="h6">Comment fonctionne le mode hors connexion ?</h2><p class="mb-0 small text-secondary">Les pairs animateur·rices saisissent leurs données (collecte, signalements, SOS) même sans réseau : les formulaires sont mis en file d’attente chiffrée dans l’appareil, puis envoyés automatiquement au retour de la connexion. En cas de modification concurrente d’un même dossier, un conflit est créé et l’administration choisit la version à conserver.</p></div>
</div></div>

<div class="row g-3 mb-4">
	<?= xa_kpi('Appareils suivis', count($devices), 'bi-phone') ?>
	<?= xa_kpi('Éléments en attente', $pendingTotal, 'bi-hourglass-split', 'warning') ?>
	<?= xa_kpi('Conflits à résoudre', count($open), 'bi-exclamation-diamond', 'danger') ?>
	<?= xa_kpi('Sans synchro > 24 h', $stale, 'bi-wifi-off', 'violet') ?>
</div>

<div class="row g-4 mb-4">
	<div class="col-xl-8">
		<div class="card mb-4"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Appareils & pairs</h2><span class="badge bg-secondary">Exemple</span></div>
			<div class="xm-table-wrap"><table class="table align-middle mb-0"><thead><tr><th>Pair / appareil</th><th>Dernière synchro</th><th class="text-end">En attente</th><th class="text-end">Conflits</th><th></th></tr></thead><tbody>
			<?php foreach ($devices as $d): $old = $d['last'] < $now - 86400; ?><tr><td><div class="fw-semibold"><?= e($d['name']) ?></div><div class="small text-secondary"><?= e($d['device']) ?></div></td>
				<td class="<?= $old ? 'text-danger' : '' ?>"><?= e(date('d/m H:i', $d['last'])) ?> <span class="small text-secondary">(il y a <?= x_ago($d['last']) ?>)</span></td>
				<td class="text-end"><?= $d['pending'] ? '<span class="badge bg-warning text-dark">' . (int)$d['pending'] . '</span>' : '0' ?></td><td class="text-end"><?= $d['conflicts'] ? '<span class="badge bg-danger">' . (int)$d['conflicts'] . '</span>' : '0' ?></td>
				<td class="text-end"><?php if ($d['pending']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="flush"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>"><button class="btn btn-sm btn-outline-primary">Forcer</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>

		<div class="card"><div class="card-header"><h2 class="h6 mb-0">Résolution des conflits</h2></div>
			<ul class="list-group list-group-flush"><?php foreach ($conf as $c): ?><li class="list-group-item">
				<div class="d-flex flex-wrap gap-2 align-items-center mb-2"><strong><?= e($c['code']) ?></strong><span class="text-secondary">· <?= e($c['field']) ?> · <?= e($c['from']) ?></span><span class="badge ms-auto bg-<?= $c['status'] === 'résolu' ? 'success' : 'danger' ?>"><?= e($c['status']) ?></span></div>
				<div class="row g-2 small"><div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-secondary">Version serveur</div><?= e($c['server']) ?></div></div><div class="col-md-6"><div class="border rounded p-2 h-100"><div class="text-secondary">Version appareil</div><?= e($c['device']) ?></div></div></div>
				<?php if ($c['status'] === 'à résoudre'): ?><form method="post" class="d-flex gap-2 mt-2"><?= csrf_field() ?><input type="hidden" name="do" value="resolve"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button name="keep" value="server" class="btn btn-sm btn-outline-primary">Garder le serveur</button><button name="keep" value="device" class="btn btn-sm btn-primary">Garder l’appareil</button></form>
				<?php else: ?><div class="small text-success mt-2"><i class="bi bi-check2"></i> <?= e($c['resolution']) ?> — <?= e($c['at']) ?></div><?php endif; ?></li><?php endforeach; ?></ul></div>
	</div>
	<div class="col-xl-4">
		<form method="post" class="card mb-4"><?= csrf_field() ?><input type="hidden" name="do" value="settings">
			<div class="card-header"><h2 class="h6 mb-0">Paramètres de synchronisation</h2></div>
			<div class="card-body">
				<div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" name="offline_sync" value="1" id="sOff" <?= setting('offline_sync', true) ? 'checked' : '' ?>><label class="form-check-label" for="sOff">Mode hors connexion activé</label></div>
				<label class="form-label small">Intervalle de synchronisation</label><select class="form-select form-select-sm mb-3" name="interval"><?php foreach ([5 => '5 minutes', 15 => '15 minutes', 30 => '30 minutes', 60 => '1 heure', 240 => '4 heures'] as $v => $l): ?><option value="<?= $v ?>" <?= (int)$cfg['interval'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
				<label class="form-label small">Taille maximale d’un envoi (Mo)</label><input class="form-control form-control-sm mb-3" name="max_mb" value="<?= (int)$cfg['max_mb'] ?>">
				<div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="encrypt" value="1" id="sEnc" <?= !empty($cfg['encrypt']) ? 'checked' : '' ?>><label class="form-check-label" for="sEnc">Chiffrement AES-256 <span class="badge bg-warning text-dark">simulé</span></label></div>
				<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="wifi_only" value="1" id="sWifi" <?= !empty($cfg['wifi_only']) ? 'checked' : '' ?>><label class="form-check-label" for="sWifi">Synchroniser en Wi-Fi uniquement</label></div>
			</div><div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form>

		<div class="card"><div class="card-header"><h2 class="h6 mb-0">Cache local (ce navigateur)</h2></div>
			<div class="card-body"><div class="small mb-2">Service worker : <strong id="swState">vérification…</strong></div><ul class="small list-unstyled mb-3" id="cacheList"><li class="text-secondary">Lecture des caches…</li></ul>
				<div class="small mb-3">File d’attente locale : <strong data-xm-queue-count>0</strong> élément(s)</div>
				<button class="btn btn-outline-danger btn-sm" id="btnClear"><i class="bi bi-trash me-1"></i>Vider le cache local</button></div></div>
	</div>
</div>

<div class="card"><div class="card-header"><h2 class="h6 mb-0">Journal de synchronisation</h2></div>
	<div class="xm-table-wrap"><table class="table table-sm align-middle mb-0"><thead><tr><th>Date</th><th>Utilisateur</th><th>Événement</th><th>Détail</th></tr></thead><tbody>
	<?php foreach (array_slice($log, 0, 15) as $j): ?><tr><td class="text-nowrap"><?= e($j['at']) ?></td><td><?= e($j['user']) ?></td><td><?= e($j['action']) ?></td><td><?= e($j['detail']) ?></td></tr><?php endforeach; if (!$log): ?><tr><td colspan="4" class="text-center text-secondary py-3">Aucune synchronisation enregistrée.</td></tr><?php endif; ?></tbody></table></div></div>
<?php
$adminScripts = <<<'JS'
<script>
(function () {
  function listCaches() {
    var ul = document.getElementById('cacheList'), sw = document.getElementById('swState');
    sw.textContent = 'serviceWorker' in navigator ? (navigator.serviceWorker.controller ? 'actif (contrôle la page)' : 'enregistré / en attente') : 'non pris en charge';
    if (!('caches' in window)) { ul.innerHTML = '<li class="text-secondary">API Cache indisponible.</li>'; return; }
    caches.keys().then(function (keys) {
      if (!keys.length) { ul.innerHTML = '<li class="text-secondary">Aucun cache enregistré.</li>'; return; }
      ul.innerHTML = '';
      keys.forEach(function (k) { caches.open(k).then(function (c) { return c.keys(); }).then(function (r) { var li = document.createElement('li'); li.innerHTML = '<i class="bi bi-hdd me-1"></i>'; li.appendChild(document.createTextNode(k + ' — ' + r.length + ' ressource(s)')); ul.appendChild(li); }); });
    }).catch(function () { ul.innerHTML = '<li class="text-secondary">Lecture impossible.</li>'; });
  }
  listCaches();
  document.getElementById('btnClear').addEventListener('click', function () {
    if (!confirm('Vider les caches du navigateur ? La file d’attente hors connexion est conservée.')) return;
    caches.keys().then(function (k) { return Promise.all(k.map(function (n) { return caches.delete(n); })); }).then(function () { XM.toast('Cache local vidé.', 'success'); listCaches(); });
  });
  document.getElementById('btnForce').addEventListener('click', function () {
    if (!navigator.onLine) { XM.toast('Hors connexion : impossible de synchroniser maintenant.', 'warning'); return; }
    Promise.resolve(XM.sync && XM.sync()).then(function () { XM.toast('Synchronisation lancée.', 'success'); });
  });
})();
</script>
JS;
include __DIR__ . '/../partials/admin-bottom.php';
