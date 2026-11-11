<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Mises à jour & optimisation'; $adminPage = 'mises-a-jour';
$tasks = ['cache' => ['Vider et régénérer le cache de l’application', 'bi-lightning-charge'], 'images' => ['Compression des images (WebP, 1600 px max.)', 'bi-image'], 'logs' => ['Purge des journaux de plus de 90 jours', 'bi-journal-x'], 'backup' => ['Sauvegarde de la base de données', 'bi-database-down'], 'index' => ['Reconstruction des index de recherche', 'bi-search'], 'sw' => ['Mise à jour du cache hors connexion (service worker)', 'bi-cloud-arrow-down']];
$state = setting('optim_state', []); if (!is_array($state)) $state = [];
$bdir = X_DATA . '/backups';
function mj_ver_cmp($a, $b) { return version_compare($a, $b); }
/** Nom de sauvegarde sûr (AAAAMMJJ-HHMMSS, suffixe facultatif) ou null. */
function mj_bk($name) { return is_string($name) && preg_match('/^\d{8}-\d{6}(-[a-z]+)?$/', $name) && is_dir(X_DATA . '/backups/' . $name) ? $name : null; }
function mj_bk_label($b) { return preg_replace('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2}).*$/', '$3/$2/$1 à $4:$5', $b); }
function mj_size($o) { return $o >= 1048576 ? round($o / 1048576, 1) . ' Mo' : max(1, round($o / 1024)) . ' Ko'; }
/** Inventaire d'une sauvegarde : collection, enregistrements, taille, écart avec les données actuelles. */
function mj_bk_files($b) {
    $out = [];
    foreach (glob(X_DATA . '/backups/' . $b . '/*.json') as $f) {
        $c = basename($f, '.json'); $j = json_decode(file_get_contents($f), true); $n = is_array($j) ? count($j) : 0;
        $cur = X_DATA . '/' . $c . '.json'; $nc = file_exists($cur) ? count(json_decode(file_get_contents($cur), true) ?: []) : null;
        $out[] = ['file' => $c, 'records' => $n, 'size' => filesize($f), 'current' => $nc, 'diff' => $nc === null ? null : $nc - $n];
    }
    return $out;
}
/** Copie les collections JSON vers un nouveau dossier de sauvegarde ; retourne [nom, nb fichiers, taille]. */
function mj_backup_now($suffix = '') {
    $name = date('Ymd-His') . ($suffix ? '-' . $suffix : ''); $d = X_DATA . '/backups/' . $name;
    if (!is_dir($d)) mkdir($d, 0777, true); $n = 0; $size = 0;
    foreach (glob(X_DATA . '/*.json') as $f) { copy($f, $d . '/' . basename($f)); $n++; $size += filesize($f); }
    return [$name, $n, $size];
}

