<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Annuaire dynamique'; $adminPage = 'annuaire';
$roles = array_values(array_unique(array_merge(x_roles_annuaire(), ['Responsable de réseau', 'Personnel identifié'])));

function ann_phone_ok($p) { return $p === '' || (bool)preg_match('/^(\+229|00229)?\s?0?1\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{2}$/', trim($p)) || (bool)preg_match('/^(\+229)?\s?\d{8,10}$/', preg_replace('/\s/', '', $p)); }
/** Même logique que route_alert() de api.php, paramétrée (rôle voulu, zone). */
function ann_route($needRole, $zone) {
    $ann = array_filter(store_all('annuaire'), fn($r) => !empty($r['active']));
    if ($needRole) foreach ($ann as $r) if ($r['role'] === $needRole && ($r['zone'] ?? '') === $zone) return [$r, $needRole . ' de la zone'];
    foreach ($ann as $r) if ($r['role'] === 'Point focal' && ($r['zone'] ?? '') === $zone) return [$r, 'Point focal de la zone (repli)'];
    foreach ($ann as $r) if ($r['role'] === 'Point focal') return [$r, 'Point focal national (repli)'];
    foreach ($ann as $r) return [$r, 'Premier contact actif (repli)'];
    return [null, 'Aucun contact actif'];
}

if (($_GET['export'] ?? '') === 'csv') {
    xa_csv('xomiton-annuaire-' . date('Ymd') . '.csv', ['Nom', 'Rôle', 'Organisation', 'Téléphone', 'E-mail', 'WhatsApp', 'Département', 'Commune', 'Disponibilité', 'Actif'],
        array_map(fn($r) => [$r['name'], $r['role'], $r['org'] ?? '', $r['phone'] ?? '', $r['email'] ?? '', $r['whatsapp'] ?? '', $r['zone'] ?? '', $r['commune'] ?? '', $r['availability'] ?? '', !empty($r['active']) ? 'oui' : 'non'], store_all('annuaire')));
}
if (($_GET['export'] ?? '') === 'modele') {
    xa_csv('modele-import-annuaire.csv', ['Nom', 'Rôle', 'Organisation', 'Téléphone', 'E-mail', 'WhatsApp', 'Département', 'Commune', 'Disponibilité', 'Actif'],
        [['Exemple NOM Prénom', 'Juriste', 'Barreau', '+229 01 97 00 00 00', 'exemple@mail.bj', '+229 01 97 00 00 00', 'Littoral', 'Cotonou', '9h-17h', 'oui']]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'save') {
        $name = xa_post('name'); $role = xa_post('role'); $phone = xa_post('phone'); $email = xa_post('email');
        if (mb_strlen($name) < 3 || !in_array($role, $roles, true)) xa_back('danger', 'Nom (3 caractères min.) et rôle requis.');
        if (!ann_phone_ok($phone) || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) xa_back('danger', 'Téléphone ou e-mail invalide.');
        $row = ['name' => $name, 'role' => $role, 'org' => xa_post('org'), 'phone' => $phone, 'email' => $email, 'whatsapp' => xa_post('whatsapp') ?: $phone, 'zone' => xa_post('zone'), 'commune' => xa_post('commune'), 'availability' => xa_post('availability'), 'active' => !empty($_POST['active'])];
        if (xa_post('id') !== '') { store_update('annuaire', xa_post('id'), $row); x_log('Annuaire', 'Contact modifié : ' . $name); xa_back('success', 'Contact mis à jour.'); }
        store_append('annuaire', $row); x_log('Annuaire', 'Contact ajouté : ' . $name); xa_back('success', 'Contact ajouté à l’annuaire.');
    }
    if ($do === 'toggle') { $r = store_find('annuaire', xa_post('id')); if ($r) { store_update('annuaire', $r['id'], ['active' => empty($r['active'])]); x_log('Annuaire', ($r['active'] ? 'Désactivation : ' : 'Activation : ') . $r['name']); } xa_back('success', 'Statut du contact modifié.'); }
    if ($do === 'delete') { $r = store_find('annuaire', xa_post('id')); store_delete('annuaire', xa_post('id')); x_log('Annuaire', 'Contact supprimé : ' . ($r['name'] ?? '')); xa_back('success', 'Contact supprimé.'); }
    if ($do === 'import') {
        $f = $_FILES['csv']['tmp_name'] ?? '';
        if (!$f || !is_uploaded_file($f)) xa_back('danger', 'Sélectionnez un fichier CSV.');
        $raw = file_get_contents($f); $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $delim = substr_count(strtok($raw, "\n"), ';') >= substr_count(strtok($raw, "\n"), ',') ? ';' : ',';
        $lines = array_map(fn($l) => str_getcsv($l, $delim), preg_split('/\r\n|\n|\r/', trim($raw)));
        array_shift($lines); $ok = 0; $ko = 0;
        foreach ($lines as $c) {
            $c = array_pad($c, 10, '');
            if (mb_strlen(trim($c[0])) < 3 || !in_array(trim($c[1]), $roles, true) || !ann_phone_ok($c[3]) || ($c[4] !== '' && !filter_var($c[4], FILTER_VALIDATE_EMAIL))) { $ko++; continue; }
            store_append('annuaire', ['name' => trim($c[0]), 'role' => trim($c[1]), 'org' => $c[2], 'phone' => $c[3], 'email' => $c[4], 'whatsapp' => $c[5] ?: $c[3], 'zone' => $c[6], 'commune' => $c[7], 'availability' => $c[8], 'active' => !in_array(strtolower(trim($c[9])), ['non', '0', 'false'], true)]);
            $ok++;
        }
        x_log('Annuaire', "Import CSV : $ok ajoutés, $ko rejetés");
        xa_back($ok ? 'success' : 'warning', "Import terminé : $ok contact(s) ajouté(s), $ko ligne(s) rejetée(s) par les règles de validation.");
    }
    xa_back('danger', 'Action inconnue.');
}

