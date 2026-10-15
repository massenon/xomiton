<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$DEF = [1];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect('pages.php', 'danger', 'Session expirée, veuillez réessayer.');
	$pg = store_find('pages', $_POST['id'] ?? 0);
	if ($pg && ($_POST['action'] ?? '') === 'follow') {
		$on = x_set_toggle('pages', $pg['id'], $DEF);
		x_log('Page', ($on ? 'Abonnement : ' : 'Désabonnement : ') . $pg['name']);
		x_redirect('pages.php' . (!empty($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''), 'success', $on ? 'Vous suivez maintenant « ' . $pg['name'] . ' ».' : 'Vous ne suivez plus « ' . $pg['name'] . ' ».');
	}
}
$pageTitle = 'Pages';
$nav = 'pages';
$q = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? '';
$all = store_all('pages');
$cats = array_values(array_unique(array_column($all, 'category')));
$pages = array_values(array_filter($all, function ($p) use ($q, $cat) {
	if ($cat !== '' && $p['category'] !== $cat) return false;
	return $q === '' || mb_stripos($p['name'] . ' ' . $p['desc'], $q) !== false;
}));
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
							<div><h1 class="h4 mb-1">Pages</h1><p class="small text-secondary mb-0">Suivez les organisations, cliniques et réseaux partenaires de Xomiton.</p></div>
							<a class="btn btn-primary mt-3 mt-sm-0" href="create-page.php"><i class="bi bi-plus-circle me-1"></i>Créer une page</a>
						</div>
						<hr>
						<div class="d-md-flex justify-content-between gap-3 align-items-center">
							<div class="d-flex flex-wrap gap-2 mb-3 mb-md-0">
								<a class="btn btn-sm <?= $cat === '' ? 'btn-primary' : 'btn-light' ?>" href="?">Toutes</a>
								<?php foreach ($cats as $c): ?><a class="btn btn-sm <?= $cat === $c ? 'btn-primary' : 'btn-light' ?>" href="?cat=<?= urlencode($c) ?>"><?= e($c) ?></a><?php endforeach; ?>
							</div>
							<form method="get" role="search"><?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
								<div class="input-group"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une page…" aria-label="Rechercher"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button></div>
							</form>
						</div>
					</div>
					<div class="row g-4">
						<?php foreach ($pages as $p): $f = x_set_has('pages', $p['id'], $DEF); ?>
						<div class="col-sm-6 col-xl-4">
							<div class="card h-100 overflow-hidden xm-card-hover">
								<div class="xm-ratio-16x9"><img class="xm-img-cover" src="<?= img($p['image']) ?>" alt=""></div>
								<div class="card-body">
									<span class="xm-chip mb-2"><?= e($p['category']) ?></span>
									<h2 class="h6 mb-1"><?= e($p['name']) ?></h2>
									<p class="small text-secondary mb-2"><?= e($p['desc']) ?></p>
									<p class="small mb-0"><i class="bi bi-people me-1"></i><?= number_format((int)$p['followers'] + ($f && !in_array((int)$p['id'], $DEF, true) ? 1 : 0), 0, ',', ' ') ?> abonné·es</p>
								</div>
								<div class="card-footer bg-transparent border-0 pt-0">
									<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="follow"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
										<button class="btn btn-sm w-100 <?= $f ? 'btn-success-soft' : 'btn-primary-soft' ?>" type="submit"><i class="bi bi-<?= $f ? 'check2' : 'plus-lg' ?> me-1"></i><?= $f ? 'Abonné·e' : 'Suivre la page' ?></button></form>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
						<?php if (!$pages): ?><div class="col-12"><div class="card card-body text-center text-secondary">Aucune page trouvée.</div></div><?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