/* Aperçu d'une sauvegarde (JSON pour la modale) */
if (isset($_GET['bk_preview'])) {
    header('Content-Type: application/json; charset=utf-8');
    $b = mj_bk($_GET['bk_preview']); if (!$b) { http_response_code(404); echo json_encode(['ok' => false]); exit; }
    $file = preg_replace('/[^a-z0-9_-]/i', '', $_GET['f'] ?? '');
    if ($file !== '') {
        $p = X_DATA . '/backups/' . $b . '/' . $file . '.json';
        $rows = file_exists($p) ? (json_decode(file_get_contents($p), true) ?: []) : [];
        $mask = function ($r) { if (!is_array($r)) return $r; foreach ($r as $k => $v) if (preg_match('/hash|pass|token|secret|pin/i', (string)$k)) $r[$k] = '••••'; return $r; };
        echo json_encode(['ok' => true, 'file' => $file, 'total' => count($rows), 'sample' => array_map($mask, array_slice($rows, 0, 5))], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); exit;
    }
    $files = mj_bk_files($b);
    echo json_encode(['ok' => true, 'name' => $b, 'label' => mj_bk_label($b), 'files' => $files, 'records' => array_sum(array_column($files, 'records')), 'size' => mj_size(array_sum(array_column($files, 'size')))], JSON_UNESCAPED_UNICODE); exit;
}
/* Téléchargement d'une sauvegarde (archive ZIP) */
if (isset($_GET['bk_download']) && ($b = mj_bk($_GET['bk_download']))) {
    if (!class_exists('ZipArchive')) xa_back('danger', 'Extension ZIP indisponible sur ce serveur.');
    $tmp = tempnam(sys_get_temp_dir(), 'xmbk'); $z = new ZipArchive(); $z->open($tmp, ZipArchive::OVERWRITE);
    foreach (glob(X_DATA . '/backups/' . $b . '/*.json') as $f) $z->addFile($f, basename($f));
    $z->close(); x_log('Sauvegarde', 'Téléchargement : ' . $b);
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="xomiton-sauvegarde-' . $b . '.zip"'); header('Content-Length: ' . filesize($tmp));
    readfile($tmp); unlink($tmp); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'release') {
        $v = xa_post('version'); $notes = xa_post('notes'); $type = xa_post('type', 'Correctif'); $mode = xa_post('mode'); $date = xa_post('date') ?: date('Y-m-d');
        if (!preg_match('/^\d+\.\d+\.\d+$/', $v)) xa_back('danger', 'Version invalide (format attendu : 1.5.0).');
        if (mb_strlen($notes) < 10) xa_back('danger', 'Notes de version trop courtes (10 caractères min.).');
        foreach (store_all('updates') as $u) if ($u['version'] === $v && $u['status'] !== 'planifiée') xa_back('danger', 'Cette version existe déjà.');
        if ($mode === 'plan') {
            if (!strtotime($date) || strtotime($date) < strtotime('today')) xa_back('danger', 'La date planifiée doit être aujourd’hui ou plus tard.');
            store_append('updates', ['version' => $v, 'date' => $date, 'type' => $type, 'notes' => $notes, 'status' => 'planifiée']); x_log('Mise à jour', 'Planifiée : ' . $v); xa_back('success', 'Version ' . $v . ' planifiée pour le ' . xa_fr_date($date) . '.');
        }
        if (version_compare($v, setting('platform_version', '0.0.0'), '<=')) xa_back('danger', 'La nouvelle version doit être supérieure à la version actuelle (' . setting('platform_version') . ').');
        store_append('updates', ['version' => $v, 'date' => date('Y-m-d'), 'type' => $type, 'notes' => $notes, 'status' => 'déployée']);
        setting_set('platform_version', $v); setting_set('last_update', date('Y-m-d')); x_log('Mise à jour', 'Publiée : ' . $v); xa_back('success', 'Version ' . $v . ' publiée.');
    }
    if ($do === 'deploy') {
        $u = store_find('updates', xa_post('id')); if (!$u || $u['status'] !== 'planifiée') xa_back('danger', 'Version introuvable.');
        if (version_compare($u['version'], setting('platform_version', '0.0.0'), '<=')) xa_back('danger', 'Cette version n’est pas supérieure à la version actuelle.');
        store_update('updates', $u['id'], ['status' => 'déployée', 'date' => date('Y-m-d')]); setting_set('platform_version', $u['version']); setting_set('last_update', date('Y-m-d')); x_log('Mise à jour', 'Déployée : ' . $u['version']); xa_back('success', 'Version ' . $u['version'] . ' déployée.');
    }
    if ($do === 'bk_restore' || $do === 'bk_delete') {
        $b = mj_bk(xa_post('bk')); if (!$b) xa_back('danger', 'Sauvegarde introuvable.');
        if ($do === 'bk_delete') {
            array_map('unlink', glob($bdir . '/' . $b . '/*')); rmdir($bdir . '/' . $b);
            x_log('Sauvegarde', 'Suppression : ' . $b); xa_back('success', 'Sauvegarde du ' . mj_bk_label($b) . ' supprimée.');
        }
        if (xa_post('confirm') !== 'RESTAURER') xa_back('danger', 'Tapez RESTAURER pour confirmer la restauration.');
        [$safety] = mj_backup_now('avant'); $n = 0;
        foreach (glob($bdir . '/' . $b . '/*.json') as $f) { copy($f, X_DATA . '/' . basename($f)); $n++; }
        x_log('Sauvegarde', 'Restauration de ' . $b . ' (' . $n . ' fichiers), état précédent conservé dans ' . $safety);
        xa_back('success', 'Sauvegarde du ' . mj_bk_label($b) . ' restaurée (' . $n . ' fichiers). L’état précédent a été sauvegardé automatiquement.');
    }
    if ($do === 'cancel') { store_delete('updates', xa_post('id')); xa_back('success', 'Planification annulée.'); }
    if ($do === 'maintenance') {
        $on = !empty($_POST['maintenance']); $s = xa_post('m_start'); $e = xa_post('m_end'); $msg = xa_post('m_msg');
        if (($s !== '' || $e !== '') && (!strtotime($s) || !strtotime($e) || strtotime($e) <= strtotime($s))) xa_back('danger', 'Fenêtre de maintenance invalide : la fin doit suivre le début.');
        setting_set('maintenance', $on); setting_set('maint_start', $s); setting_set('maint_end', $e); setting_set('maint_msg', $msg);
        x_log('Maintenance', $on ? 'Mode maintenance activé' : 'Mode maintenance désactivé'); xa_back($on ? 'warning' : 'success', $on ? 'Mode maintenance ACTIVÉ : seule l’administration peut se connecter.' : 'Mode maintenance désactivé.');
    }
    if ($do === 'run' && isset($tasks[xa_post('task')])) {
        $t = xa_post('task'); $res = 'Terminé (simulation).';
        if ($t === 'backup') {
            [, $n, $size] = mj_backup_now();
            $res = $n . ' fichiers sauvegardés (' . mj_size($size) . ').';
        } elseif ($t === 'logs') {
            $keep = array_filter(store_all('journal'), fn($j) => strtotime($j['at']) >= strtotime('-90 days')); $del = count(store_all('journal')) - count($keep);
            store_save('journal', $keep); $res = $del . ' entrée(s) purgée(s).';
        } elseif ($t === 'images') { $res = 'Images optimisées : −38 % de poids (simulation).'; }
        elseif ($t === 'cache') { $res = 'Cache régénéré (simulation).'; } elseif ($t === 'index') { $res = 'Index reconstruits (simulation).'; } else { $res = 'Nouvelle version du cache hors ligne diffusée (simulation).'; }
        $state[$t] = ['at' => date('Y-m-d H:i'), 'res' => $res]; setting_set('optim_state', $state);
        x_log('Optimisation', $tasks[$t][0] . ' — ' . $res); xa_back('success', $tasks[$t][0] . ' : ' . $res);
    }
    xa_back('danger', 'Action inconnue.');
}

