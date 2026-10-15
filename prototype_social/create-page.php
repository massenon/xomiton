<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$cats = ['Organisation', 'Santé', 'Juridique', 'Réseau', 'Éducation', 'Autonomisation'];
$err = null; $old = ['name' => '', 'category' => 'Organisation', 'desc' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$old = ['name' => trim($_POST['name'] ?? ''), 'category' => $_POST['category'] ?? 'Organisation', 'desc' => trim($_POST['desc'] ?? '')];
	if (!csrf_ok()) $err = 'Session expirée, veuillez réessayer.';
	elseif (mb_strlen($old['name']) < 3) $err = 'Le nom de la page doit contenir au moins 3 caractères.';
	elseif (!in_array($old['category'], $cats, true)) $err = 'Catégorie invalide.';
	else {
		$imgs = ['hero-09.jpg', 'hero-10.jpg', 'hero-12.jpg', 'hero-13.jpg', 'hero-04.jpg'];
		$row = store_append('pages', ['name' => $old['name'], 'category' => $old['category'], 'followers' => 0, 'image' => $imgs[array_rand($imgs)], 'desc' => $old['desc'], 'owner' => x_user()['id']]);
		x_set_toggle('pages', $row['id'], [1]);
		x_log('Page', 'Création : ' . $old['name']);
		x_redirect('pages.php', 'success', 'Votre page « ' . $old['name'] . ' » a été créée.');
	}
}
$pageTitle = 'Créer une page';
$nav = 'pages';
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
				<div class="col-md-8 col-lg-6 vstack gap-4">
					<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
					<div class="card">
						<div class="card-header border-0 pb-0"><h1 class="h5 mb-0">Créer une page</h1></div>
						<div class="card-body">
							<form method="post" data-xm-validate>
								<?= csrf_field() ?>
								<div class="mb-3"><label class="form-label" for="pname">Nom de la page</label><input class="form-control" id="pname" name="name" required minlength="3" maxlength="80" value="<?= e($old['name']) ?>" placeholder="Ex. : Clinique amie de Parakou"></div>
								<div class="mb-3"><label class="form-label" for="pcat">Catégorie</label>
									<select class="form-select" id="pcat" name="category"><?php foreach ($cats as $c): ?><option <?= $old['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
								<div class="mb-3"><label class="form-label" for="pdesc">Description</label><textarea class="form-control" id="pdesc" name="desc" rows="4" maxlength="400" placeholder="Présentez votre structure et ses services…"><?= e($old['desc']) ?></textarea></div>
								<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>Les pages sont vérifiées par l’équipe de modération avant d’être mises en avant.</div>
								<div class="d-flex gap-2 justify-content-end"><a class="btn btn-light" href="pages.php">Annuler</a><button class="btn btn-primary" type="submit">Créer la page</button></div>
							</form>
						</div>
					</div>
				</div>
				<?= x_right_column() ?>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
