<?php
$u = x_user();
$act = $nav ?? '';
$L = function ($href, $icon, $label, $key = '', $cls = '') use ($act) {
    $a = ($key !== '' && $key === $act) ? ' active' : '';
    echo '<a class="xm-side-link' . $a . ' ' . $cls . '" href="' . BASE . $href . '"><i class="bi ' . $icon . '"></i><span>' . $label . '</span></a>';
};
$counts = ['posts' => count(store_all('posts')), 'cases' => count(store_all('cases'))];
?>
<!-- Sidenav START -->
<div class="col-lg-3">

	<div class="d-flex align-items-center d-lg-none">
		<button class="border-0 bg-transparent" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasSideNavbar" aria-controls="offcanvasSideNavbar">
			<span class="btn btn-primary"><i class="fa-solid fa-sliders-h"></i></span>
			<span class="h6 mb-0 fw-bold d-lg-none ms-2">Menu</span>
		</button>
	</div>

	<nav class="navbar navbar-expand-lg mx-0">
		<div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasSideNavbar">
			<div class="offcanvas-header">
				<button type="button" class="btn-close text-reset ms-auto" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
			</div>

			<div class="offcanvas-body d-block px-2 px-lg-0">
				<div class="card overflow-hidden">
					<div class="h-50px" style="background-image:url(<?= img('profile-cover.jpg') ?>); background-position:center; background-size:cover;"></div>
					<div class="card-body pt-0">
						<div class="text-center">
							<div class="avatar avatar-lg mt-n5 mb-3">
								<a href="<?= BASE ?>my-profile.php"><img class="avatar-img rounded border border-white border-3" src="<?= x_avatar($u['avatar'] ?? 0) ?>" alt="Avatar de <?= e($u['name'] ?? 'Invité·e') ?>" title="<?= e($u['name'] ?? 'Invité·e') ?>"></a>
							</div>
							<h5 class="mb-0"><a href="<?= BASE ?>my-profile.php"><?= e($u['name'] ?? 'Invité·e') ?></a></h5>
							<small><?= e('@' . ($u['pseudo'] ?? 'invite')) ?> · <?= e($u['zone'] ?? 'Bénin') ?></small>
							<div class="hstack gap-2 gap-xl-3 justify-content-center mt-3">
								<div><h6 class="mb-0">24</h6><small>Posts</small></div>
								<div class="vr"></div>
								<div><h6 class="mb-0">182</h6><small>Abonné·es</small></div>
								<div class="vr"></div>
								<div><h6 class="mb-0">96</h6><small>Abonnements</small></div>
							</div>
						</div>
						<hr>

						<div class="d-flex flex-column gap-1">
							<div class="xm-nav-section pt-0">Réseau social</div>
							<?php $L('index.php', 'bi-house-heart', 'Fil d’actualité', 'fil');
							$L('my-profile.php', 'bi-person-circle', 'Mon profil', 'profil');
							$L('messaging.php', 'bi-chat-dots', 'Messages (Messenger)', 'chat');
							$L('groups.php', 'bi-people', 'Groupes', 'groupes');
							$L('pages.php', 'bi-flag', 'Pages', 'pages');
							$L('events.php', 'bi-calendar-event', 'Événements', 'events');
							$L('forums.php', 'bi-chat-square-text', 'Forums', 'forums');
							$L('who-to-follow.php', 'bi-person-plus', 'Qui suivre &amp; invitations', 'follow'); ?>

							<div class="xm-nav-section">Apprendre</div>
							<?php $L('thematiques.php', 'bi-grid', 'Thématiques', 'thematiques');
							$L('ressources.php', 'bi-journal-text', 'Ressources', 'ressources');
								$L('do-glegbe.php', 'bi-droplet-half', 'Do Glegbe (règles)', 'doglegbe');
							$L('violences.php', 'bi-shield-exclamation', 'Violences', 'violences');
							$L('ccc.php', 'bi-collection-play', 'Outils CCC interactifs', 'ccc'); ?>

							<div class="xm-nav-section">Aide &amp; protection</div>
							<?php $L('sos.php', 'bi-broadcast', 'SOS – alerte GPS', 'sos', 'danger');
							$L('signalement.php', 'bi-megaphone', 'Signaler', 'signalement');
							$L('urgences.php', 'bi-telephone-fill', 'Contacts d’urgence', 'urgences');
							$L('annuaire.php', 'bi-person-lines-fill', 'Annuaire', 'annuaire');
							$L('assistant.php', 'bi-robot', 'Assistant chat', 'assistant'); ?>

							<?php if (in_array($u['role'] ?? '', ['pair', 'admin', 'superadmin'], true)): ?>
							<div class="xm-nav-section">Pair animateur·rice</div>
							<?php $L('espace-pair.php', 'bi-clipboard2-pulse', 'Espace pair', 'pair');
							$L('collecte.php', 'bi-pencil-square', 'Collecte de données', 'collecte'); ?>
							<?php endif; ?>

							<div class="xm-nav-section">Compte</div>
							<?php $L('settings.php', 'bi-gear', 'Paramètres', 'settings');
							$L('help.php', 'bi-life-preserver', 'Centre d’aide', 'help');
							if (x_is_admin()) $L('admin/index.php', 'bi-speedometer2', 'Administration', 'admin');
							$L('logout.php', 'bi-power', 'Se déconnecter', '', 'danger'); ?>
						</div>
					</div>
				</div>

				<ul class="nav small mt-3 justify-content-center lh-1">
					<li class="nav-item"><a class="nav-link" href="<?= BASE ?>my-profile-about.php">À propos</a></li>
					<li class="nav-item"><a class="nav-link" href="<?= BASE ?>privacy-and-terms.php">Confidentialité &amp; conditions</a></li>
				</ul>
				<p class="small text-center mt-1">©<?= date('Y') ?> Xomiton</p>
			</div>
		</div>
	</nav>
</div>
<!-- Sidenav END -->