$ups = store_all('updates'); usort($ups, fn($a, $b) => version_compare($b['version'], $a['version']));
$cur = setting('platform_version', '1.0.0'); $maint = (bool)setting('maintenance', false);
$backups = is_dir($bdir) ? array_reverse(array_values(array_filter(scandir($bdir), fn($d) => (bool)mj_bk($d)))) : [];
$bkInfo = [];
foreach ($backups as $b) { $fs = glob($bdir . '/' . $b . '/*.json'); $bkInfo[$b] = ['files' => count($fs), 'size' => array_sum(array_map('filesize', $fs))]; }
$bkTotal = array_sum(array_column($bkInfo, 'size'));
$planned = array_filter($ups, fn($u) => $u['status'] === 'planifiée');
$tcls = ['Fonctionnalité' => 'primary', 'Correctif' => 'success', 'Sécurité' => 'danger', 'Optimisation' => 'info'];
$adminActions = '<span class="badge bg-primary fs-6">v' . e($cur) . '</span>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Version actuelle', 'v' . e($cur), 'bi-tag', '', 'Mise à jour : ' . xa_fr_date(setting('last_update', ''))) ?>
	<?= xa_kpi('Versions planifiées', count($planned), 'bi-calendar-check', 'success') ?>
	<?= xa_kpi('Sauvegardes', count($backups), 'bi-database-check', 'violet') ?>
	<?= xa_kpi('Maintenance', $maint ? 'ACTIVE' : 'Inactive', 'bi-cone-striped', $maint ? 'danger' : 'warning') ?>