$all = store_all('annuaire');
$fr = $_GET['role'] ?? ''; $fz = $_GET['zone'] ?? ''; $fs = $_GET['st'] ?? ''; $fq = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($all, fn($r) => ($fr === '' || $r['role'] === $fr) && ($fz === '' || ($r['zone'] ?? '') === $fz) && ($fs === '' || ($fs === 'actif') === !empty($r['active'])) && ($fq === '' || stripos($r['name'] . ' ' . ($r['org'] ?? ''), $fq) !== false)));
$cnt = array_count_values(array_column($all, 'role'));
$testZone = $_GET['tz'] ?? 'Littoral'; $testNeed = $_GET['tn'] ?? 'soutien_juridique';
$needRoles = ['soutien_juridique' => 'Juriste', 'soutien_psychologique' => 'Psychologue', 'soutien_medical' => 'Gynécologue', 'sos' => null];
$needLbl = ['soutien_juridique' => 'Soutien juridique', 'soutien_psychologique' => 'Soutien psychologique', 'soutien_medical' => 'Soutien médical', 'sos' => 'SOS / dénonciation'];
[$testTarget, $testWhy] = ann_route($needRoles[$testNeed] ?? null, $testZone);

$adminActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mContact"><i class="bi bi-plus-lg me-1"></i>Contact</button>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Contacts', count($all), 'bi-person-lines-fill') ?>
	<?= xa_kpi('Actifs', count(array_filter($all, fn($r) => !empty($r['active']))), 'bi-check-circle', 'success') ?>
	<?= xa_kpi('Points focaux', $cnt['Point focal'] ?? 0, 'bi-geo-alt', 'violet') ?>
	<?= xa_kpi('Juristes · Psy · Gynéco', ($cnt['Juriste'] ?? 0) . ' · ' . ($cnt['Psychologue'] ?? 0) . ' · ' . ($cnt['Gynécologue'] ?? 0), 'bi-heart-pulse', 'warning') ?>
</div>

