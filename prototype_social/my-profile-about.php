<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'À propos de moi';
$nav = 'profil';
$profTab = 'about';
$u = x_user();
$rows = [
	['bi-person', 'Nom', $u['name']], ['bi-at', 'Pseudonyme', '@' . ($u['pseudo'] ?? '')], ['bi-envelope', 'E-mail', $u['email'] ?? ''],
	['bi-telephone', 'Téléphone', $u['phone'] ?? 'Non renseigné'], ['bi-geo-alt', 'Zone', trim(($u['commune'] ?? '') . ', ' . ($u['zone'] ?? ''), ', ')],
	['bi-person-badge', 'Profil', x_role_label($u['role'])], ['bi-calendar2-plus', 'Membre depuis', x_date_fr($u['joined'] ?? 'now')],
];
$them = array_slice(x_thematiques(), 0, 4);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
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
						<div class="card-header border-0 pb-0 d-flex justify-content-between"><h5 class="card-title mb-0">Informations</h5><a class="btn btn-primary-soft btn-sm" href="settings.php"><i class="bi bi-pencil me-1"></i>Modifier</a></div>
						<div class="card-body">
							<div class="row g-3">
								<?php foreach ($rows as $r): ?><div class="col-sm-6"><div class="d-flex gap-3"><i class="bi <?= $r[0] ?> fs-5 text-primary"></i><div><div class="small text-secondary"><?= e($r[1]) ?></div><div class="fw-bold"><?= e($r[2]) ?></div></div></div></div><?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="card">
						<div class="card-header border-0 pb-0"><h5 class="card-title mb-0">Centres d’intérêt</h5></div>
						<div class="card-body">
							<div class="d-flex flex-wrap gap-2"><?php foreach ($them as $t): ?><a class="xm-chip text-decoration-none" href="thematique.php?id=<?= (int)$t['id'] ?>"><?= e(mb_strimwidth($t['title'], 0, 48, '…')) ?></a><?php endforeach; ?></div>
						</div>
					</div>
					<div class="card card-body">
						<h5 class="card-title">Confidentialité de votre profil</h5>
						<p class="small text-secondary mb-2">Votre nom complet n’est visible que par vos connexions. Vos publications anonymes ne sont jamais liées à votre compte publiquement.</p>
						<a href="settings.php#confidentialite" class="small">Gérer ma confidentialité</a>
					</div>
				</div>
				<?= x_profile_side() ?>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