</div>

<div class="row g-4 mb-4">
	<div class="col-xl-7">
		<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0">Historique des versions</h2></div>
			<div class="xm-table-wrap"><table class="table align-middle mb-0"><thead><tr><th>Version</th><th>Date</th><th>Type</th><th>Notes</th><th>Statut</th><th></th></tr></thead><tbody>
			<?php foreach ($ups as $u): ?><tr><td class="fw-semibold">v<?= e($u['version']) ?><?= $u['version'] === $cur ? ' <span class="badge bg-primary">actuelle</span>' : '' ?></td><td class="text-nowrap"><?= e(xa_fr_date($u['date'])) ?></td><td><span class="badge bg-<?= $tcls[$u['type']] ?? 'secondary' ?>"><?= e($u['type']) ?></span></td><td class="small"><?= e($u['notes']) ?></td><td><?= xa_status_badge($u['status']) ?></td>
				<td class="text-end text-nowrap"><?php if ($u['status'] === 'planifiée'): ?><form method="post" class="d-inline-flex gap-1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button name="do" value="deploy" class="btn btn-sm btn-success">Déployer</button><button name="do" value="cancel" class="btn btn-sm btn-light text-danger" aria-label="Annuler" onclick="return confirm('Annuler cette planification ?')"><i class="bi bi-x-lg"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div></div>

		<form method="post" class="card" data-xm-validate><?= csrf_field() ?><input type="hidden" name="do" value="release">
			<div class="card-header"><h2 class="h6 mb-0">Publier ou planifier une version</h2></div>
			<div class="card-body row g-3">
				<div class="col-md-4"><label class="form-label">Version *</label><input class="form-control" name="version" placeholder="1.4.3" required pattern="\d+\.\d+\.\d+"></div>
				<div class="col-md-4"><label class="form-label">Type</label><select class="form-select" name="type"><?php foreach (array_keys($tcls) as $t): ?><option><?= e($t) ?></option><?php endforeach; ?></select></div>
				<div class="col-md-4"><label class="form-label">Date (si planifiée)</label><input type="date" class="form-control" name="date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></div>
				<div class="col-12"><label class="form-label">Notes de version *</label><textarea class="form-control" name="notes" rows="3" required data-rule="min_len_20"></textarea></div>
				<div class="col-12"><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="mode" value="now" id="mNow" checked><label class="form-check-label" for="mNow">Publier maintenant</label></div><div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="mode" value="plan" id="mPlan"><label class="form-check-label" for="mPlan">Planifier</label></div></div>
			</div><div class="card-footer text-end"><button class="btn btn-primary btn-sm">Valider</button></div></form>
	</div>
	<div class="col-xl-5">
		<form method="post" class="card mb-4 <?= $maint ? 'border-danger' : '' ?>"><?= csrf_field() ?><input type="hidden" name="do" value="maintenance">
			<div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-cone-striped me-2"></i>Fenêtre de maintenance</h2></div>
			<div class="card-body row g-3">
				<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="maintenance" value="1" id="mOn" <?= $maint ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="mOn">Mode maintenance</label><div class="small text-secondary">Bloque la connexion des non-administrateur·rices.</div></div></div>
				<div class="col-md-6"><label class="form-label small">Début prévu</label><input type="datetime-local" class="form-control form-control-sm" name="m_start" value="<?= e(setting('maint_start', '')) ?>"></div>
				<div class="col-md-6"><label class="form-label small">Fin prévue</label><input type="datetime-local" class="form-control form-control-sm" name="m_end" value="<?= e(setting('maint_end', '')) ?>"></div>
				<div class="col-12"><label class="form-label small">Message aux membres</label><input class="form-control form-control-sm" name="m_msg" maxlength="200" value="<?= e(setting('maint_msg', 'Xomiton est en maintenance. Nous revenons très vite.')) ?>"></div>
			</div><div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer</button></div></form>

		<div class="card" id="sauvegardes">
			<div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
				<div><h2 class="h6 mb-0">Sauvegardes</h2><div class="small text-secondary"><?= count($backups) ?> sauvegarde<?= count($backups) > 1 ? 's' : '' ?> · <?= $backups ? mj_size($bkTotal) : '0 Ko' ?> au total</div></div>
				<form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="run"><input type="hidden" name="task" value="backup"><button class="btn btn-sm btn-primary"><i class="bi bi-database-down me-1"></i>Sauvegarder maintenant</button></form>
			</div>
			<?php if ($backups): $last = $backups[0]; ?>
			<div class="card-body border-bottom py-2 small d-flex flex-wrap gap-3">
				<span><i class="bi bi-clock-history text-primary me-1"></i>Dernière : <b><?= e(mj_bk_label($last)) ?></b></span>
				<span><i class="bi bi-files text-primary me-1"></i><?= (int)$bkInfo[$last]['files'] ?> collections</span>
				<span><i class="bi bi-hdd text-primary me-1"></i><?= mj_size($bkInfo[$last]['size']) ?></span>
			</div>
			<?php endif; ?>
			<ul class="list-group list-group-flush" style="max-height:320px;overflow:auto">
			<?php foreach ($backups as $b): $auto = str_ends_with($b, '-avant'); ?>
				<li class="list-group-item d-flex align-items-center gap-2">
					<i class="bi <?= $auto ? 'bi-shield-check text-success' : 'bi-database text-primary' ?> fs-5" aria-hidden="true"></i>
					<div class="flex-grow-1 small">
						<div class="fw-semibold"><?= e(mj_bk_label($b)) ?><?= $auto ? ' <span class="badge bg-success-subtle text-success-emphasis">avant restauration</span>' : '' ?></div>
						<div class="text-secondary"><?= (int)$bkInfo[$b]['files'] ?> fichiers · <?= mj_size($bkInfo[$b]['size']) ?></div>
					</div>
					<div class="d-flex gap-1">
						<button type="button" class="btn btn-sm btn-light" data-bk-preview="<?= e($b) ?>" data-bs-toggle="modal" data-bs-target="#mBackup" title="Aperçu" aria-label="Aperçu de la sauvegarde du <?= e(mj_bk_label($b)) ?>"><i class="bi bi-eye"></i></button>
						<a class="btn btn-sm btn-light" href="?bk_download=<?= e($b) ?>" title="Télécharger (ZIP)" aria-label="Télécharger la sauvegarde"><i class="bi bi-download"></i></a>
						<form method="post" data-confirm="Supprimer définitivement cette sauvegarde ?"><?= csrf_field() ?><input type="hidden" name="do" value="bk_delete"><input type="hidden" name="bk" value="<?= e($b) ?>"><button class="btn btn-sm btn-light text-danger" title="Supprimer" aria-label="Supprimer la sauvegarde"><i class="bi bi-trash"></i></button></form>
					</div>
				</li>
			<?php endforeach; if (!$backups): ?>
				<li class="list-group-item small text-secondary text-center py-4"><i class="bi bi-database-x fs-3 d-block mb-1"></i>Aucune sauvegarde. Lancez « Sauvegarder maintenant ».</li>
			<?php endif; ?>
			</ul>
		</div>
	</div>
