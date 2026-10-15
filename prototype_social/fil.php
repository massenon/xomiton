<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
$pageTitle = 'Mon fil';
$pageDesc = 'Votre fil Xomiton : témoignages, questions, ressources et événements de la communauté.';
$nav = 'fil';
$posts = store_all('posts');
usort($posts, fn($a, $b) => $b['ts'] <=> $a['ts']);
$posts = array_filter($posts, fn($p) => ($p['visibility'] ?? 'public') !== 'me' || (int)$p['user'] === (int)x_user()['id']);
$tags = ['' => 'Tout', 'Témoignage' => 'Témoignages', 'Question' => 'Questions', 'Ressource' => 'Ressources', 'Événement' => 'Événements', 'Sondage' => 'Sondages'];
$f = $_GET['tag'] ?? '';
if ($f) $posts = array_filter($posts, fn($p) => ($p['tag'] ?? '') === $f);
[$posts, $moreBtn] = x_paginate(array_values($posts), 6);
$them = array_slice(x_thematiques(), 0, 6);
$cats = x_thematique_cats();
$events = array_slice(store_all('events'), 0, 3);
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
				<?php include("partials/sidenav.php"); ?>

				<div class="col-md-8 col-lg-6 vstack gap-4">
					<h1 class="visually-hidden">Mon fil d’actualité</h1>
					<?= flash_html() ?>

					<!-- Accès rapides -->
					<div class="row g-2">
						<div class="col-6 col-sm-3"><a class="card card-body text-center py-3 h-100 xm-card-hover border-danger" href="sos.php"><i class="bi bi-broadcast fs-3 text-danger"></i><span class="small fw-bold mt-1">SOS</span></a></div>
						<div class="col-6 col-sm-3"><a class="card card-body text-center py-3 h-100 xm-card-hover" href="signalement.php"><i class="bi bi-megaphone fs-3 text-primary"></i><span class="small fw-bold mt-1">Signaler</span></a></div>
						<div class="col-6 col-sm-3"><a class="card card-body text-center py-3 h-100 xm-card-hover" href="assistant.php"><i class="bi bi-robot fs-3" style="color:#6F2C91"></i><span class="small fw-bold mt-1">Assistant chat</span></a></div>
						<div class="col-6 col-sm-3"><a class="card card-body text-center py-3 h-100 xm-card-hover" href="urgences.php"><i class="bi bi-telephone-fill fs-3 text-success"></i><span class="small fw-bold mt-1">Urgences</span></a></div>
					</div>

					<!-- Créer une publication -->
					<div class="card card-body">
						<div class="d-flex mb-3">
							<div class="avatar avatar-xs me-2"><a href="my-profile.php"><img class="avatar-img rounded-circle" src="<?= x_avatar(x_user()['avatar'] ?? 0) ?>" alt="Votre avatar" title="Votre avatar"></a></div>
							<button class="form-control text-start text-secondary border-0 bg-light" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateFeed">Partagez vos pensées, <?= e(explode(' ', x_user()['name'])[0]) ?>…</button>
						</div>
						<ul class="nav nav-pills nav-stack small fw-normal">
							<li class="nav-item"><a class="nav-link bg-light py-1 px-2 mb-0" href="#" data-bs-toggle="modal" data-bs-target="#modalCreateFeed"><i class="bi bi-image-fill text-success pe-2"></i>Photo</a></li>
							<li class="nav-item"><a class="nav-link bg-light py-1 px-2 mb-0" href="#" data-bs-toggle="modal" data-bs-target="#modalCreateEvents"><i class="bi bi-calendar2-event-fill text-danger pe-2"></i>Événement</a></li>
							<li class="nav-item"><a class="nav-link bg-light py-1 px-2 mb-0" href="#" data-bs-toggle="modal" data-bs-target="#modalCreatePoll"><i class="bi bi-bar-chart-fill text-info pe-2"></i>Sondage</a></li>
							<li class="nav-item"><a class="nav-link bg-light py-1 px-2 mb-0" href="violences.php"><i class="bi bi-shield-exclamation text-warning pe-2"></i>Violences</a></li>
							<li class="nav-item"><a class="nav-link bg-light py-1 px-2 mb-0" href="signalement.php"><i class="bi bi-megaphone-fill text-primary pe-2"></i>Signaler</a></li>
						</ul>
					</div>

					<!-- À la une -->
					<div>
						<div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">À la une – Thématiques</h6><a class="small" href="thematiques.php">Voir tout</a></div>
						<div class="d-flex gap-3 overflow-auto pb-2">
							<?php foreach ($them as $t): ?>
							<a href="thematique.php?id=<?= $t['id'] ?>" class="card xm-card-hover flex-shrink-0 overflow-hidden" style="width:190px">
								<div class="xm-ratio-4x3"><img class="xm-img-cover" src="<?= img($t['cover']) ?>" alt="Couverture de la fiche : <?= e($t['title']) ?>" title="<?= e($t['title']) ?>" loading="lazy"></div>
								<div class="card-body p-2"><span class="xm-chip mb-1"><?= e($cats[$t['cat']][0]) ?></span><p class="small fw-bold mb-0 lh-sm" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= e($t['title']) ?></p></div>
							</a>
							<?php endforeach; ?>
						</div>
					</div>

					<!-- Filtres -->
					<ul class="nav nav-pills gap-2">
						<?php foreach ($tags as $k => $lbl): ?><li class="nav-item"><a class="nav-link py-1 px-3 <?= $f === $k ? 'active' : 'bg-light' ?>" href="?tag=<?= urlencode($k) ?>"><?= e($lbl) ?></a></li><?php endforeach; ?>
					</ul>

					<!-- Fil -->
					<div class="vstack gap-4" id="liste"><?php foreach ($posts as $p) echo x_render_post($p); ?></div>
					<?php if (!$posts): ?><div class="card card-body text-center text-secondary">Aucune publication pour ce filtre.</div><?php endif; ?>

					<!-- Mur : fin -->
					<?= $moreBtn ?>
				</div>

				<div class="col-lg-3">
					<div class="row g-4">
						<div class="col-sm-6 col-lg-12"><?= x_render_who_to_follow(5) ?></div>

						<div class="col-sm-6 col-lg-12">
							<div class="card">
								<div class="card-header pb-0 border-0 d-flex justify-content-between"><h5 class="card-title mb-0">Événements</h5><a class="small" href="events.php">Tout voir</a></div>
								<div class="card-body">
									<?php foreach ($events as $ev): ?>
									<div class="d-flex mb-3">
										<div class="avatar"><img class="avatar-img rounded-2" src="<?= img($ev['image']) ?>" alt="Visuel de l’événement : <?= e($ev['title']) ?>" title="<?= e($ev['title']) ?>" loading="lazy"></div>
										<div class="ms-2"><h6 class="card-title mb-0"><a href="events.php"><?= e($ev['title']) ?></a></h6><p class="small mb-0"><?= e(date('d/m', strtotime($ev['date']))) ?> · <?= e($ev['place']) ?></p></div>
									</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>

						<div class="col-12">
							<div class="card border-danger">
								<div class="card-body">
									<h6 class="text-danger"><i class="bi bi-telephone-fill me-1"></i> En cas d’urgence</h6>
									<p class="small mb-2">Ligne d’écoute Xomiton, juristes et psychologues joignables 24/7.</p>
									<a class="btn btn-danger btn-sm w-100" href="urgences.php">Contacts d’urgence</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
	<script>
	document.querySelectorAll('[data-xm-like]').forEach(a => a.addEventListener('click', ev => { ev.preventDefault(); const s = a.querySelector('span'); const on = a.classList.toggle('active'); s.textContent = (+s.textContent) + (on ? 1 : -1); }));
	</script>
</body>
</html>
