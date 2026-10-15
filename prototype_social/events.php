<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect('events.php', 'danger', 'Session expirée, veuillez réessayer.');
	$act = $_POST['action'] ?? '';
	if ($act === 'create') {
		$title = trim($_POST['title'] ?? ''); $date = $_POST['date'] ?? '';
		if (mb_strlen($title) < 3 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) x_redirect('events.php', 'danger', 'Titre (3 caractères min.) et date valides requis.');
		$time = preg_match('/^\d{2}:\d{2}$/', $_POST['time'] ?? '') ? $_POST['time'] : '15:00';
		$row = store_append('events', ['title' => $title, 'date' => $date, 'time' => $time, 'place' => trim($_POST['place'] ?? '') ?: 'À préciser',
			'image' => 'hero-05.jpg', 'description' => trim($_POST['description'] ?? ''), 'going' => 0, 'organizer' => x_user()['name'], 'cat' => 'Communauté']);
		x_log('Événement', 'Création : ' . $title);
		x_redirect('event-details.php?id=' . $row['id'], 'success', 'Événement créé.');
	}
	if ($act === 'going') {
		$ev = store_find('events', $_POST['id'] ?? 0);
		if ($ev) {
			$on = x_set_toggle('events_going', $ev['id']);
			x_log('Événement', ($on ? 'Participation : ' : 'Annulation : ') . $ev['title']);
			$back = (string)($_POST['back'] ?? '');
			if (!preg_match('/^(events\.php|event-details\.php\?id=\d+)$/', $back)) $back = 'events.php';
			x_redirect($back, 'success', $on ? 'Votre participation est enregistrée.' : 'Votre participation est annulée.');
		}
	}
}
$pageTitle = 'Événements';
$pageDesc = 'Ateliers, causeries, dépistages et formations organisés par la communauté Xomiton, près de chez vous ou en ligne.';
$pageImage = 'covers/og-default.jpg';
$nav = 'events';
$q = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? '';
$all = store_all('events');
usort($all, fn($a, $b) => strcmp($a['date'], $b['date']));
$cats = array_values(array_diff(array_unique(array_filter(array_column($all, 'cat'))), ['Causerie']));
$events = array_values(array_filter($all, function ($ev) use ($q, $cat) {
	if ($cat !== '' && ($ev['cat'] ?? '') !== $cat) return false;
	return $q === '' || mb_stripos($ev['title'] . ' ' . $ev['place'] . ' ' . $ev['description'], $q) !== false;
}));
[$events, $moreBtn] = x_paginate($events, 6);
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
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>
					<div class="card card-body">
						<div class="d-sm-flex justify-content-between align-items-center">
							<div><h1 class="h4 mb-1">Événements</h1><p class="small text-secondary mb-0">Ateliers, causeries, dépistages et formations près de chez vous ou en ligne.</p></div>
							<button class="btn btn-primary mt-3 mt-sm-0" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateEvents"><i class="bi bi-plus-circle me-1"></i>Créer un événement</button>
						</div>
						<hr>
						<div class="d-md-flex justify-content-between gap-3 align-items-center">
							<div class="d-flex flex-wrap gap-2 mb-3 mb-md-0">
								<a class="btn btn-sm <?= $cat === '' ? 'btn-primary' : 'btn-light' ?>" href="?">Tous</a>
								<?php foreach ($cats as $c): ?><a class="btn btn-sm <?= $cat === $c ? 'btn-primary' : 'btn-light' ?>" href="?cat=<?= urlencode($c) ?>"><?= e($c) ?></a><?php endforeach; ?>
							</div>
							<form method="get" role="search"><?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
								<div class="input-group"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un événement…" aria-label="Rechercher"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button></div>
							</form>
						</div>
					</div>

					<div class="row g-4" id="liste">
						<?php foreach ($events as $ev): $go = x_set_has('events_going', $ev['id']); $t = strtotime($ev['date']); ?>
						<div class="col-sm-6 col-xl-4">
							<div class="card h-100 overflow-hidden xm-card-hover">
								<a href="event-details.php?id=<?= (int)$ev['id'] ?>" class="position-relative d-block">
									<div class="xm-ratio-16x9"><img class="xm-img-cover" src="<?= img($ev['image']) ?>" alt="Visuel de l’événement : <?= e($ev['title']) ?>" title="<?= e($ev['title']) ?>" loading="lazy" width="640" height="360"></div>
									<span class="position-absolute top-0 start-0 m-2 bg-body rounded text-center px-2 py-1 shadow-sm"><span class="d-block h5 mb-0 lh-1"><?= date('j', $t) ?></span><small class="text-uppercase"><?= e(x_months_fr()[(int)date('n', $t) - 1]) ?></small></span>
								</a>
								<div class="card-body">
									<span class="xm-chip mb-2"><?= e($ev['cat'] ?? 'Événement') ?></span>
									<h2 class="h6 mb-2"><a href="event-details.php?id=<?= (int)$ev['id'] ?>"><?= e($ev['title']) ?></a></h2>
									<p class="small mb-1"><i class="bi bi-clock me-1"></i><?= e($ev['time']) ?> · <i class="bi bi-geo-alt me-1"></i><?= e($ev['place']) ?></p>
									<p class="small text-secondary mb-0"><i class="bi bi-people me-1"></i><?= (int)$ev['going'] + ($go ? 1 : 0) ?> participant·es</p>
								</div>
								<div class="card-footer bg-transparent border-0 pt-0">
									<form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="action" value="going"><input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
										<button class="btn btn-sm flex-grow-1 <?= $go ? 'btn-success-soft' : 'btn-primary-soft' ?>" type="submit"><i class="bi bi-<?= $go ? 'check2-circle' : 'calendar-plus' ?> me-1"></i><?= $go ? 'Je participe' : 'Je participe ?' ?></button>
										<a class="btn btn-sm btn-light" href="event-details.php?id=<?= (int)$ev['id'] ?>">Détails</a>
									</form>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
						<?php if (!$events): ?><div class="col-12"><div class="card card-body text-center text-secondary">Aucun événement trouvé.</div></div><?php endif; ?>
					</div>
					<?= $moreBtn ?>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
