<?php
require __DIR__ . '/inc/bootstrap.php';
$pageTitle = 'Connexion';
$error = '';
$next = $_GET['next'] ?? $_POST['next'] ?? '';
if (x_user() && $_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . (x_is_admin() ? 'admin/index.php' : 'index.php')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { $error = 'Session expirée, veuillez réessayer.'; }
    else {
        $login = strtolower(trim($_POST['email'] ?? ''));
        $found = null;
        foreach (store_all('users') as $r) {
            if (strtolower($r['email']) === $login || strtolower($r['pseudo']) === $login) { $found = $r; break; }
        }
        if (!$found || !$found['hash'] || !password_verify($_POST['password'] ?? '', $found['hash'])) {
            $error = 'Identifiants incorrects.';
            x_log('Connexion échouée', $login);
        } elseif ($found['status'] === 'en_attente') {
            $error = 'Votre compte est en attente de validation par l’équipe Xomiton.';
        } elseif ($found['status'] === 'suspendu') {
            $error = 'Ce compte est suspendu. Contactez-nous : ' . setting('contact_email', 'contact@xomiton.bj');
        } elseif (setting('maintenance') && !in_array($found['role'], ['admin', 'superadmin'], true)) {
            $error = 'La plateforme est en maintenance. Réessayez dans quelques instants.';
        } else {
            session_regenerate_id(true);
            unset($found['hash']);
            $_SESSION['user'] = $found;
            x_log('Connexion', 'Succès');
            $dest = (x_is_admin() && !$next) ? 'admin/index.php' : ($next && !preg_match('#^(https?:)?//#', $next) ? $next : 'index.php');
            header('Location: ' . $dest); exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
</head>
<body>
<main id="contenu" tabindex="-1">
	<div class="container-fluid p-0">
		<div class="row g-0 min-vh-100">
			<div class="col-lg-6 d-none d-lg-flex xm-auth-side align-items-end p-5" style="background-image:url(<?= img('sign-in.jpg') ?>)">
				<div class="text-white">
					<img src="<?= BASE ?>assets/images/xomiton/brand/logo-mono-blanc.svg" alt="Xomiton" height="44" class="mb-4">
					<h2 class="display-6 fw-bold">Votre espace safe d’apprentissage et d’appui holistique.</h2>
					<p class="lead mb-0">Filles et femmes LBTQ+ : retrouvez votre communauté bienveillante, des ressources fiables et un soutien confidentiel.</p>
				</div>
			</div>
			<div class="col-lg-6 d-flex align-items-center">
				<div class="w-100 p-4 p-md-5 mx-auto" style="max-width:520px">
					<a href="landing.php" class="d-lg-none d-inline-block mb-4"><img src="<?= BASE ?>assets/images/logo.svg" alt="Xomiton" height="38"></a>
					<h1 class="h2 fw-bold">Bon retour 👋</h1>
					<p class="text-secondary">Connectez-vous pour accéder à votre espace.</p>

					<?= flash_html() ?>
					<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

					<form method="post" data-xm-validate novalidate>
						<?= csrf_field() ?>
						<input type="hidden" name="next" value="<?= e($next) ?>">
						<div class="mb-3">
							<label class="form-label" for="email">E-mail ou pseudonyme</label>
							<input type="text" class="form-control form-control-lg" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus autocomplete="username">
						</div>
						<div class="mb-3">
							<label class="form-label" for="password">Mot de passe</label>
							<div class="input-group input-group-lg">
								<input type="password" class="form-control fakepassword" id="password" name="password" required autocomplete="current-password">
								<span class="input-group-text p-0 bg-transparent"><i class="fakepasswordicon fa-solid fa-eye-slash cursor-pointer p-2 w-40px"></i></span>
							</div>
						</div>
						<div class="d-flex justify-content-between align-items-center mb-4">
							<div class="form-check mb-0"><input type="checkbox" class="form-check-input" id="remember"><label class="form-check-label" for="remember">Rester connecté·e</label></div>
							<a href="forgot-password.php" class="small">Mot de passe oublié ?</a>
						</div>
						<div class="d-grid"><button type="submit" class="btn btn-lg btn-primary">Se connecter</button></div>
					</form>

					<p class="text-center mt-4 mb-2">Pas encore de compte ? <a href="sign-up.php" class="fw-bold">Créer un compte</a></p>
					<p class="text-center small"><a href="signalement.php"><i class="bi bi-megaphone me-1"></i>Signaler (sans compte)</a> · <a href="urgences.php" class="text-danger">Urgences</a></p>

					<div class="card bg-primary bg-opacity-10 border-0 mt-4">
						<div class="card-body py-3 small">
							<div class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i>Comptes de démonstration</div>
							<div class="d-flex flex-wrap gap-2">
								<button type="button" class="btn btn-sm btn-primary-soft" data-demo="membre@xomiton.bj|Membre123!">Membre</button>
								<button type="button" class="btn btn-sm btn-primary-soft" data-demo="pair@xomiton.bj|Pair123!">Pair animateur·rice</button>
								<button type="button" class="btn btn-sm btn-primary-soft" data-demo="admin@xomiton.bj|Admin123!">Administrateur</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>
<?php $noSosFab = true; include("partials/footer-scripts.php"); ?>
<script>
document.querySelectorAll('[data-demo]').forEach(b => b.addEventListener('click', () => {
	const [u, p] = b.dataset.demo.split('|');
	document.getElementById('email').value = u; document.getElementById('password').value = p;
}));
document.querySelectorAll('.fakepasswordicon').forEach(i => i.addEventListener('click', () => {
	const f = document.getElementById('password'); const show = f.type === 'password'; f.type = show ? 'text' : 'password';
	i.classList.toggle('fa-eye', show); i.classList.toggle('fa-eye-slash', !show);
}));
</script>
</body>
</html>
