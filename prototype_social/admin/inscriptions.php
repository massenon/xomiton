<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Inscriptions (activer / désactiver)'; $adminPage = 'inscriptions';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'toggle') {
        $open = !empty($_POST['open']);
        setting_set('registrations_open', $open);
        x_log('Paramètres', 'Inscriptions : ' . ($open ? 'activées' : 'désactivées'));
        xa_back($open ? 'success' : 'warning', $open ? 'Les inscriptions sont désormais OUVERTES.' : 'Les inscriptions sont désormais FERMÉES.');
    }
    if ($do === 'options') {
        $reopen = xa_post('reopen_at');
        if ($reopen !== '' && !strtotime($reopen)) xa_back('danger', 'Date de réouverture invalide.');
        $ws = xa_post('window_start'); $we = xa_post('window_end');
        if (!empty($_POST['window_on']) && ($ws === '' || $we === '' || $ws >= $we)) xa_back('danger', 'Plage horaire invalide : l’heure de début doit précéder l’heure de fin.');
        $msg = xa_post('closed_msg');
        if (mb_strlen($msg) > 300) xa_back('danger', 'Le message ne doit pas dépasser 300 caractères.');
        setting_set('manual_validation', !empty($_POST['manual_validation']));
        setting_set('reg_closed_msg', $msg);
        setting_set('reg_reopen_at', $reopen);
        setting_set('reg_window_on', !empty($_POST['window_on']));
        setting_set('reg_window_start', $ws); setting_set('reg_window_end', $we);
        x_log('Paramètres', 'Options d’inscription modifiées');
        xa_back('success', 'Options d’inscription enregistrées.');
    }
    if (in_array($do, ['approve', 'reject'], true)) {
        $u = store_find('users', xa_post('id'));
        if (!$u || $u['status'] !== 'en_attente') xa_back('danger', 'Demande introuvable.');
        if ($do === 'approve') { store_update('users', $u['id'], ['status' => 'actif']); x_log('Inscription', 'Approuvée : ' . $u['email']); xa_back('success', 'Inscription de ' . $u['name'] . ' approuvée.'); }
        store_delete('users', $u['id']); x_log('Inscription', 'Rejetée : ' . $u['email']); xa_back('warning', 'Inscription rejetée et supprimée.');
    }
    xa_back('danger', 'Action inconnue.');
}

$open = (bool)setting('registrations_open', true);
$manual = (bool)setting('manual_validation', false);
$users = store_all('users');
$pending = array_values(array_filter($users, fn($u) => $u['status'] === 'en_attente'));
$recent = $users; usort($recent, fn($a, $b) => [$b['joined'] ?? '', $b['id']] <=> [$a['joined'] ?? '', $a['id']]); $recent = array_slice($recent, 0, 10);
$weeks = []; $labels = [];
for ($i = 7; $i >= 0; $i--) { $start = strtotime('monday this week -' . $i . ' week'); $labels[] = date('d/m', $start); $weeks[] = count(array_filter($users, fn($u) => !empty($u['joined']) && strtotime($u['joined']) >= $start && strtotime($u['joined']) < $start + 7 * 86400)); }
$closedMsg = setting('reg_closed_msg', 'Les inscriptions sont momentanément fermées. Revenez bientôt ou contactez-nous.');
$reopen = setting('reg_reopen_at', '');
$total7 = count(array_filter($users, fn($u) => !empty($u['joined']) && strtotime($u['joined']) >= strtotime('-7 days')));

