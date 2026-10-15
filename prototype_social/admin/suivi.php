<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin1.php';
$pageTitle = 'Suivi des victimes';
$adminPage = 'suivi';
$steps = ['Accueil', 'Écoute', 'Orientation médicale', 'Appui psychologique', 'Appui juridique', 'Hébergement', 'Suivi à 30 jours', 'Suivi à 90 jours', 'Clôture'];
$outcomes = ['Réalisé', 'En attente', 'Reporté'];
$today = date('Y-m-d');
$cases = []; foreach (store_all('cases') as $c) $cases[$c['code']] = $c;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { xa_flash('danger', 'Session expirée.'); xa_redirect('suivi.php'); }
    $act = $_POST['action'] ?? '';
    if ($act === 'done') {
        $s = store_find('suivi', (int)$_POST['id']);
        if ($s) { store_update('suivi', $s['id'], ['outcome' => 'Réalisé']); x_log('Suivi réalisé', $s['case_code'] . ' / ' . $s['step']); xa_flash('success', 'Étape marquée comme réalisée.'); }
        xa_redirect('suivi.php', 'case=' . urlencode($s['case_code'] ?? ''));
    }
    $d = [];
    foreach (['case_code', 'step', 'date', 'by', 'outcome', 'next', 'comment'] as $k) $d[$k] = trim((string)($_POST[$k] ?? ''));
    $err = [];
    if (!isset($cases[$d['case_code']])) $err[] = 'Code dossier inconnu.';
    if (!in_array($d['step'], $steps, true)) $err[] = 'Étape invalide.';
    if (!xa_date_ok($d['date']) || $d['date'] > $today) $err[] = 'Date invalide ou dans le futur.';
    if (!in_array($d['outcome'], $outcomes, true)) $err[] = 'Résultat invalide.';
    if ($d['by'] === '') $err[] = 'Renseignez la personne qui assure le suivi.';
    if ($d['next'] !== '' && (!xa_date_ok($d['next']) || $d['next'] < $d['date'])) $err[] = 'La prochaine date doit être postérieure à la date de l’étape.';
    if ($err) { xa_flash('danger', implode(' ', $err)); xa_redirect('suivi.php', 'case=' . urlencode($d['case_code'])); }
    $d['comment'] = mb_substr($d['comment'], 0, 1000);
    store_append('suivi', $d);
    x_log('Suivi ajouté', $d['case_code'] . ' / ' . $d['step']);
    xa_flash('success', 'Étape de suivi enregistrée pour ' . $d['case_code'] . '.');
    xa_redirect('suivi.php', 'case=' . urlencode($d['case_code']));
}

$all = store_all('suivi');
$selCode = strtoupper(xa_get('case'));
$total = count($all);
$done = count(array_filter($all, fn($s) => $s['outcome'] === 'Réalisé'));
$overdue = array_values(array_filter($all, fn($s) => $s['outcome'] !== 'Réalisé' && $s['next'] !== '' && $s['next'] < $today));
usort($overdue, fn($a, $b) => strcmp($a['next'], $b['next']));
$upcoming = array_values(array_filter($all, fn($s) => $s['next'] !== '' && $s['next'] >= $today));
usort($upcoming, fn($a, $b) => strcmp($a['next'], $b['next']));
$gaps = []; $first = [];
foreach ($all as $s) {
    if ($s['next'] !== '') $gaps[] = (strtotime($s['next']) - strtotime($s['date'])) / 86400;
    if (isset($cases[$s['case_code']])) { $first[$s['case_code']] = min($first[$s['case_code']] ?? '9999', $s['date']); }
}
$delays = [];
foreach ($first as $code => $d) $delays[] = max(0, (strtotime($d) - strtotime($cases[$code]['date'])) / 86400);
$avgGap = $gaps ? round(array_sum($gaps) / count($gaps)) : 0;
$avgFirst = $delays ? round(array_sum($delays) / count($delays)) : 0;
$nCases = count(array_unique(array_column($all, 'case_code')));

