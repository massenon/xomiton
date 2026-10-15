<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Mon activité';
$nav = 'profil';
$profTab = 'activity';
$mail = x_user()['email'] ?? '';
$log = array_values(array_filter(store_all('journal'), fn($j) => ($j['user'] ?? '') === $mail));
usort($log, fn($a, $b) => strcmp($b['at'], $a['at']));
$log = array_slice($log, 0, 40);
$icons = ['Connexion' => 'bi-box-arrow-in-right', 'Groupe' => 'bi-people', 'Page' => 'bi-flag', 'Événement' => 'bi-calendar-event', 'Forum' => 'bi-chat-square-text', 'Invitation' => 'bi-person-plus', 'Publication' => 'bi-pencil-square', 'Paramètres' => 'bi-gear', 'Mot de passe' => 'bi-key'];
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
						<div class="card-header border-0 pb-0"><h5 class="card-title mb-0">Journal de mon activité</h5></div>
						<div class="card-body">
							<?php foreach ($log as $j): ?>
							<div class="d-flex gap-3 pb-3 mb-3 border-bottom">
								<span class="icon-md rounded-circle bg-primary bg-opacity-10 text-primary flex-shrink-0 d-flex align-items-center justify-content-center"><i class="bi <?= $icons[$j['action']] ?? 'bi-activity' ?>"></i></span>
								<div><div class="fw-bold"><?= e($j['action']) ?></div><div class="small"><?= e($j['detail']) ?></div><div class="small text-secondary"><?= e(x_date_fr($j['at'])) ?> à <?= e(substr($j['at'], 11, 5)) ?></div></div>
							</div>
							<?php endforeach; ?>
							<?php if (!$log): ?><p class="text-center text-secondary mb-0">Aucune activité enregistrée pour le moment.</p><?php endif; ?>
						</div>
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
