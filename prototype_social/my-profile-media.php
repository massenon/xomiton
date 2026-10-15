<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Mes médias';
$nav = 'profil';
$profTab = 'media';
$media = array_slice(x_media_images(), 4, 15);
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
				<div class="col-lg-8 vstack gap-4">
					<?= flash_html() ?>
					<?php include("partials/profile-head.php"); ?>
					<div class="card">
						<div class="card-header border-0 pb-0 d-flex justify-content-between"><h5 class="card-title mb-0">Photos</h5><a class="btn btn-primary-soft btn-sm" href="albums.php">Médiathèque</a></div>
						<div class="card-body"><div class="row g-3">
							<?php foreach ($media as $m): ?>
							<div class="col-6 col-md-4"><a href="<?= img($m) ?>" data-glightbox data-gallery="profil"><div class="xm-ratio-1x1 rounded"><img class="xm-img-cover" src="<?= img($m) ?>" alt="Photo Xomiton" loading="lazy"></div></a></div>
							<?php endforeach; ?>
						</div></div>
					</div>
				</div>
				<?= x_profile_side() ?>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<script src="<?= BASE ?>assets/vendor/glightbox/js/glightbox.min.js"></script>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
