<?php
/* En-tête de profil Xomiton. Variables attendues : $profTab (clé de l'onglet actif). */
require_once __DIR__ . '/../inc/x_reseau.php';
$pu = x_user();
$profTab = $profTab ?? 'posts';
$__nPosts = count(x_user_posts($pu['id']));
$__nConn = count(x_connections($pu['id']));
$__tabs = [
	'posts' => ['my-profile.php', 'Publications'], 'about' => ['my-profile-about.php', 'À propos'],
	'connections' => ['my-profile-connections.php', 'Connexions'], 'media' => ['my-profile-media.php', 'Médias'],
	'events' => ['my-profile-events.php', 'Événements'], 'activity' => ['my-profile-activity.php', 'Activité'],
];
?>
<div class="card">
	<div class="h-200px rounded-top" style="background-image:url(<?= img('profile-cover.jpg') ?>); background-position:center; background-size:cover; background-repeat:no-repeat;"></div>
	<div class="card-body py-0">
		<div class="d-sm-flex align-items-start text-center text-sm-start">
			<div>
				<div class="avatar avatar-xxl mt-n5 mb-3">
					<img class="avatar-img rounded-circle border border-white border-3" src="<?= x_avatar($pu['avatar'] ?? 0) ?>" alt="Avatar de <?= e($pu['name'] ?? 'ce profil') ?>" title="<?= e($pu['name'] ?? 'Profil') ?>">
				</div>
			</div>
			<div class="ms-sm-4 mt-sm-3">
				<h1 class="mb-0 h5"><?= e($pu['name']) ?> <?php if (in_array($pu['role'], ['pair', 'admin', 'superadmin'], true)): ?><i class="bi bi-patch-check-fill text-primary small" title="<?= e(x_role_label($pu['role'])) ?>"></i><?php endif; ?></h1>
				<p class="mb-0 text-secondary">@<?= e($pu['pseudo'] ?? '') ?> · <?= $__nConn ?> connexions</p>
			</div>
			<div class="d-flex mt-3 justify-content-center ms-sm-auto">
				<a class="btn btn-primary-soft me-2" href="settings.php"><i class="bi bi-pencil-fill pe-1"></i> Modifier le profil</a>
				<div class="dropdown">
					<button class="icon-md btn btn-light" type="button" id="profileAction" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Plus d’actions"><i class="bi bi-three-dots"></i></button>
					<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileAction">
						<li><a class="dropdown-item" href="settings.php#confidentialite"><i class="bi bi-lock fa-fw pe-2"></i>Confidentialité</a></li>
						<li><a class="dropdown-item" href="settings.php#donnees"><i class="bi bi-download fa-fw pe-2"></i>Télécharger mes données</a></li>
						<li><hr class="dropdown-divider"></li>
						<li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear fa-fw pe-2"></i>Paramètres</a></li>
					</ul>
				</div>
			</div>
		</div>
		<ul class="list-inline mb-0 text-center text-sm-start mt-3 mt-sm-0">
			<li class="list-inline-item"><i class="bi bi-person-badge me-1"></i><?= e(x_role_label($pu['role'])) ?></li>
			<li class="list-inline-item"><i class="bi bi-geo-alt me-1"></i><?= e(trim(($pu['commune'] ?? '') . ', ' . ($pu['zone'] ?? ''), ', ')) ?></li>
			<li class="list-inline-item"><i class="bi bi-calendar2-plus me-1"></i>Membre depuis <?= e(x_date_fr($pu['joined'] ?? 'now')) ?></li>
		</ul>
	</div>
	<div class="card-footer mt-3 pt-2 pb-0">
		<ul class="nav nav-bottom-line align-items-center justify-content-center justify-content-md-start mb-0 border-0 flex-nowrap overflow-auto">
			<?php foreach ($__tabs as $k => $t): ?>
			<li class="nav-item"><a class="nav-link text-nowrap <?= $profTab === $k ? 'active' : '' ?>" href="<?= $t[0] ?>"><?= $t[1] ?><?= $k === 'posts' ? ' <span class="badge bg-primary bg-opacity-10 text-primary ms-1">' . $__nPosts . '</span>' : '' ?></a></li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>