<div class="card mb-4">
	<div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
		<h2 class="h6 mb-0">Personnes ciblées par le routage des alertes</h2>
		<div class="d-flex gap-2 flex-wrap">
			<a class="btn btn-outline-secondary btn-sm" href="?export=csv"><i class="bi bi-download me-1"></i>Exporter CSV</a>
			<button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#mImport"><i class="bi bi-upload me-1"></i>Importer CSV</button>
		</div>
	</div>
	<div class="card-body border-bottom">
		<form class="row g-2" method="get">
			<div class="col-12 col-md-3"><input type="search" class="form-control form-control-sm" name="q" value="<?= e($fq) ?>" placeholder="Nom, organisation…"></div>
			<div class="col-6 col-md-3"><select name="role" class="form-select form-select-sm"><option value="">Tous les rôles</option><?php foreach ($roles as $r): ?><option <?= $fr === $r ? 'selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-3"><select name="zone" class="form-select form-select-sm"><?= xa_zone_options($fz, true) ?></select></div>
			<div class="col-6 col-md-2"><select name="st" class="form-select form-select-sm"><option value="">Tous statuts</option><option value="actif" <?= $fs === 'actif' ? 'selected' : '' ?>>Actifs</option><option value="inactif" <?= $fs === 'inactif' ? 'selected' : '' ?>>Inactifs</option></select></div>
			<div class="col-6 col-md-1 d-grid"><button class="btn btn-sm btn-primary-soft">OK</button></div>
		</form>
	</div>
	<div class="xm-table-wrap">
		<table class="table table-hover align-middle mb-0">
			<thead><tr><th>Contact</th><th>Rôle</th><th>Zone</th><th>Coordonnées</th><th>Actif</th><th class="text-end">Actions</th></tr></thead>
			<tbody>
			<?php foreach ($rows as $r): ?>
				<tr class="<?= empty($r['active']) ? 'text-secondary' : '' ?>">
					<td><div class="fw-semibold"><?= e($r['name']) ?></div><div class="small text-secondary"><?= e($r['org'] ?? '') ?></div></td>
					<td><span class="badge bg-primary-subtle text-primary-emphasis"><?= e($r['role']) ?></span></td>
					<td><?= e($r['zone'] ?? '') ?><div class="small text-secondary"><?= e($r['commune'] ?? '') ?></div></td>
					<td class="small"><i class="bi bi-telephone me-1"></i><?= e($r['phone'] ?? '') ?><br><i class="bi bi-envelope me-1"></i><?= e($r['email'] ?? '') ?><br><span class="text-secondary"><?= e($r['availability'] ?? '') ?></span></td>
					<td><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
						<div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" <?= !empty($r['active']) ? 'checked' : '' ?> onchange="this.form.submit()" aria-label="Activer ou désactiver <?= e($r['name']) ?>"></div></form></td>
					<td class="text-end text-nowrap">
						<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mContact" data-fill='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>' title="Modifier"><i class="bi bi-pencil"></i></button>
						<form method="post" class="d-inline" data-confirm="Supprimer ce contact ?"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form>
					</td>
				</tr>
			<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="text-center text-secondary py-4">Aucun contact ne correspond aux filtres.</td></tr><?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="card mb-4">
	<div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-signpost-split me-2"></i>Aperçu du routage des alertes</h2></div>
	<div class="card-body">
		<p class="small text-secondary">Règle appliquée à chaque alerte (identique à <code>api.php</code>) : 1. le rôle correspondant au besoin exprimé (juriste, psychologue, gynécologue) dans la zone de la personne ; 2. à défaut, le point focal de la zone ; 3. le point focal national ; 4. le premier contact actif. Seuls les contacts <strong>actifs</strong> sont ciblés.</p>
		<form class="row g-2 align-items-end mb-3" method="get">
			<div class="col-12 col-md-4"><label class="form-label small">Type de besoin</label><select name="tn" class="form-select form-select-sm"><?php foreach ($needLbl as $k => $l): ?><option value="<?= $k ?>" <?= $testNeed === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
			<div class="col-12 col-md-4"><label class="form-label small">Zone de l’alerte</label><select name="tz" class="form-select form-select-sm"><?= xa_zone_options($testZone) ?></select></div>
			<div class="col-12 col-md-2 d-grid"><button class="btn btn-sm btn-primary">Simuler</button></div>
		</form>
		<div class="alert alert-info d-flex gap-3 align-items-center">
			<i class="bi bi-send-check fs-3"></i>
			<div><?php if ($testTarget): ?><strong><?= e($testTarget['name']) ?></strong> · <?= e($testTarget['role']) ?> (<?= e($testTarget['zone'] ?? '') ?>)<br><span class="small"><?= e($testWhy) ?> — <?= e($testTarget['phone'] ?? '') ?></span><?php else: ?>Aucun contact actif : l’alerte resterait non routée.<?php endif; ?></div>
		</div>
		<div class="xm-table-wrap"><table class="table table-sm align-middle mb-0">
			<thead><tr><th>Département</th><?php foreach ($needLbl as $l): ?><th><?= e($l) ?></th><?php endforeach; ?></tr></thead>
			<tbody>
			<?php foreach (array_keys(x_zones()) as $z): ?>
				<tr><td class="fw-semibold"><?= e($z) ?></td>
				<?php foreach ($needRoles as $k => $nr): [$t, $why] = ann_route($nr, $z); $fallback = str_contains($why, 'repli'); ?>
					<td class="small"><?= $t ? e($t['name']) : '<span class="text-danger">—</span>' ?><?= $fallback ? ' <span class="badge bg-warning text-dark" title="' . e($why) . '">repli</span>' : '' ?></td>
				<?php endforeach; ?></tr>
			<?php endforeach; ?>
			</tbody></table></div>
	</div>
