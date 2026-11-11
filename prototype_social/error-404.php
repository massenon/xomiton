<?php
require __DIR__ . '/inc/x_public.php';
x_pub_open('Page introuvable', ['forcePublic' => true, 'noSosFab' => true]);
$u = x_user();
?>
<section class="py-5">
	<div class="container">
		<div class="row align-items-center g-5 py-lg-5">
			<div class="col-lg-6 text-center text-lg-start">
				<div class="display-1 fw-bold" style="color:var(--xm-blue,#0433C3);line-height:1">404</div>
				<h1 class="h2 mt-2">Oups, cette page est introuvable</h1>
				<p class="text-secondary lead">La page que vous cherchez a peut-être été déplacée ou n’existe plus. Vous n’avez rien fait de mal : retrouvons ensemble le bon chemin.</p>
				<div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-start">
					<a class="btn btn-primary" href="<?= $u ? 'fil.php' : 'index.php' ?>"><i class="bi bi-house-heart me-1"></i><?= $u ? 'Mon espace' : 'Retour à l’accueil' ?></a>
					<a class="btn btn-primary-soft" href="help.php"><i class="bi bi-life-preserver me-1"></i>Centre d’aide</a>
					<a class="btn btn-danger-soft" href="urgences.php"><i class="bi bi-telephone me-1"></i>Contacts d’urgence</a>
				</div>
				<p class="small text-secondary mt-4 mb-0"><i class="bi bi-shield-check me-1"></i>En danger ? Utilisez le bouton SOS dans votre espace ou appelez les numéros d’urgence.</p>
			</div>
			<div class="col-lg-6">
				<img class="img-fluid rounded-4 shadow" style="max-height:380px;width:100%;object-fit:cover" src="<?= img('hero-03.jpg') ?>" alt="Illustration Xomiton">
			</div>
		</div>
	</div>
</section>
<?php x_pub_close(); ?>
