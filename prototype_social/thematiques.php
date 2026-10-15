<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Thématiques';
$pageDesc = 'Fiches pédagogiques Xomiton : violences basées sur le genre, droits et lois, hygiène menstruelle, santé mentale, sexualité et autonomie.';
$pageImage = 'covers/th-1.jpg';
$nav = 'thematiques';
$cats = x_thematique_cats();
$all = x_thematiques();
$cat = $_GET['cat'] ?? '';
$q = trim($_GET['q'] ?? '');
$list = array_values(array_filter($all, function ($t) use ($cat, $q) {
    if ($cat !== '' && $t['cat'] !== $cat) return false;
    if ($q !== '' && mb_stripos($t['title'] . ' ' . $t['summary'], $q) === false) return false;
    return true;
}));
[$list, $moreBtn] = x_paginate($list, 6);
$counts = [];
foreach ($all as $t) $counts[$t['cat']] = ($counts[$t['cat']] ?? 0) + 1;
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
						<div class="d-sm-flex justify-content-between align-items-center mb-3">
							<div>
								<h1 class="h4 mb-1">Thématiques</h1>
								<p class="mb-0 small text-secondary"><?= count($all) ?> fiches pédagogiques pour comprendre, se protéger et agir.</p>
							</div>
							<form class="mt-3 mt-sm-0" method="get" role="search">
								<?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
								<div class="input-group">
									<input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une thématique…" aria-label="Rechercher">
									<button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
								</div>
							</form>
						</div>
						<div class="d-flex flex-wrap gap-2">
							<a class="btn btn-sm <?= $cat === '' ? 'btn-primary' : 'btn-light' ?>" href="?<?= $q ? 'q=' . urlencode($q) : '' ?>">Toutes (<?= count($all) ?>)</a>
							<?php foreach ($cats as $k => $c): ?>
							<a class="btn btn-sm <?= $cat === $k ? 'btn-primary' : 'btn-light' ?>" href="?cat=<?= $k ?><?= $q ? '&q=' . urlencode($q) : '' ?>"><i class="bi <?= $c[1] ?> me-1"></i><?= e($c[0]) ?> (<?= $counts[$k] ?? 0 ?>)</a>
							<?php endforeach; ?>
						</div>
					</div>

					<div class="row g-4" id="liste">
						<?php foreach ($list as $t): $c = $cats[$t['cat']]; ?>
						<div class="col-sm-6 col-xl-4">
							<a href="thematique.php?id=<?= (int)$t['id'] ?>" class="card h-100 xm-card-hover overflow-hidden text-decoration-none">
								<div class="xm-ratio-16x9"><img class="xm-img-cover" src="<?= img($t['cover']) ?>" alt="Couverture de la fiche : <?= e($t['title']) ?>" title="<?= e($t['title']) ?>" loading="lazy" width="640" height="360"></div>
								<div class="card-body">
									<span class="badge bg-<?= $c[2] ?> bg-opacity-10 text-<?= $c[2] ?> mb-2"><i class="bi <?= $c[1] ?> me-1"></i><?= e($c[0]) ?></span>
									<h2 class="h6 mb-2"><?= e($t['title']) ?></h2>
									<p class="small text-secondary mb-0"><?= e($t['summary']) ?></p>
								</div>
								<div class="card-footer border-0 pt-0 bg-transparent small text-secondary"><i class="bi bi-clock me-1"></i><?= (int)$t['minutes'] ?> min de lecture</div>
							</a>
						</div>
						<?php endforeach; ?>
						<?php if (!$list): ?><div class="col-12"><div class="card card-body text-center text-secondary">Aucune thématique ne correspond à votre recherche.</div></div><?php endif; ?>
					</div>
					<?= $moreBtn ?>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