</div>

<!-- Modale contact -->
<div class="modal fade" id="mContact" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" data-xm-validate data-fill-form>
	<?= csrf_field() ?><input type="hidden" name="do" value="save"><input type="hidden" name="id">
	<div class="modal-header"><h5 class="modal-title" data-title-new="Nouveau contact" data-title-edit="Modifier le contact">Nouveau contact</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-md-6"><label class="form-label">Nom *</label><input class="form-control" name="name" required></div>
		<div class="col-md-6"><label class="form-label">Rôle *</label><select class="form-select" name="role" required><?php foreach ($roles as $r): ?><option><?= e($r) ?></option><?php endforeach; ?></select></div>
		<div class="col-md-6"><label class="form-label">Organisation</label><input class="form-control" name="org"></div>
		<div class="col-md-6"><label class="form-label">Disponibilité</label><input class="form-control" name="availability" placeholder="24/7, 9h-17h"></div>
		<div class="col-md-6"><label class="form-label">Téléphone *</label><input class="form-control" name="phone" required data-rule="phone_bj" placeholder="+229 01 97 38 88 47"></div>
		<div class="col-md-6"><label class="form-label">E-mail</label><input class="form-control" name="email" data-rule="email" type="text"></div>
		<div class="col-md-6"><label class="form-label">WhatsApp</label><input class="form-control" name="whatsapp" data-rule="phone_bj"></div>
		<div class="col-md-3"><label class="form-label">Département</label><select class="form-select" name="zone" data-zone-select="#annCommune"><?= xa_zone_options() ?></select></div>
		<div class="col-md-3"><label class="form-label">Commune</label><select class="form-select" name="commune" id="annCommune"><option value="">—</option></select></div>
		<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="active" value="1" id="annActive" checked><label class="form-check-label" for="annActive">Contact actif (ciblable par le routage des alertes)</label></div></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>

<!-- Modale import -->
<div class="modal fade" id="mImport" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" enctype="multipart/form-data">
	<?= csrf_field() ?><input type="hidden" name="do" value="import">
	<div class="modal-header"><h5 class="modal-title">Importer un fichier CSV</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body">
		<p class="small text-secondary">Colonnes attendues : Nom ; Rôle ; Organisation ; Téléphone ; E-mail ; WhatsApp ; Département ; Commune ; Disponibilité ; Actif. Les lignes qui échouent aux règles de validation (téléphone, e-mail, rôle) sont rejetées. <a href="?export=modele">Télécharger le modèle</a>.</p>
		<input type="file" name="csv" accept=".csv,text/csv" class="form-control" required>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Importer</button></div>
</form></div></div>
<?php
$adminScripts = xa_common_js();
include __DIR__ . '/../partials/admin-bottom.php';
