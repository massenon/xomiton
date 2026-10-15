<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Médiathèque';
$nav = '';
$albums = [
	'Ateliers et rencontres' => ['hero-01.jpg', 'hero-02.jpg', 'hero-03.jpg', 'hero-04.jpg', 'hero-05.jpg', 'hero-06.jpg'],
	'Sororité et entraide' => ['hero-07.jpg', 'hero-08.jpg', 'hero-09.jpg', 'hero-10.jpg', 'hero-11.jpg', 'hero-12.jpg'],
	'Sensibilisation et droits' => ['hero-13.jpg', 'hero-14.jpg', 'hero-15.jpg', 'hero-16.jpg', '3995814.jpg', '4137441.jpg', '6033855.jpg', '7150569.jpg'],
];
$cur = $_GET['album'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<link rel="stylesheet" href="<?= BASE ?>assets/vendor/glightbox/css/glightbox.min.css">
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
						<h1 class="h4 mb-1">Médiathèque</h1>
						<p class="small text-secondary mb-3">Images de la communauté Xomiton, utilisées dans les outils de sensibilisation (boîtes à images, ateliers).</p>
						<div class="d-flex flex-wrap gap-2">
							<a class="btn btn-sm <?= $cur === '' ? 'btn-primary' : 'btn-light' ?>" href="?">Tous les albums</a>
							<?php foreach ($albums as $name => $imgs): ?><a class="btn btn-sm <?= $cur === $name ? 'btn-primary' : 'btn-light' ?>" href="?album=<?= urlencode($name) ?>"><?= e($name) ?> (<?= count($imgs) ?>)</a><?php endforeach; ?>
						</div>
					</div>
					<?php foreach ($albums as $name => $imgs): if ($cur !== '' && $cur !== $name) continue; ?>
					<div class="card">
						<div class="card-header border-0 pb-0"><h2 class="h6 mb-0"><?= e($name) ?></h2></div>
						<div class="card-body"><div class="row g-3">
							<?php foreach ($imgs as $m): ?>
							<div class="col-6 col-md-4 col-xl-3"><a href="<?= img($m) ?>" data-glightbox data-gallery="<?= e(md5($name)) ?>" data-title="<?= e($name) ?>"><div class="xm-ratio-4x3 rounded xm-card-hover"><img class="xm-img-cover" src="<?= img($m) ?>" alt="<?= e($name) ?>" loading="lazy"></div></a></div>
							<?php endforeach; ?>
						</div></div>
					</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<script src="<?= BASE ?>assets/vendor/glightbox/js/glightbox.min.js"></script>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