</div>

<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0">Optimisation de la plateforme</h2></div>
	<ul class="list-group list-group-flush"><?php foreach ($tasks as $k => $t): ?><li class="list-group-item d-flex flex-wrap align-items-center gap-3"><i class="bi <?= $t[1] ?> fs-4 text-primary"></i><div class="flex-grow-1"><div class="fw-semibold"><?= e($t[0]) ?></div><div class="small text-secondary"><?= isset($state[$k]) ? 'Dernier lancement : ' . e($state[$k]['at']) . ' — ' . e($state[$k]['res']) : 'Jamais lancé' ?></div></div>
		<form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="run"><input type="hidden" name="task" value="<?= $k ?>"><button class="btn btn-sm btn-outline-primary"><i class="bi bi-play-fill me-1"></i>Lancer</button></form></li><?php endforeach; ?></ul></div>

<div class="row g-4">
	<div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Pages les plus utilisées</h2><span class="badge bg-secondary">Exemple</span></div><div class="card-body"><canvas id="pgChart" height="200" aria-label="Pages les plus utilisées (exemple)"></canvas></div></div></div>
	<div class="col-lg-6"><div class="card h-100"><div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Adoption des fonctionnalités</h2><span class="badge bg-secondary">Exemple</span></div><div class="card-body">
		<?php foreach (['Fil d’actualité' => 88, 'Chat' => 71, 'Signaler' => 46, 'Assistant' => 39, 'Outils CCC' => 33, 'Mode hors connexion' => 21] as $l => $p): ?><div class="mb-2"><div class="d-flex justify-content-between small"><span><?= $l ?></span><span><?= $p ?> %</span></div><div class="progress" style="height:6px"><div class="progress-bar" style="width:<?= $p ?>%"></div></div></div><?php endforeach; ?>
		<hr><h3 class="h6">Recommandations</h3><ul class="small mb-0"><li>Organiser une formation courte sur le mode hors connexion (21 % d’adoption).</li><li>Envoyer un rappel aux pairs sans synchronisation depuis plus de 7 jours.</li><li>Mettre en avant l’assistant dans la page d’accueil membre.</li></ul></div></div></div>
