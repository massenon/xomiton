<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
x_require_pair();
$pageTitle = 'Collecte de données';
$nav = 'collecte';
$zones = x_zones();
$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    if (!csrf_ok()) $errors[] = 'Session expirée, veuillez réessayer.';
    $kind = in_array($_POST['kind'] ?? '', ['VBG', 'VDH'], true) ? $_POST['kind'] : '';
    $date = $_POST['date'] ?? '';
    $zone = $_POST['zone'] ?? ''; $commune = $_POST['commune'] ?? '';
    $age = $_POST['age'] ?? '';
    $type = $_POST['type'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    /* règles de validation internes (côté serveur, miroir des règles du navigateur) */
    if (!$kind) $errors[] = 'Choisissez le type de dossier (VBG ou VDH).';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) > time()) $errors[] = 'La date doit être valide et non postérieure à aujourd’hui.';
    if (!isset($zones[$zone])) $errors[] = 'Département invalide.';
    elseif ($commune !== '' && !in_array($commune, $zones[$zone], true)) $errors[] = 'La commune ne correspond pas au département.';
    if (!in_array($_POST['population'] ?? '', x_populations(), true)) $errors[] = 'Population invalide.';
    if (!in_array($_POST['sexe'] ?? '', x_sexes(), true)) $errors[] = 'Sexe invalide.';
    if (!in_array($age, x_age_ranges(), true)) $errors[] = 'Tranche d’âge invalide.';
    if (!in_array($type, array_column(x_violence_categories(), 0), true)) $errors[] = 'Type de violence invalide.';
    if (mb_strlen($notes) > 0 && mb_strlen($notes) < 20) $errors[] = 'Les notes doivent contenir au moins 20 caractères (ou rester vides).';
    if (!$errors) {
        $ids = array_column(store_all('cases'), 'id'); $n = ($ids ? max($ids) : 0) + 1;
        $row = ['id' => $n, 'code' => 'VBG-' . str_pad($n, 4, '0', STR_PAD_LEFT), 'kind' => $kind, 'date' => $date, 'ts' => strtotime($date),
            'zone' => $zone, 'commune' => $commune, 'population' => $_POST['population'], 'sexe' => $_POST['sexe'], 'age' => $age, 'type' => $type,
            'status' => 'ouvert', 'pair' => x_user()['name'], 'referral' => $_POST['referral'] ?? '', 'notes' => $notes, 'source' => 'web'];
        store_append('cases', $row);
        x_log('Collecte', 'Dossier ' . $row['code']);
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Dossier ' . $row['code'] . ' enregistré avec succès.'];
        header('Location: collecte.php'); exit;
    }
}
$mine = array_values(array_filter(store_all('cases'), fn($c) => ($c['pair'] ?? '') === x_user()['name']));
$last = $mine ?: store_all('cases');
usort($last, fn($a, $b) => ($b['id'] <=> $a['id']));
$last = array_slice($last, 0, 10);
$v = fn($k, $d = '') => e($old[$k] ?? $d);
$sel = fn($k, $val) => (($old[$k] ?? '') === $val) ? 'selected' : '';
$stCls = ['ouvert' => 'warning', 'en_cours' => 'primary', 'clos' => 'success'];
$stLbl = ['ouvert' => 'Ouvert', 'en_cours' => 'En cours', 'clos' => 'Clos'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>
					<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
						<div><h1 class="h3 mb-0">Collecte de données</h1><p class="text-secondary mb-0">Fiche de dossier VBG / VDH, utilisable hors connexion. Ne saisissez aucun nom ni identifiant direct.</p></div>
						<div class="small"><span data-xm-online-state class="badge bg-success">En ligne</span> <span class="ms-2"><i class="bi bi-cloud-arrow-up"></i> <span data-xm-queue-count>0</span> en attente</span></div>
					</div>

					<?php if ($errors): ?><div class="alert alert-danger"><strong>Veuillez corriger :</strong><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

					<form class="card card-body" method="post" id="colForm" data-xm-validate data-xm-offline="collecte" novalidate>
						<?= csrf_field() ?>
						<div class="row g-3">
							<div class="col-md-4">
								<label class="form-label" for="kind">Type de dossier <span class="text-danger">*</span></label>
								<select class="form-select" id="kind" name="kind" required><option value="">Choisir…</option><option <?= $sel('kind', 'VBG') ?> value="VBG">VBG – violences basées sur le genre</option><option <?= $sel('kind', 'VDH') ?> value="VDH">VDH – violations des droits humains</option></select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="date">Date des faits <span class="text-danger">*</span></label>
								<input class="form-control" type="date" id="date" name="date" required data-rule="required|date_not_future" max="<?= date('Y-m-d') ?>" value="<?= $v('date', date('Y-m-d')) ?>">
							</div>
							<div class="col-md-4">
								<label class="form-label" for="type">Type de violence <span class="text-danger">*</span></label>
								<select class="form-select" id="type" name="type" required><option value="">Choisir…</option><?php foreach (x_violence_categories() as $c): ?><option value="<?= e($c[0]) ?>" <?= $sel('type', $c[0]) ?>><?= e($c[1]) ?></option><?php endforeach; ?></select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="zone">Département <span class="text-danger">*</span></label>
								<select class="form-select" id="zone" name="zone" data-zone-select="#commune" required><option value="">Choisir…</option><?php foreach ($zones as $z => $cs): ?><option <?= $sel('zone', $z) ?>><?= e($z) ?></option><?php endforeach; ?></select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="commune">Commune</label>
								<select class="form-select" id="commune" name="commune" data-rule="commune_in_zone"><option value="">—</option><?php if (!empty($old['zone']) && isset($zones[$old['zone']])) foreach ($zones[$old['zone']] as $c): ?><option <?= $sel('commune', $c) ?>><?= e($c) ?></option><?php endforeach; ?></select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="population">Population <span class="text-danger">*</span></label>
								<select class="form-select" id="population" name="population" required><option value="">Choisir…</option><?php foreach (x_populations() as $p): ?><option <?= $sel('population', $p) ?>><?= e($p) ?></option><?php endforeach; ?></select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="sexe">Sexe <span class="text-danger">*</span></label>
								<select class="form-select" id="sexe" name="sexe" required><option value="">Choisir…</option><?php foreach (x_sexes() as $p): ?><option <?= $sel('sexe', $p) ?>><?= e($p) ?></option><?php endforeach; ?></select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="age">Tranche d’âge <span class="text-danger">*</span></label>
								<select class="form-select" id="age" name="age" required><option value="">Choisir…</option><?php foreach (x_age_ranges() as $p): ?><option <?= $sel('age', $p) ?>><?= e($p) ?></option><?php endforeach; ?></select>
							</div>
							<div class="col-md-4">
								<label class="form-label" for="referral">Orientation</label>
								<select class="form-select" id="referral" name="referral"><option value="">Aucune</option><?php foreach (['Point focal', 'Juriste', 'Psychologue', 'Gynécologue', 'Hébergement'] as $p): ?><option <?= $sel('referral', $p) ?>><?= e($p) ?></option><?php endforeach; ?></select>
							</div>
							<div class="col-12">
								<label class="form-label" for="notes">Notes (facultatif, 20 caractères min.)</label>
								<textarea class="form-control" id="notes" name="notes" rows="3" data-rule="min_len_20" placeholder="Résumé non identifiant : contexte, actions réalisées, suite prévue…"><?= $v('notes') ?></textarea>
							</div>
						</div>
						<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
							<div class="small text-secondary"><i class="bi bi-shield-check me-1"></i>Règles de validation actives. Hors connexion, la fiche est mise en file d’attente puis synchronisée automatiquement. Un code VBG-0000 est attribué à l’enregistrement.</div>
							<button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Enregistrer le dossier</button>
						</div>
					</form>

					<div class="card">
						<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h5 mb-0">Derniers dossiers</h2><a class="small" href="espace-pair.php">Espace pair</a></div>
						<div class="xm-table-wrap">
							<table class="table table-hover align-middle mb-0">
								<thead><tr><th>Code</th><th>Type</th><th>Date</th><th>Zone</th><th>Violence</th><th>Statut</th></tr></thead>
								<tbody>
									<?php foreach ($last as $c): ?>
									<tr>
										<td class="fw-semibold"><?= e($c['code']) ?></td>
										<td><span class="badge bg-secondary-subtle text-secondary-emphasis"><?= e($c['kind']) ?></span></td>
										<td><?= e($c['date']) ?></td>
										<td><?= e($c['commune'] ?: $c['zone']) ?></td>
										<td><?= e(x_violence_label($c['type'])) ?></td>
										<td><span class="badge bg-<?= $stCls[$c['status']] ?? 'secondary' ?>"><?= e($stLbl[$c['status']] ?? $c['status']) ?></span></td>
									</tr>
									<?php endforeach; if (!$last): ?><tr><td colspan="6" class="text-center text-secondary py-4">Aucun dossier pour le moment.</td></tr><?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); x_zones_script(); ?>
	<script>
	document.addEventListener('xm:queued', function () { XM.refreshSyncUI(); });
	document.addEventListener('xm:synced', function (e) { if (e.detail && e.detail.sent > 0) location.reload(); });
	document.getElementById('commune').addEventListener('change', function () { XM.validateField(this); });
	XM.rules.commune_in_zone = { test: function () { var z = document.getElementById('zone').value, c = document.getElementById('commune').value; return c === '' || (XM.zones[z] || []).indexOf(c) > -1; }, msg: 'Commune incohérente avec le département.' };
	</script>
</body>
</html>
