<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Mises à jour & optimisation'; $adminPage = 'mises-a-jour';
$tasks = ['cache' => ['Vider et régénérer le cache de l’application', 'bi-lightning-charge'], 'images' => ['Compression des images (WebP, 1600 px max.)', 'bi-image'], 'logs' => ['Purge des journaux de plus de 90 jours', 'bi-journal-x'], 'backup' => ['Sauvegarde de la base de données', 'bi-database-down'], 'index' => ['Reconstruction des index de recherche', 'bi-search'], 'sw' => ['Mise à jour du cache hors connexion (service worker)', 'bi-cloud-arrow-down']];
$state = setting('optim_state', []); if (!is_array($state)) $state = [];
$bdir = X_DATA . '/backups';
function mj_ver_cmp($a, $b) { return version_compare($a, $b); }

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
            $d = $bdir . '/' . date('Ymd-His'); if (!is_dir($d)) mkdir($d, 0777, true); $n = 0; $size = 0;
            foreach (glob(X_DATA . '/*.json') as $f) { copy($f, $d . '/' . basename($f)); $n++; $size += filesize($f); }
            $res = $n . ' fichiers sauvegardés (' . round($size / 1024) . ' Ko).';
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
$backups = is_dir($bdir) ? array_reverse(array_values(array_filter(scandir($bdir), fn($d) => $d[0] !== '.'))) : [];
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

		<div class="card"><div class="card-header"><h2 class="h6 mb-0">Sauvegardes</h2></div>
			<ul class="list-group list-group-flush" style="max-height:200px;overflow:auto"><?php foreach ($backups as $b): ?><li class="list-group-item small"><i class="bi bi-database me-2"></i><?= e(preg_replace('/^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/', '$3/$2/$1 à $4:$5', $b)) ?> <span class="text-secondary">· <?= count(glob($bdir . '/' . $b . '/*.json')) ?> fichiers</span></li><?php endforeach; if (!$backups): ?><li class="list-group-item small text-secondary">Aucune sauvegarde. Lancez « Sauvegarde de la base ».</li><?php endif; ?></ul></div>
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
<?php
$adminScripts = '<script>new Chart(document.getElementById("pgChart"),{type:"bar",data:{labels:["Fil","Chat","Thématiques","SOS","Annuaire","CCC"],datasets:[{label:"Visites (30 j)",data:[4200,3100,2300,860,1500,990],backgroundColor:XM_COLORS[0]}]},options:{plugins:{legend:{display:false}}}});</script>';
include __DIR__ . '/../partials/admin-bottom.php';
