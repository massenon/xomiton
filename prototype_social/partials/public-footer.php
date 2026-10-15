<?php /** Pied de page public Xomiton. */ ?>
<footer class="xm-pub-footer text-white pt-5 pb-3 mt-0 no-print">
	
	<div class="container">
		<div class="row g-4">
			<div class="col-lg-4">
				<img src="<?= BASE ?>assets/images/xomiton/brand/logo-mono-blanc.svg" alt="Xomiton, logo" title="Xomiton" style="height:40px;width:auto" class="mb-3">
				<p class="small opacity-75 mb-3">Votre espace safe d’apprentissage et d’appui holistique. Un espace numérique sécurisé pour les filles et femmes LBTQ+ au Bénin.</p>
				<a class="btn btn-sm btn-danger fw-bold" href="<?= BASE ?>sos.php"><i class="bi bi-broadcast me-1"></i>SOS avec position GPS</a>
			</div>
			<div class="col-6 col-lg-2">
				<h6 class="text-white">Plateforme</h6>
				<ul class="list-unstyled small mb-0 xm-flinks">
					<li><a href="<?= BASE ?>landing.php#valeurs">À propos de Xomiton</a></li>
					<li><a href="<?= BASE ?>help.php">Support</a></li>
					<li><a href="<?= BASE ?>do-glegbe.php">Do Glegbe</a></li>
					<li><a href="<?= BASE ?>ressources.php">Ressources</a></li>
				</ul>
			</div>
			<div class="col-6 col-lg-2">
				<h6 class="text-white">Aide &amp; protection</h6>
				<ul class="list-unstyled small mb-0 xm-flinks">
					<li><a href="<?= BASE ?>signalement.php">Signaler</a></li>
					<li><a href="<?= BASE ?>urgences.php">Contacts d’urgence</a></li>
					<li><a href="<?= BASE ?>help.php">Centre d’aide</a></li>
					<li><a href="<?= BASE ?>privacy-and-terms.php">Confidentialité &amp; conditions</a></li>
				</ul>
			</div>
			<div class="col-lg-4">
				<h6 class="text-white">Rester en sécurité</h6>
				<p class="small opacity-75">Besoin de quitter la page rapidement ? Utilisez la sortie rapide : elle vous redirige immédiatement vers un site neutre.</p>
				<a class="btn btn-sm btn-light" href="https://www.google.com"><i class="bi bi-box-arrow-up-right me-1"></i>Sortie rapide</a>
			</div>
		</div>
		<hr class="border-light opacity-25 my-4">
		<div class="d-flex flex-column flex-md-row justify-content-between small opacity-75 gap-2">
			<span>© <?= date('Y') ?> Xomiton — Bénin. Tous droits réservés.</span>
			<span class="d-flex flex-wrap gap-3 xm-flinks"><a href="<?= BASE ?>privacy-and-terms.php#charte">Charte</a><a href="<?= BASE ?>privacy-and-terms.php#confidentialite">Confidentialité</a><a href="<?= BASE ?>privacy-and-terms.php#conditions">Conditions d’utilisation</a></span>
		</div>
	</div>
</footer>
<style>
	.xm-pub-footer { background: #0A1450; padding-bottom: 4.5rem !important; }
	.xm-flinks li { margin-bottom: .35rem; }
	.xm-flinks a { color: rgba(255,255,255,.75); text-decoration: none; }
	.xm-flinks a:hover { color: #fff; text-decoration: underline; }
</style>