$q = mb_strtolower(xa_get('q'));
$timeline = [];
if ($selCode) { $timeline = array_values(array_filter($all, fn($s) => $s['case_code'] === $selCode)); usort($timeline, fn($a, $b) => strcmp($b['date'], $a['date'])); }
$list = $all; usort($list, fn($a, $b) => strcmp($b['date'], $a['date']));
if ($q) $list = array_values(array_filter($list, fn($s) => mb_strpos(mb_strtolower($s['case_code'] . ' ' . $s['step'] . ' ' . $s['by']), $q) !== false));
$byCase = []; foreach ($all as $s) $byCase[$s['case_code']] = true;

if (xa_get('export') === 'csv') {
    xa_csv('suivi_' . date('Ymd') . '.csv', ['Dossier', 'Étape', 'Date', 'Par', 'Résultat', 'Prochaine date', 'Commentaire'], array_map(fn($s) => [$s['case_code'], $s['step'], $s['date'], $s['by'], $s['outcome'], $s['next'], $s['comment']], $list));
}
include __DIR__ . '/../partials/admin-top.php';
$oc = ['Réalisé' => 'success', 'En attente' => 'warning', 'Reporté' => 'secondary'];
?>
<div class="row g-3 mb-3">
	<div class="col-6 col-lg-3"><div class="card xm-stat success"><div class="card-body"><div class="small text-secondary">Suivis réalisés</div><div class="kpi-value"><?= $total ? round($done * 100 / $total) : 0 ?> %</div><div class="small text-secondary"><?= $done ?> / <?= $total ?> étapes</div></div></div></div>
	<div class="col-6 col-lg-3"><div class="card xm-stat danger"><div class="card-body"><div class="small text-secondary">Suivis en retard</div><div class="kpi-value"><?= count($overdue) ?></div><div class="small text-secondary">prochaine date dépassée</div></div></div></div>
	<div class="col-6 col-lg-3"><div class="card xm-stat"><div class="card-body"><div class="small text-secondary">Délai moyen entre suivis</div><div class="kpi-value"><?= $avgGap ?> j</div><div class="small text-secondary">1er suivi après <?= $avgFirst ?> j en moyenne</div></div></div></div>
	<div class="col-6 col-lg-3"><div class="card xm-stat violet"><div class="card-body"><div class="small text-secondary">Survivant·es suivi·es</div><div class="kpi-value"><?= $nCases ?></div><div class="small text-secondary"><?= count($upcoming) ?> suivi(s) à venir</div></div></div></div>
</div>

