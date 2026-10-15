<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$ev = store_find('events', $_GET['id'] ?? 0);
if (!$ev) x_redirect('events.php', 'warning', 'Cet événement est introuvable.');
$go = x_set_has('events_going', $ev['id']);
$t = strtotime($ev['date']);
$past = $t < strtotime('today');
$pageTitle = $ev['title'];
$nav = 'events';
$others = array_slice(array_values(array_filter(store_all('events'), fn($x) => $x['id'] != $ev['id'])), 0, 3);
$users = array_slice(array_values(array_filter(store_all('users'), fn($u) => $u['status'] === 'actif' && $u['role'] !== 'superadmin')), 0, 6);
$wd = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int)date('w', $t)];
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
					<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="events.php">Événements</a></li><li class="breadcrumb-item active" aria-current="page"><?= e($ev['title']) ?></li></ol></nav>
					<div class="card overflow-hidden">
						<div style="height:260px"><img class="xm-img-cover" src="<?= img($ev['image']) ?>" alt=""></div>
						<div class="card-body">
							<span class="xm-chip mb-2"><?= e($ev['cat'] ?? 'Événement') ?></span>
							<h1 class="h4"><?= e($ev['title']) ?></h1>
							<ul class="nav nav-divider small mb-3">
								<li class="nav-item"><i class="bi bi-calendar-event me-1"></i><?= e($wd . ' ' . x_date_fr($ev['date'])) ?></li>
								<li class="nav-item"><i class="bi bi-clock me-1"></i><?= e($ev['time']) ?></li>
								<li class="nav-item"><i class="bi bi-geo-alt me-1"></i><?= e($ev['place']) ?></li>
							</ul>
							<h2 class="h6">Description</h2>
							<p><?= nl2br(e($ev['description'] ?: 'Aucune description renseignée.')) ?></p>
							<p class="small text-secondary mb-3">Organisé par <strong><?= e($ev['organizer'] ?? 'Équipe Xomiton') ?></strong>. L’événement se déroule dans le respect de la charte Xomiton : confidentialité, bienveillance et consentement pour toute photo.</p>
							<form method="post" action="events.php" class="d-flex flex-wrap gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="going"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>"><input type="hidden" name="back" value="event-details.php?id=<?= (int)$ev['id'] ?>">
								<?php if ($past): ?><span class="badge bg-secondary align-self-center">Événement passé</span>
								<?php else: ?><button class="btn <?= $go ? 'btn-success-soft' : 'btn-primary' ?>" type="submit"><i class="bi bi-<?= $go ? 'check2-circle' : 'calendar-plus' ?> me-1"></i><?= $go ? 'Je participe – annuler' : 'Je participe' ?></button><?php endif; ?>
								<button class="btn btn-light" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText(location.href);XM.toast('Lien copié')"><i class="bi bi-share me-1"></i>Partager</button>
							</form>
						</div>
					</div>
				</div>
				<div class="col-lg-4">
					<div class="row g-4">
						<div class="col-sm-6 col-lg-12">
							<div class="card">
								<div class="card-header border-0 pb-0"><h5 class="card-title mb-0"><?= (int)$ev['going'] + ($go ? 1 : 0) ?> participant·es</h5></div>
								<div class="card-body">
									<ul class="avatar-group list-unstyled align-items-center mb-0">
										<?php foreach ($users as $u): ?><li class="avatar avatar-xs"><img class="avatar-img rounded-circle" src="<?= x_avatar($u['avatar']) ?>" alt="<?= e($u['name']) ?>"></li><?php endforeach; ?>
									</ul>
								</div>
							</div>
						</div>
						<div class="col-sm-6 col-lg-12">
							<div class="card">
								<div class="card-header border-0 pb-0"><h5 class="card-title mb-0">Autres événements</h5></div>
								<div class="card-body">
									<?php foreach ($others as $o): ?>
									<div class="d-flex mb-3"><div class="avatar"><img class="avatar-img rounded-2" src="<?= img($o['image']) ?>" alt=""></div>
									<div class="ms-2"><h6 class="card-title mb-0"><a href="event-details.php?id=<?= (int)$o['id'] ?>"><?= e($o['title']) ?></a></h6><p class="small mb-0"><?= e(x_date_fr($o['date'], false)) ?> · <?= e($o['place']) ?></p></div></div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<div class="col-12"><a class="btn btn-light w-100" href="events.php"><i class="bi bi-arrow-left me-1"></i>Tous les événements</a></div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
