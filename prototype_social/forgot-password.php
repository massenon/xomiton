<?php
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = 'Mot de passe oublié';
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) { x_log('Réinitialisation demandée', trim($_POST['email'] ?? '')); $sent = true; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
</head>
<body>
<main id="contenu" tabindex="-1">
	<div class="container">
		<div class="row justify-content-center align-items-center vh-100 py-5">
			<div class="col-sm-10 col-md-8 col-lg-6 col-xl-5 text-center">
				<a href="landing.php"><img src="<?= BASE ?>assets/images/logo.svg" alt="Xomiton" height="40" class="mb-4"></a>
				<div class="card card-body text-center p-4 p-sm-5">
					<h1 class="h3 fw-bold">Mot de passe oublié ?</h1>
					<?php if ($sent): ?>
						<div class="alert alert-success mt-3">Si un compte correspond à cette adresse, un lien de réinitialisation vient d’être envoyé. Pensez à vérifier vos courriers indésirables.</div>
						<a class="btn btn-primary" href="sign-in.php">Retour à la connexion</a>
					<?php else: ?>
						<p class="text-secondary">Indiquez votre adresse e-mail : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>
						<form method="post" data-xm-validate novalidate class="text-start">
							<?= csrf_field() ?>
							<div class="mb-3"><label class="form-label">E-mail</label><input type="email" class="form-control form-control-lg" name="email" data-rule="email" required></div>
							<div class="d-grid"><button class="btn btn-lg btn-primary" type="submit">Envoyer le lien</button></div>
						</form>
						<p class="mt-4 mb-0"><a href="sign-in.php">← Retour à la connexion</a></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</main>
<?php $noSosFab = true; include("partials/footer-scripts.php"); ?>
</body>
</html>
