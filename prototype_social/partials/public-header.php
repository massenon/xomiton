<?php
/** En-tête public Xomiton (pages sans connexion). Variable optionnelle : $pubNav (clé active). */
$pubNav = $pubNav ?? '';
$__u = x_user();
$__l = function ($href, $label, $key) use ($pubNav) {
	echo '<li class="nav-item"><a class="nav-link' . ($pubNav === $key ? ' active fw-bold' : '') . '" href="' . BASE . $href . '">' . $label . '</a></li>';
};
?>
<a class="xm-skip-link" href="#contenu">Aller au contenu</a>
<div class="xm-offline-bar" id="xmOfflineBar"><i class="bi bi-wifi-off me-1"></i> Vous êtes hors connexion — vos saisies sont conservées et seront synchronisées automatiquement.</div>
<header class="navbar-light bg-mode sticky-top shadow-sm no-print" id="pubHeader">
	<nav class="navbar navbar-expand-lg">
		<div class="container">
			<a class="navbar-brand" href="<?= BASE ?>landing.php">
				<img class="light-mode-item navbar-brand-item" src="<?= BASE ?>assets/images/logo.svg" alt="Xomiton, logo (mode clair)" title="Xomiton">
				<img class="dark-mode-item navbar-brand-item" src="<?= BASE ?>assets/images/logo-dark.svg" alt="Xomiton, logo (mode sombre)" title="Xomiton">
			</a>
			<button class="navbar-toggler ms-auto icon-md btn btn-light p-0" type="button" data-bs-toggle="collapse" data-bs-target="#pubNavbar" aria-controls="pubNavbar" aria-expanded="false" aria-label="Menu">
				<span class="navbar-toggler-animation"><span></span><span></span><span></span></span>
			</button>
			<div class="collapse navbar-collapse" id="pubNavbar">
				<ul class="navbar-nav mx-auto">
					<?php $__l('landing.php', 'Accueil', 'accueil');
					$__l('landing.php#valeurs', 'Notre espace', 'valeurs');
					$__l('do-glegbe.php', 'Do Glegbe', 'doglegbe');
					$__l('blog.php', 'Actualités', 'blog');
					$__l('help.php', 'Aide', 'aide'); ?>
				</ul>
				<div class="d-flex flex-wrap gap-2 py-3 py-lg-0 align-items-center">
					<a class="btn btn-sm btn-danger-soft fw-bold" href="<?= BASE ?>signalement.php"><i class="bi bi-megaphone me-1"></i>Signaler</a>
					<?php if ($__u): ?>
						<a class="btn btn-sm btn-primary" href="<?= BASE ?>index.php"><i class="bi bi-house-heart me-1"></i>Mon espace</a>
					<?php else: ?>
						<a class="btn btn-sm btn-outline-primary" href="<?= BASE ?>sign-in.php">Se connecter</a>
						<a class="btn btn-sm btn-primary" href="<?= BASE ?>sign-up.php">Créer un compte</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</nav>
	<div class="xm-rainbow-line"></div>
</header>
<script>
	(function () {
		var b = document.getElementById('pubThemeToggle'); if (!b) return;
		b.addEventListener('click', function () {
			var d = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
			try { localStorage.setItem('theme', d); } catch (e) {}
			document.documentElement.setAttribute('data-bs-theme', d);
		});
	})();
</script>