</div>
<!-- Modale : aperçu d'une sauvegarde -->
<div class="modal fade" id="mBackup" tabindex="-1" aria-labelledby="mBackupLabel" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
	<div class="modal-header"><div><h5 class="modal-title" id="mBackupLabel">Aperçu de la sauvegarde</h5><div class="small text-secondary" data-bk-meta>Chargement…</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body">
		<div class="row g-2 mb-3" data-bk-kpis></div>
		<div class="xm-table-wrap mb-3"><table class="table table-sm table-hover align-middle mb-0">
			<thead><tr><th>Collection</th><th class="text-end">Enregistrements</th><th class="text-end">Actuellement</th><th class="text-end">Écart</th><th class="text-end">Taille</th><th></th></tr></thead>
			<tbody data-bk-rows><tr><td colspan="6" class="text-center text-secondary py-3">Chargement…</td></tr></tbody>
		</table></div>
		<div class="d-none" data-bk-sample-wrap>
			<h3 class="h6 d-flex justify-content-between"><span>Extrait : <code data-bk-sample-name></code></span><span class="small text-secondary" data-bk-sample-count></span></h3>
			<pre class="bg-body-tertiary border rounded p-3 small mb-0" style="max-height:260px;overflow:auto" data-bk-sample></pre>
		</div>
	</div>
	<form method="post" class="modal-footer flex-wrap gap-2">
		<?= csrf_field() ?><input type="hidden" name="do" value="bk_restore"><input type="hidden" name="bk" data-bk-name>
		<div class="me-auto small text-secondary" style="max-width:340px">La restauration remplace les données actuelles. L’état actuel est sauvegardé automatiquement juste avant.</div>
		<label class="visually-hidden" for="bkConfirm">Tapez RESTAURER pour confirmer</label>
		<input class="form-control form-control-sm w-auto" id="bkConfirm" name="confirm" placeholder="Tapez RESTAURER" autocomplete="off" required pattern="RESTAURER">
		<a class="btn btn-light btn-sm" data-bk-download href="#"><i class="bi bi-download me-1"></i>Télécharger</a>
		<button class="btn btn-danger btn-sm" data-bk-restore disabled><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurer</button>
	</form>