<div class="row g-4">
	<div class="col-xl-8">
		<div class="card mb-4"><div class="card-body">
			<form method="get" class="row g-2 align-items-end">
				<div class="col-md-5"><label class="form-label small mb-1" for="sCase">Frise d’un dossier</label><input class="form-control form-control-sm" id="sCase" name="case" list="caseCodes" value="<?= e($selCode) ?>" placeholder="VBG-0000"><datalist id="caseCodes"><?php foreach (array_keys($byCase) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
				<div class="col-md-4"><label class="form-label small mb-1" for="sQ">Filtrer la liste</label><input class="form-control form-control-sm" id="sQ" name="q" value="<?= e(xa_get('q')) ?>" placeholder="Étape, pair…"></div>
				<div class="col-md-3 d-flex gap-2"><button class="btn btn-primary btn-sm flex-fill">Afficher</button><a class="btn btn-outline-primary btn-sm" href="?<?= e(xa_qs(['export' => 'csv'])) ?>" title="Export CSV"><i class="bi bi-filetype-csv"></i></a></div>
			</form>
		</div></div>

		<?php if ($selCode): $cc = $cases[$selCode] ?? null; ?>
		<div class="card mb-4">
			<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Frise de suivi — <?= e($selCode) ?></h2><?php if ($cc): ?><a class="small" href="fiches.php?case=<?= e($selCode) ?>">Fiche de référence</a><?php endif; ?></div>
			<div class="card-body">
				<?php if ($cc): ?><p class="small text-secondary mb-3"><?= e($cc['kind']) ?> · <?= e($cc['sexe']) ?>, <?= e($cc['age']) ?> ans · <?= e($cc['population']) ?> · <?= e($cc['commune']) ?> · accueillie le <?= e(date('d/m/Y', strtotime($cc['date']))) ?></p>
				<?php else: ?><div class="alert alert-warning">Dossier inconnu dans le registre.</div><?php endif; ?>
				<?php if (!$timeline): ?><p class="text-secondary">Aucune étape enregistrée pour ce dossier.</p><?php endif; ?>
				<ul class="list-unstyled mb-0 ms-2 border-start border-2 ps-3">
				<?php foreach ($timeline as $s): $late = $s['outcome'] !== 'Réalisé' && $s['next'] !== '' && $s['next'] < $today; ?>
					<li class="mb-3 position-relative">
						<span class="position-absolute rounded-circle bg-<?= $late ? 'danger' : $oc[$s['outcome']] ?? 'primary' ?>" style="width:12px;height:12px;left:-1.15rem;top:.35rem"></span>
						<div class="d-flex flex-wrap justify-content-between gap-2">
							<div><span class="fw-semibold"><?= e($s['step']) ?></span> <span class="badge bg-<?= $oc[$s['outcome']] ?? 'secondary' ?> <?= ($oc[$s['outcome']] ?? '') === 'warning' ? 'text-dark' : '' ?>"><?= e($s['outcome']) ?></span> <?php if ($late): ?><span class="badge bg-danger">En retard</span><?php endif; ?></div>
							<span class="small text-secondary"><?= e(date('d/m/Y', strtotime($s['date']))) ?> · <?= e($s['by']) ?></span>
						</div>
						<?php if ($s['comment']): ?><div class="small"><?= e($s['comment']) ?></div><?php endif; ?>
						<?php if ($s['next']): ?><div class="small <?= $late ? 'text-danger fw-semibold' : 'text-secondary' ?>"><i class="bi bi-calendar-event me-1"></i>Prochain suivi : <?= e(date('d/m/Y', strtotime($s['next']))) ?></div><?php endif; ?>
						<?php if ($s['outcome'] !== 'Réalisé'): ?><form method="post" class="mt-1"><?= csrf_field() ?><input type="hidden" name="action" value="done"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-outline-success py-0">Marquer réalisé</button></form><?php endif; ?>
					</li>
				<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php endif; ?>

		<div class="card">
			<div class="card-header"><h2 class="h6 mb-0">Toutes les étapes (<?= count($list) ?>)</h2></div>
			<div class="xm-table-wrap"><table class="table table-hover align-middle mb-0">
				<thead><tr><th>Dossier</th><th>Étape</th><th>Date</th><th>Par</th><th>Résultat</th><th>Prochaine</th></tr></thead><tbody>
				<?php foreach ($list as $s): $late = $s['outcome'] !== 'Réalisé' && $s['next'] !== '' && $s['next'] < $today; ?>
					<tr class="<?= $late ? 'table-danger' : '' ?>">
						<td><a href="?case=<?= e($s['case_code']) ?>" class="fw-semibold"><?= e($s['case_code']) ?></a></td><td><?= e($s['step']) ?></td><td class="text-nowrap"><?= e(date('d/m/Y', strtotime($s['date']))) ?></td><td class="small"><?= e($s['by']) ?></td>
						<td><span class="badge bg-<?= $oc[$s['outcome']] ?? 'secondary' ?> <?= ($oc[$s['outcome']] ?? '') === 'warning' ? 'text-dark' : '' ?>"><?= e($s['outcome']) ?></span></td>
						<td class="text-nowrap small <?= $late ? 'text-danger fw-semibold' : '' ?>"><?= $s['next'] ? e(date('d/m/Y', strtotime($s['next']))) : '—' ?><?= $late ? ' <i class="bi bi-exclamation-triangle-fill"></i>' : '' ?></td>
					</tr>
				<?php endforeach; if (!$list): ?><tr><td colspan="6" class="text-center text-secondary py-3">Aucun résultat.</td></tr><?php endif; ?>
				</tbody></table></div>
		</div>
	</div>

	<div class="col-xl-4">
		<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0">Ajouter une étape de suivi</h2></div><div class="card-body">
			<form method="post" data-xm-validate novalidate>
				<?= csrf_field() ?>
				<div class="mb-2"><label class="form-label" for="aCase">Code dossier</label><input class="form-control" id="aCase" name="case_code" list="allCodes" required data-rule="case_code" value="<?= e($selCode) ?>" placeholder="VBG-0000"><datalist id="allCodes"><?php foreach (array_keys($cases) as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
				<div class="mb-2"><label class="form-label" for="aStep">Étape</label><select class="form-select" id="aStep" name="step" required><?php foreach ($steps as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
				<div class="row g-2 mb-2"><div class="col-6"><label class="form-label" for="aDate">Date</label><input type="date" class="form-control" id="aDate" name="date" required data-rule="date_not_future" max="<?= $today ?>" value="<?= $today ?>"></div>
				<div class="col-6"><label class="form-label" for="aOut">Résultat</label><select class="form-select" id="aOut" name="outcome"><?php foreach ($outcomes as $o): ?><option><?= e($o) ?></option><?php endforeach; ?></select></div></div>
				<div class="mb-2"><label class="form-label" for="aBy">Par</label><input class="form-control" id="aBy" name="by" required value="<?= e(x_user()['name']) ?>"></div>
				<div class="mb-2"><label class="form-label" for="aNext">Prochaine date de suivi</label><input type="date" class="form-control" id="aNext" name="next" min="<?= $today ?>"></div>
				<div class="mb-3"><label class="form-label" for="aCom">Commentaire</label><textarea class="form-control" id="aCom" name="comment" rows="2" maxlength="1000"></textarea></div>
				<button class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>Enregistrer</button>
			</form>
		</div></div>

		<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>En retard (<?= count($overdue) ?>)</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach (array_slice($overdue, 0, 8) as $s): ?><li class="list-group-item d-flex justify-content-between"><a href="?case=<?= e($s['case_code']) ?>"><?= e($s['case_code']) ?></a><span class="small text-danger"><?= e($s['step']) ?> · <?= e(date('d/m', strtotime($s['next']))) ?></span></li><?php endforeach; ?>
				<?php if (!$overdue): ?><li class="list-group-item text-secondary">Aucun retard.</li><?php endif; ?>
			</ul></div>

		<div class="card"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-calendar3 me-1"></i>Prochains suivis</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach (array_slice($upcoming, 0, 8) as $s): $dt = strtotime($s['next']); ?>
				<li class="list-group-item d-flex align-items-center gap-3"><div class="text-center border rounded px-2 py-1" style="min-width:52px"><div class="fw-bold lh-1"><?= date('d', $dt) ?></div><div class="small text-secondary"><?= ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int)date('n', $dt) - 1] ?></div></div><div><a href="?case=<?= e($s['case_code']) ?>" class="fw-semibold"><?= e($s['case_code']) ?></a><div class="small text-secondary"><?= e($s['step']) ?> · <?= e($s['by']) ?></div></div></li>
				<?php endforeach; ?>
				<?php if (!$upcoming): ?><li class="list-group-item text-secondary">Aucun suivi planifié. Ajoutez une prochaine date lors d’une étape.</li><?php endif; ?>
			</ul></div>
	</div>
</div>
<?php include __DIR__ . '/../partials/admin-bottom.php';