$adminActions = '<a class="btn btn-outline-primary btn-sm" href="../sign-up.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Voir la page</a>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-4">
	<div class="col-lg-7">
		<div class="card mb-4 <?= $open ? 'border-success' : 'border-danger' ?>">
			<div class="card-body p-4">
				<form method="post" id="toggleForm" class="d-flex flex-wrap align-items-center gap-4">
					<?= csrf_field() ?><input type="hidden" name="do" value="toggle">
					<div class="form-check form-switch fs-1 mb-0"><input class="form-check-input" type="checkbox" role="switch" name="open" value="1" id="regSwitch" style="width:3.2em;height:1.6em" <?= $open ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Inscriptions ouvertes"></div>
					<div class="flex-grow-1">
						<div class="h4 fw-bold mb-1"><?= $open ? '<span class="text-success">Inscriptions ouvertes</span>' : '<span class="text-danger">Inscriptions fermées</span>' ?></div>
						<div class="text-secondary"><?= $open ? 'Toute personne peut créer un compte sur la page d’inscription' . ($manual ? ' (validation manuelle requise).' : '.') : 'La page d’inscription affiche le message ci-dessous ; les comptes existants ne sont pas affectés.' ?></div>
					</div>
					<span class="badge fs-6 bg-<?= $open ? 'success' : 'danger' ?>"><?= $open ? 'ON' : 'OFF' ?></span>
				</form>
			</div>
			<div class="card-footer small d-flex flex-wrap gap-3 justify-content-between">
				<span><i class="bi bi-lightning-charge me-1"></i>Effet immédiat sur <code>sign-up.php</code></span>
				<a href="../sign-up.php" target="_blank" rel="noopener">Aperçu en direct <i class="bi bi-box-arrow-up-right"></i></a>
			</div>
		</div>

		<form method="post" class="card mb-4" data-xm-validate>
			<?= csrf_field() ?><input type="hidden" name="do" value="options">
			<div class="card-header"><h2 class="h6 mb-0">Options d’inscription</h2></div>
			<div class="card-body row g-3">
				<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="manual_validation" value="1" id="manualV" <?= $manual ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="manualV">Validation manuelle des nouveaux comptes</label><div class="small text-secondary">Les nouveaux comptes restent « en attente » jusqu’à approbation par un·e administrateur·rice.</div></div></div>
				<div class="col-12"><label class="form-label">Message affiché lorsque les inscriptions sont fermées</label><textarea class="form-control" name="closed_msg" rows="3" maxlength="300"><?= e($closedMsg) ?></textarea></div>
				<div class="col-md-6"><label class="form-label">Date / heure de réouverture prévue</label><input type="datetime-local" class="form-control" name="reopen_at" value="<?= e($reopen) ?>"></div>
				<div class="col-md-6">
					<div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" name="window_on" value="1" id="winOn" <?= setting('reg_window_on') ? 'checked' : '' ?>><label class="form-check-label" for="winOn">Plage horaire d’ouverture quotidienne</label></div>
					<div class="input-group input-group-sm"><input type="time" class="form-control" name="window_start" value="<?= e(setting('reg_window_start', '08:00')) ?>"><span class="input-group-text">à</span><input type="time" class="form-control" name="window_end" value="<?= e(setting('reg_window_end', '20:00')) ?>"></div>
				</div>
			</div>
			<div class="card-footer text-end"><button class="btn btn-primary btn-sm">Enregistrer les options</button></div>
		</form>

		<div class="card">
			<div class="card-header d-flex justify-content-between"><h2 class="h6 mb-0">Demandes en attente (<?= count($pending) ?>)</h2></div>
			<?php if (!$pending): ?><div class="card-body text-secondary">Aucune demande en attente.</div><?php else: ?>
			<ul class="list-group list-group-flush">
				<?php foreach ($pending as $u): ?>
				<li class="list-group-item d-flex flex-wrap align-items-center gap-3">
					<img src="<?= x_avatar($u['avatar'] ?? 0) ?>" class="rounded-circle" width="40" height="40" alt="">
					<div class="flex-grow-1"><div class="fw-semibold"><?= e($u['name']) ?> <small class="text-secondary">@<?= e($u['pseudo']) ?></small></div><div class="small text-secondary"><?= e($u['email']) ?> · <?= e($u['zone'] ?? '—') ?> · <?= e(xa_fr_date($u['joined'] ?? '')) ?></div></div>
					<form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
						<button name="do" value="approve" class="btn btn-sm btn-success"><i class="bi bi-check-lg me-1"></i>Approuver</button>
						<button name="do" value="reject" class="btn btn-sm btn-outline-danger" onclick="return confirm('Rejeter cette demande ?')">Rejeter</button></form>
				</li>
				<?php endforeach; ?>
			</ul><?php endif; ?>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="row g-3 mb-4">
			<?= str_replace('col-6 col-xl-3', 'col-6', xa_kpi('Cette semaine', $total7, 'bi-person-plus', 'success')) ?>
			<?= str_replace('col-6 col-xl-3', 'col-6', xa_kpi('En attente', count($pending), 'bi-hourglass-split', 'warning')) ?>
		</div>
		<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0">Inscriptions par semaine</h2></div><div class="card-body"><canvas id="regChart" height="200" aria-label="Inscriptions par semaine"></canvas></div></div>
		<div class="card">
			<div class="card-header"><h2 class="h6 mb-0">10 dernières inscriptions</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($recent as $u): ?><li class="list-group-item d-flex align-items-center gap-2"><img src="<?= x_avatar($u['avatar'] ?? 0) ?>" class="rounded-circle" width="32" height="32" alt=""><div class="flex-grow-1 small"><div class="fw-semibold"><?= e($u['name']) ?></div><div class="text-secondary"><?= e(xa_fr_date($u['joined'] ?? '')) ?> · <?= e($u['role']) ?></div></div><?= xa_status_badge($u['status']) ?></li><?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>
<?php
$adminScripts = '<script>new Chart(document.getElementById("regChart"),{type:"bar",data:{labels:' . json_encode($labels) . ',datasets:[{label:"Inscriptions",data:' . json_encode($weeks) . ',backgroundColor:XM_COLORS[0]}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});</script>';
include __DIR__ . '/../partials/admin-bottom.php';
