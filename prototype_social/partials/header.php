<?php
$u = x_user();
$uName = $u['name'] ?? 'Invité·e';
$uAvatar = x_avatar($u['avatar'] ?? 0);
$uRoleLabel = ['superadmin' => 'Super administrateur', 'admin' => 'Administrateur', 'pair' => 'Pair animateur·rice', 'membre' => 'Membre'][$u['role'] ?? 'membre'] ?? 'Membre';
$nav = $nav ?? '';
?>
<a class="xm-skip-link" href="#contenu">Aller au contenu</a>
<div class="xm-offline-bar" id="xmOfflineBar"><i class="bi bi-wifi-off me-1"></i> Vous êtes hors connexion — vos saisies sont conservées et seront synchronisées automatiquement.</div>
<!-- =======================
Header START -->
<header class="<?= isset($headerClass) ? $headerClass : 'navbar-light fixed-top bg-mode' ?> header-static">

	<nav class="navbar navbar-expand-lg <?= isset($navClass) ? $navClass : '' ?>">
		<div class="container<?= isset($divClass) ? $divClass : '' ?>">
			<a class="navbar-brand" href="<?= BASE ?>index.php">
				<img class="light-mode-item navbar-brand-item" src="<?= BASE ?>assets/images/logo.svg" alt="Xomiton, logo (mode clair)" title="Xomiton">
				<img class="dark-mode-item navbar-brand-item" src="<?= BASE ?>assets/images/logo-dark.svg" alt="Xomiton, logo (mode sombre)" title="Xomiton">
			</a>

			<button class="navbar-toggler ms-auto icon-md btn btn-light p-0" type="button" data-bs-toggle="collapse"
				data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false"
				aria-label="Menu">
				<span class="navbar-toggler-animation"><span></span><span></span><span></span></span>
			</button>

			<div class="collapse navbar-collapse" id="navbarCollapse">
				<?php if (!isset($hideSearch)): ?>
					<div class="nav mt-3 mt-lg-0 flex-nowrap align-items-center px-4 px-lg-0">
						<div class="nav-item w-100">
							<form class="rounded position-relative" action="<?= BASE ?>ressources.php" method="get">
								<input class="form-control ps-5 bg-light" type="search" name="q" placeholder="Rechercher une ressource, un contact…" aria-label="Rechercher">
								<button class="btn bg-transparent px-2 py-0 position-absolute top-50 start-0 translate-middle-y" type="submit"><i class="bi bi-search fs-5"> </i></button>
							</form>
						</div>
					</div>
				<?php endif; ?>

				<ul class="navbar-nav navbar-nav-scroll <?= isset($navCenter) ? 'mx-auto' : 'ms-auto' ?>">
					<li class="nav-item"><a class="nav-link <?= $nav === 'fil' ? 'active' : '' ?>" href="<?= BASE ?>index.php">Fil</a></li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="menuApprendre" data-bs-toggle="dropdown" aria-expanded="false">Apprendre</a>
						<ul class="dropdown-menu" aria-labelledby="menuApprendre">
							<li><a class="dropdown-item" href="<?= BASE ?>thematiques.php"><i class="bi bi-grid fa-fw me-2"></i>Thématiques</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>ressources.php"><i class="bi bi-journal-text fa-fw me-2"></i>Ressources</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>violences.php"><i class="bi bi-shield-exclamation fa-fw me-2"></i>Violences : comprendre</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>lois-benin.php"><i class="bi bi-bank fa-fw me-2"></i>Lois et codes du Bénin</a></li>
							<li class="dropdown-divider"></li>
							<li><a class="dropdown-item" href="<?= BASE ?>ccc.php"><i class="bi bi-collection-play fa-fw me-2"></i>Outils CCC interactifs</a></li>
						</ul>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="menuCommunaute" data-bs-toggle="dropdown" aria-expanded="false">Communauté</a>
						<ul class="dropdown-menu" aria-labelledby="menuCommunaute">
							<li><a class="dropdown-item" href="<?= BASE ?>groups.php"><i class="bi bi-people fa-fw me-2"></i>Groupes</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>pages.php"><i class="bi bi-flag fa-fw me-2"></i>Pages</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>events.php"><i class="bi bi-calendar-event fa-fw me-2"></i>Événements</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>forums.php"><i class="bi bi-chat-square-text fa-fw me-2"></i>Forums</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>who-to-follow.php"><i class="bi bi-person-plus fa-fw me-2"></i>Qui suivre / Invitations</a></li>
						</ul>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="menuAide" data-bs-toggle="dropdown" aria-expanded="false">Aide &amp; protection</a>
						<ul class="dropdown-menu" aria-labelledby="menuAide">
							<li><a class="dropdown-item text-danger fw-bold" href="<?= BASE ?>sos.php"><i class="bi bi-broadcast fa-fw me-2"></i>SOS (position GPS)</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>signalement.php"><i class="bi bi-megaphone fa-fw me-2"></i>Signaler</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>urgences.php"><i class="bi bi-telephone-fill fa-fw me-2"></i>Contacts d’urgence</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>annuaire.php"><i class="bi bi-person-lines-fill fa-fw me-2"></i>Annuaire</a></li>
							<li><a class="dropdown-item" href="<?= BASE ?>assistant.php"><i class="bi bi-robot fa-fw me-2"></i>Assistant chat</a></li>
						</ul>
					</li>
					<?php if (in_array($u['role'] ?? '', ['pair', 'admin', 'superadmin'], true)): ?>
					<li class="nav-item"><a class="nav-link <?= $nav === 'pair' ? 'active' : '' ?>" href="<?= BASE ?>espace-pair.php">Espace pair</a></li>
					<?php endif; ?>
				</ul>
			</div>

			<ul class="nav flex-nowrap align-items-center ms-sm-3 list-unstyled">
				<?php if (!isset($hideButtons)): ?>
					<li class="nav-item ms-2 d-none d-sm-block">
						<a class="btn btn-danger btn-sm fw-bold" href="<?= BASE ?>sos.php"><i class="bi bi-broadcast me-1"></i>SOS</a>
					</li>
					<li class="nav-item ms-2">
						<a class="nav-link bg-light icon-md btn btn-light p-0" href="<?= BASE ?>messaging.php" title="Messages">
							<i class="bi bi-chat-left-text-fill fs-6"> </i>
						</a>
					</li>
					<li class="nav-item ms-2">
						<a class="nav-link bg-light icon-md btn btn-light p-0" href="<?= BASE ?>settings.php" title="Paramètres">
							<i class="bi bi-gear-fill fs-6"> </i>
						</a>
					</li>
					<li class="nav-item dropdown ms-2">
						<a class="nav-link bg-light icon-md btn btn-light p-0" href="#" id="notifDropdown" role="button"
							data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
							<span class="badge-notif animation-blink"></span>
							<i class="bi bi-bell-fill fs-6"> </i>
						</a>
						<div class="dropdown-menu dropdown-animation dropdown-menu-end dropdown-menu-size-md p-0 shadow-lg border-0" aria-labelledby="notifDropdown">
							<div class="card">
								<div class="card-header d-flex justify-content-between align-items-center">
									<h6 class="m-0">Notifications <span class="badge bg-danger bg-opacity-10 text-danger ms-2">3 nouvelles</span></h6>
									<a class="small" href="#">Tout effacer</a>
								</div>
								<div class="card-body p-0">
									<ul class="list-group list-group-flush list-unstyled p-2">
										<li>
											<div class="list-group-item list-group-item-action rounded badge-unread d-flex border-0 mb-1 p-3">
												<div class="avatar text-center d-none d-sm-inline-block"><img class="avatar-img rounded-circle" src="<?= x_avatar(1) ?>" alt="Photo de profil de Mariam A." title="Mariam A."></div>
												<div class="ms-sm-3">
													<p class="small mb-2"><b>Mariam A.</b> vous a envoyé une invitation.</p>
													<a class="btn btn-sm py-1 btn-primary me-2" href="<?= BASE ?>who-to-follow.php">Voir</a>
												</div>
											</div>
										</li>
										<li>
											<a href="<?= BASE ?>events.php" class="list-group-item list-group-item-action rounded badge-unread d-flex border-0 mb-1 p-3">
												<div class="avatar text-center d-none d-sm-inline-block"><div class="avatar-img rounded-circle bg-primary"><span class="text-white position-absolute top-50 start-50 translate-middle"><i class="bi bi-calendar-event"></i></span></div></div>
												<div class="ms-sm-3"><p class="small mb-0">Atelier « Dire non sans culpabiliser » : samedi 15h.</p></div>
											</a>
										</li>
										<li>
											<a href="<?= BASE ?>ressources.php" class="list-group-item list-group-item-action rounded d-flex border-0 mb-1 p-3">
												<div class="avatar text-center d-none d-sm-inline-block"><div class="avatar-img rounded-circle bg-success"><span class="text-white position-absolute top-50 start-50 translate-middle"><i class="bi bi-journal-text"></i></span></div></div>
												<div class="ms-sm-3"><p class="small mb-0">Nouvelle fiche : « Porter plainte, étape par étape ».</p></div>
											</a>
										</li>
									</ul>
								</div>
								<div class="card-footer text-center"><a href="<?= BASE ?>notifications.php" class="btn btn-sm btn-primary-soft">Voir toute l’activité</a></div>
							</div>
						</div>
					</li>
				<?php endif; ?>

				<li class="nav-item ms-2 dropdown">
					<a class="nav-link btn icon-md p-0" href="#" id="profileDropdown" role="button" data-bs-auto-close="outside" data-bs-display="static" data-bs-toggle="dropdown" aria-expanded="false">
						<img class="avatar-img rounded-2" src="<?= $uAvatar ?>" alt="Mon avatar" title="<?= e($uName) ?>" width="40" height="40">
					</a>
					<ul class="dropdown-menu dropdown-animation dropdown-menu-end pt-3 small me-md-n3" aria-labelledby="profileDropdown">
						<li class="px-3">
							<div class="d-flex align-items-center position-relative">
								<div class="avatar me-3"><img class="avatar-img rounded-circle" src="<?= $uAvatar ?>" alt="Avatar de <?= e($uName) ?>" title="<?= e($uName) ?>"></div>
								<div>
									<a class="h6 stretched-link" href="<?= BASE ?>my-profile.php"><?= e($uName) ?></a>
									<p class="small m-0"><?= e($uRoleLabel) ?></p>
								</div>
							</div>
							<a class="dropdown-item btn btn-primary-soft btn-sm my-2 text-center" href="<?= BASE ?>my-profile.php">Voir mon profil</a>
						</li>
						<li><a class="dropdown-item" href="<?= BASE ?>settings.php"><i class="bi bi-gear fa-fw me-2"></i>Paramètres &amp; confidentialité</a></li>
						<li><a class="dropdown-item" href="<?= BASE ?>help.php"><i class="fa-fw bi bi-life-preserver me-2"></i>Centre d’aide</a></li>
						<?php if (x_is_admin()): ?>
						<li><a class="dropdown-item" href="<?= BASE ?>admin/index.php"><i class="bi bi-speedometer2 fa-fw me-2"></i>Administration</a></li>
						<?php endif; ?>
						<li class="dropdown-divider"></li>
						<li><a class="dropdown-item bg-danger-soft-hover" href="<?= BASE ?>logout.php"><i class="bi bi-power fa-fw me-2"></i>Se déconnecter</a></li>
					</ul>
				</li>
			</ul>
		</div>
	</nav>
</header>
<!-- Header END -->
