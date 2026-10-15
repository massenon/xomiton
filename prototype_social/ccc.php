<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Outils CCC interactifs';
$nav = 'ccc';
$pageDesc = 'Outils de communication pour le changement de comportement : manuel du pair animateur·rice, boîtes à images, bandes dessinées SSR et lois et codes du Bénin, utilisables hors connexion.';
$pageImage = 'covers/ccc-manuel.jpg';
$tools = [
    ['manuel-pair-animateur.php', 'Manuel du pair animateur·rice', 'Modules de formation, listes de suivi et quiz de fin de module.', 'bi-journal-bookmark-fill', 'primary', 'covers/ccc-manuel.jpg', count(x_ccc_manuel()) . ' modules'],
    ['boites-a-images.php', 'Boîtes à images', 'Images pour animer des discussions, avec guide de l’animateur·rice.', 'bi-images', 'success', 'covers/ccc-images.jpg', count(x_ccc_boites()) . ' images'],
    ['bd-ssr.php', 'Bandes dessinées SSR', 'Histoires courtes sur la santé sexuelle et reproductive à lire en groupe.', 'bi-chat-square-quote-fill', 'warning', 'covers/ccc-bd.jpg', count(x_ccc_bd()) . ' histoires'],
    ['lois-benin.php', 'Lois et codes du Bénin', 'Bibliothèque juridique consultable, avec résumés en langage simple.', 'bi-bank2', 'info', 'covers/ccc-lois.jpg', count(x_lois()) . ' textes'],
];
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
					<div class="card xm-hero-bg border-0 overflow-hidden">
						<div class="card-body p-4 p-md-5">
							<span class="badge bg-white text-primary mb-2">Communication pour le changement de comportement</span>
							<h1 class="h2 text-white">Outils CCC interactifs</h1>
							<p class="mb-0 text-white opacity-75" style="max-width:560px">Les outils papier de terrain, rendus numériques : à utiliser en séance de groupe, en entretien individuel ou en autoformation, y compris hors connexion.</p>
						</div>
					</div>
					<div class="row g-3">
						<?php foreach ($tools as $t): ?>
						<div class="col-md-6">
							<a href="<?= e($t[0]) ?>" class="card h-100 overflow-hidden xm-card-hover text-decoration-none">
								<div class="xm-ratio-16x9 position-relative"><img class="xm-img-cover" src="<?= img($t[5]) ?>" alt="Couverture de l’outil : <?= e($t[1]) ?>" title="<?= e($t[1]) ?>" loading="lazy" width="640" height="360"><span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75"><?= e($t[6]) ?></span></div>
								<div class="card-body">
									<div class="d-flex align-items-center gap-2 mb-1"><span class="icon-md rounded-circle bg-<?= $t[4] ?> bg-opacity-10 text-<?= $t[4] ?>"><i class="bi <?= $t[3] ?>"></i></span><h2 class="h5 mb-0"><?= e($t[1]) ?></h2></div>
									<p class="small text-secondary mb-0"><?= e($t[2]) ?></p>
								</div>
							</a>
						</div>
						<?php endforeach; ?>
					</div>
					<div class="card card-body flex-md-row align-items-md-center gap-3">
						<i class="bi bi-cloud-arrow-down fs-2 text-primary"></i>
						<div class="flex-grow-1"><strong>Sur le terrain, sans réseau ?</strong><div class="small text-secondary">Les outils restent consultables hors connexion une fois ouverts une première fois ; vos progressions sont conservées sur l’appareil.</div></div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
</body>
</html>
