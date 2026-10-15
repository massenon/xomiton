<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$DEF = [1, 4];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect('groups.php', 'danger', 'Session expirée, veuillez réessayer.');
	$act = $_POST['action'] ?? '';
	if ($act === 'toggle') {
		$g = store_find('groups', $_POST['id'] ?? 0);
		if ($g) {
			$on = x_set_toggle('groups', $g['id'], $DEF);
			x_log('Groupe', ($on ? 'Adhésion : ' : 'Départ : ') . $g['name']);
			$back = (string)($_POST['back'] ?? '');
			if (!preg_match('/^group-details\.php\?id=\d+$/', $back)) $back = 'groups.php';
			x_redirect($back, 'success', $on ? 'Vous avez rejoint « ' . $g['name'] . ' ».' : 'Vous avez quitté « ' . $g['name'] . ' ».');
		}
	}
	if ($act === 'create') {
		$name = trim($_POST['name'] ?? '');
		if (mb_strlen($name) < 3) x_redirect('groups.php', 'danger', 'Le nom du groupe doit contenir au moins 3 caractères.');
		$imgs = ['hero-09.jpg', 'hero-10.jpg', 'hero-12.jpg', 'hero-13.jpg', 'hero-04.jpg', 'hero-05.jpg'];
		$row = store_append('groups', [
			'name' => $name, 'desc' => trim($_POST['desc'] ?? ''), 'members' => 1,
			'privacy' => ($_POST['privacy'] ?? '') === 'Public' ? 'Public' : 'Privé',
			'image' => $imgs[array_rand($imgs)], 'zone' => $_POST['zone'] ?: 'National', 'owner' => x_user()['id'],
		]);
		x_set_toggle('groups', $row['id'], $DEF);
		x_log('Groupe', 'Création : ' . $name);
		x_redirect('group-details.php?id=' . $row['id'], 'success', 'Groupe créé. Invitez vos pair·es !');
	}
}

