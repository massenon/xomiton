<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Mon profil';
$nav = 'profil';
$profTab = 'posts';
$posts = x_user_posts(x_user()['id']);
[$posts, $moreBtn] = x_paginate(array_values($posts), 6);
$pageDesc = 'Votre profil Xomiton : vos publications, connexions, médias et événements.';
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
					<div class="card card-body">
						<div class="d-flex mb-3">
							<div class="avatar avatar-xs me-2"><img class="avatar-img rounded-circle" src="<?= x_avatar(x_user()['avatar'] ?? 0) ?>" alt="Votre avatar" title="Votre avatar"></div>
							<button class="form-control text-start text-secondary border-0 bg-light" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateFeed">Partagez vos pensées, <?= e(explode(' ', x_user()['name'])[0]) ?>…</button>
						</div>
					</div>
					<div class="vstack gap-4" id="liste"><?php foreach ($posts as $p) echo x_render_post($p); ?></div>
					<?php if (!$posts): ?><div class="card card-body text-center text-secondary"><i class="bi bi-chat-square-heart fs-2 mb-2"></i>Vous n’avez pas encore publié. Partagez un témoignage, une question ou une ressource avec la communauté.</div><?php endif; ?>
					<?= $moreBtn ?>
				</div>
				<?= x_profile_side() ?>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
