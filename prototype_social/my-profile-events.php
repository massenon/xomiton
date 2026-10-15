<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$pageTitle = 'Mes événements';
$nav = 'profil';
$profTab = 'events';
$all = store_all('events');
usort($all, fn($a, $b) => strcmp($a['date'], $b['date']));
$mine = array_values(array_filter($all, fn($ev) => x_set_has('events_going', $ev['id']) || ($ev['organizer'] ?? '') === x_user()['name']));
$sugg = array_slice(array_values(array_filter($all, fn($ev) => !in_array($ev, $mine, true))), 0, 3);
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
						<div class="card-header border-0 pb-0 d-flex justify-content-between"><h5 class="card-title mb-0">Événements auxquels je participe</h5><button class="btn btn-primary-soft btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreateEvents">Créer</button></div>
						<div class="card-body">
							<?php foreach ($mine as $ev): ?>
							<div class="d-flex gap-3 mb-3"><div class="avatar avatar-lg"><img class="avatar-img rounded-2" src="<?= img($ev['image']) ?>" alt=""></div>
								<div><h6 class="mb-0"><a href="event-details.php?id=<?= (int)$ev['id'] ?>"><?= e($ev['title']) ?></a></h6><p class="small text-secondary mb-0"><i class="bi bi-calendar-event me-1"></i><?= e(x_date_fr($ev['date'])) ?> · <?= e($ev['time']) ?> · <?= e($ev['place']) ?></p></div></div>
							<?php endforeach; ?>
							<?php if (!$mine): ?><p class="text-secondary text-center mb-0">Vous ne participez à aucun événement pour l’instant. <a href="events.php">Découvrir les événements</a></p><?php endif; ?>
						</div>
					</div>
					<?php if ($sugg): ?><div class="card">
						<div class="card-header border-0 pb-0"><h5 class="card-title mb-0">À venir pour vous</h5></div>
						<div class="card-body"><?php foreach ($sugg as $ev): ?>
							<div class="d-flex gap-3 mb-3"><div class="avatar avatar-lg"><img class="avatar-img rounded-2" src="<?= img($ev['image']) ?>" alt=""></div>
								<div><h6 class="mb-0"><a href="event-details.php?id=<?= (int)$ev['id'] ?>"><?= e($ev['title']) ?></a></h6><p class="small text-secondary mb-0"><?= e(x_date_fr($ev['date'])) ?> · <?= e($ev['place']) ?></p></div></div>
						<?php endforeach; ?></div>
					</div><?php endif; ?>
				</div>
				<?= x_profile_side() ?>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