$pageTitle = 'Groupes';
$pageDesc = 'Des cercles d’entraide Xomiton pour échanger en toute sécurité : groupes publics et privés, par zone ou à l’échelle nationale.';
$nav = 'groupes';
$q = trim($_GET['q'] ?? '');
$tab = $_GET['tab'] ?? 'all';
$groups = array_values(array_filter(store_all('groups'), function ($g) use ($q, $tab, $DEF) {
	if ($q !== '' && mb_stripos($g['name'] . ' ' . $g['desc'] . ' ' . $g['zone'], $q) === false) return false;
	if ($tab === 'mine' && !x_set_has('groups', $g['id'], $DEF)) return false;
	return true;
}));
[$groups, $moreBtn] = x_paginate($groups, 6);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>
					<div class="card card-body">
						<div class="d-sm-flex justify-content-between align-items-center">
							<div>
								<h1 class="h4 mb-1">Groupes</h1>
								<p class="small text-secondary mb-0">Des cercles d’entraide pour échanger en toute sécurité.</p>
							</div>
							<button class="btn btn-primary mt-3 mt-sm-0" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateGroup"><i class="bi bi-plus-circle me-1"></i>Créer un groupe</button>
						</div>
						<hr>
						<div class="d-sm-flex justify-content-between align-items-center gap-3">
							<ul class="nav nav-pills gap-2 mb-3 mb-sm-0">
								<li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'all' ? 'active' : 'bg-light' ?>" href="?tab=all<?= $q ? '&q=' . urlencode($q) : '' ?>">Tous les groupes</a></li>
								<li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'mine' ? 'active' : 'bg-light' ?>" href="?tab=mine<?= $q ? '&q=' . urlencode($q) : '' ?>">Mes groupes</a></li>
							</ul>
							<form method="get" role="search">
								<input type="hidden" name="tab" value="<?= e($tab) ?>">
								<div class="input-group"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un groupe…" aria-label="Rechercher"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button></div>
							</form>
						</div>
					</div>

					<div class="row g-4" id="liste">
						<?php foreach ($groups as $g): $in = x_set_has('groups', $g['id'], $DEF); ?>
						<div class="col-sm-6 col-xl-4">
							<div class="card h-100 overflow-hidden xm-card-hover">
								<a href="group-details.php?id=<?= (int)$g['id'] ?>"><div class="xm-ratio-16x9"><img class="xm-img-cover" src="<?= img(x_group_cover($g)) ?>" alt="Couverture du groupe : <?= e($g['name']) ?>" title="<?= e($g['name']) ?>" loading="lazy" width="640" height="360"></div></a>
								<div class="card-body">
									<span class="badge bg-<?= $g['privacy'] === 'Public' ? 'success' : 'secondary' ?> bg-opacity-10 text-<?= $g['privacy'] === 'Public' ? 'success' : 'secondary' ?> mb-2"><i class="bi bi-<?= $g['privacy'] === 'Public' ? 'globe2' : 'lock' ?> me-1"></i><?= e($g['privacy']) ?></span>
									<h2 class="h6 mb-1"><a href="group-details.php?id=<?= (int)$g['id'] ?>"><?= e($g['name']) ?></a></h2>
									<p class="small text-secondary mb-2"><?= e($g['desc']) ?></p>
									<p class="small mb-0"><i class="bi bi-people me-1"></i><?= (int)$g['members'] + ($in && !in_array((int)$g['id'], $DEF, true) && ($g['owner'] ?? 0) != x_user()['id'] ? 1 : 0) ?> membres · <i class="bi bi-geo-alt me-1"></i><?= e($g['zone']) ?></p>
								</div>
								<div class="card-footer bg-transparent border-0 pt-0">
									<form method="post" class="d-flex gap-2">
										<?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
										<button class="btn btn-sm flex-grow-1 <?= $in ? 'btn-success-soft' : 'btn-primary-soft' ?>" type="submit"><i class="bi bi-<?= $in ? 'check2' : 'plus-lg' ?> me-1"></i><?= $in ? 'Membre' : 'Rejoindre' ?></button>
										<a class="btn btn-sm btn-light" href="group-details.php?id=<?= (int)$g['id'] ?>">Voir</a>
									</form>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
						<?php if (!$groups): ?><div class="col-12"><div class="card card-body text-center text-secondary">Aucun groupe trouvé.</div></div><?php endif; ?>
					</div>
					<?= $moreBtn ?>
				</div>
			</div>
		</div>
	</main>

	<div class="modal fade" id="modalCreateGroup" tabindex="-1" aria-labelledby="modalCreateGroupLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<form method="post" data-xm-validate>
					<?= csrf_field() ?><input type="hidden" name="action" value="create">
					<div class="modal-header"><h5 class="modal-title" id="modalCreateGroupLabel">Créer un groupe</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
					<div class="modal-body">
						<div class="mb-3"><label class="form-label" for="gname">Nom du groupe</label><input class="form-control" id="gname" name="name" required minlength="3" maxlength="80" placeholder="Ex. : Cercle d’entraide de Bohicon"></div>
						<div class="mb-3"><label class="form-label" for="gdesc">Description</label><textarea class="form-control" id="gdesc" name="desc" rows="3" maxlength="300"></textarea></div>
						<div class="row g-3">
							<div class="col-sm-6"><label class="form-label" for="gpriv">Confidentialité</label><select class="form-select" id="gpriv" name="privacy"><option>Privé</option><option>Public</option></select></div>
							<div class="col-sm-6"><label class="form-label" for="gzone">Zone</label><select class="form-select" id="gzone" name="zone"><option value="">National</option><?php foreach (array_keys(x_zones()) as $z): ?><option><?= e($z) ?></option><?php endforeach; ?></select></div>
						</div>
					</div>
					<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Créer le groupe</button></div>
				</form>
			</div>
		</div>
	</div>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
