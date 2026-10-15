<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Mes connexions';
$nav = 'profil';
$profTab = 'connections';
$conn = x_connections(x_user()['id']);
$q = trim($_GET['q'] ?? '');
if ($q !== '') $conn = array_values(array_filter($conn, fn($c) => mb_stripos($c['name'] . $c['pseudo'] . $c['zone'], $q) !== false));
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
						<div class="card-header border-0 pb-0 d-sm-flex justify-content-between align-items-center">
							<h5 class="card-title mb-2 mb-sm-0">Connexions <span class="badge bg-primary bg-opacity-10 text-primary"><?= count($conn) ?></span></h5>
							<form method="get" role="search"><div class="input-group input-group-sm"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher…" aria-label="Rechercher"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button></div></form>
						</div>
						<div class="card-body">
							<div class="row g-3">
								<?php foreach ($conn as $c): ?>
								<div class="col-sm-6"><div class="d-flex align-items-center gap-3 border rounded p-2">
									<div class="avatar"><img class="avatar-img rounded-circle" src="<?= x_avatar($c['avatar']) ?>" alt=""></div>
									<div class="overflow-hidden flex-grow-1"><div class="h6 mb-0 text-truncate"><?= e($c['name']) ?></div><p class="small text-secondary mb-0 text-truncate"><?= e(x_role_label($c['role'])) ?> · <?= e($c['zone']) ?></p></div>
									<a class="btn btn-light btn-sm icon-md" href="messaging.php?with=<?= (int)$c['id'] ?>" title="Envoyer un message" aria-label="Envoyer un message"><i class="bi bi-chat-dots"></i></a>
								</div></div>
								<?php endforeach; ?>
								<?php if (!$conn): ?><div class="col-12 text-center text-secondary py-3">Aucune connexion trouvée. <a href="who-to-follow.php">Envoyer une invitation</a></div><?php endif; ?>
							</div>
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