</div></div></div>
<?php
$adminScripts = xa_common_js() . <<<'JS'
<script>
(function () {
  var m = document.getElementById('mBackup'); if (!m) return;
  var q = function (s) { return m.querySelector(s); }, esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
  var fmt = function (o) { return o >= 1048576 ? (o / 1048576).toFixed(1) + ' Mo' : Math.max(1, Math.round(o / 1024)) + ' Ko'; };
  var current = '';
  function sample(f) {
    q('[data-bk-sample-wrap]').classList.remove('d-none'); q('[data-bk-sample-name]').textContent = f + '.json'; q('[data-bk-sample]').textContent = 'Chargement…';
    fetch('?bk_preview=' + encodeURIComponent(current) + '&f=' + encodeURIComponent(f)).then(function (r) { return r.json(); }).then(function (d) {
      q('[data-bk-sample-count]').textContent = d.total > 5 ? '5 premiers sur ' + d.total : d.total + ' enregistrement(s)';
      q('[data-bk-sample]').textContent = JSON.stringify(d.sample, null, 2);
    });
  }
  m.addEventListener('show.bs.modal', function (ev) {
    current = ev.relatedTarget ? ev.relatedTarget.getAttribute('data-bk-preview') : '';
    q('[data-bk-name]').value = current; q('[data-bk-download]').href = '?bk_download=' + encodeURIComponent(current);
    q('#bkConfirm').value = ''; q('[data-bk-restore]').disabled = true; q('[data-bk-sample-wrap]').classList.add('d-none');
    q('[data-bk-rows]').innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-3">Chargement…</td></tr>';
    fetch('?bk_preview=' + encodeURIComponent(current)).then(function (r) { return r.json(); }).then(function (d) {
      if (!d.ok) { q('[data-bk-meta]').textContent = 'Sauvegarde introuvable.'; return; }
      q('[data-bk-meta]').textContent = 'Sauvegarde du ' + d.label + ' · ' + d.files.length + ' collections · ' + d.size;
      var changed = d.files.filter(function (f) { return f.diff; }).length;
      var k = [['Collections', d.files.length, 'bi-files'], ['Enregistrements', d.records, 'bi-list-ol'], ['Taille', d.size, 'bi-hdd'], ['Modifiées depuis', changed, 'bi-arrow-left-right']];
      q('[data-bk-kpis]').innerHTML = k.map(function (x) { return '<div class="col-6 col-md-3"><div class="border rounded p-2 h-100"><div class="small text-secondary"><i class="bi ' + x[2] + ' me-1"></i>' + x[0] + '</div><div class="fw-bold fs-5">' + esc(String(x[1])) + '</div></div></div>'; }).join('');
      q('[data-bk-rows]').innerHTML = d.files.map(function (f) {
        var diff = f.diff === null ? '<span class="badge bg-secondary">absente</span>' : f.diff === 0 ? '<span class="text-secondary">=</span>' : '<span class="badge bg-' + (f.diff > 0 ? 'primary' : 'warning text-dark') + '">' + (f.diff > 0 ? '+' : '') + f.diff + '</span>';
        return '<tr><td><code>' + esc(f.file) + '</code></td><td class="text-end">' + f.records + '</td><td class="text-end">' + (f.current === null ? '—' : f.current) + '</td><td class="text-end">' + diff + '</td><td class="text-end small">' + fmt(f.size) + '</td><td class="text-end"><button type="button" class="btn btn-sm btn-link py-0" data-bk-file="' + esc(f.file) + '">Voir</button></td></tr>';
      }).join('');
    });
  });
  m.addEventListener('click', function (e) { var b = e.target.closest('[data-bk-file]'); if (b) sample(b.getAttribute('data-bk-file')); });
  q('#bkConfirm').addEventListener('input', function () { q('[data-bk-restore]').disabled = this.value !== 'RESTAURER'; });
})();
</script>
JS;
$adminScripts .= '<script>new Chart(document.getElementById("pgChart"),{type:"bar",data:{labels:["Fil","Chat","Thématiques","SOS","Annuaire","CCC"],datasets:[{label:"Visites (30 j)",data:[4200,3100,2300,860,1500,990],backgroundColor:XM_COLORS[0]}]},options:{plugins:{legend:{display:false}}}});</script>';
include __DIR__ . '/../partials/admin-bottom.php';
