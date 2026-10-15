<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$DEF = [1, 4];
$g = store_find('groups', $_GET['id'] ?? 0);
if (!$g) x_redirect('groups.php', 'warning', 'Ce groupe est introuvable.');
$in = x_set_has('groups', $g['id'], $DEF);
$pageTitle = $g['name'];
$pageImage = x_group_cover($g);
$pageImageAlt = 'Couverture du groupe : ' . $g['name'];
$nav = 'groupes';
$posts = store_all('posts');
usort($posts, fn($a, $b) => $b['ts'] <=> $a['ts']);
$posts = array_slice(array_filter($posts, fn($p) => ($p['visibility'] ?? 'public') === 'public'), (int)$g['id'] % 2, 2);
$users = array_values(array_filter(store_all('users'), fn($u) => $u['status'] === 'actif' && $u['role'] !== 'superadmin'));
$members = array_slice($users, 0, 8);
$tab = $_GET['tab'] ?? 'about';
$groupEvents = array_slice(store_all('events'), 0, 2);
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
					<div class="card overflow-hidden">
						<div class="xm-ratio-16x9" style="max-height:240px;aspect-ratio:auto;height:240px"><img class="xm-img-cover" src="<?= img(x_group_cover($g)) ?>" alt="Couverture du groupe : <?= e($g['name']) ?>" title="<?= e($g['name']) ?>"></div>
						<div class="card-body">
							<div class="d-md-flex align-items-center justify-content-between">
								<div>
									<h1 class="h4 mb-1"><?= e($g['name']) ?></h1>
									<ul class="nav nav-divider small text-secondary">
										<li class="nav-item"><i class="bi bi-<?= $g['privacy'] === 'Public' ? 'globe2' : 'lock' ?> me-1"></i>Groupe <?= e(mb_strtolower($g['privacy'])) ?></li>
										<li class="nav-item"><i class="bi bi-people me-1"></i><?= (int)$g['members'] ?> membres</li>
										<li class="nav-item"><i class="bi bi-geo-alt me-1"></i><?= e($g['zone']) ?></li>
									</ul>
								</div>
								<form method="post" action="groups.php" class="mt-3 mt-md-0 d-flex gap-2">
									<?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$g['id'] ?>"><input type="hidden" name="back" value="group-details.php?id=<?= (int)$g['id'] ?>">
									<button class="btn <?= $in ? 'btn-success-soft' : 'btn-primary' ?>" type="submit"><i class="bi bi-<?= $in ? 'check2' : 'plus-lg' ?> me-1"></i><?= $in ? 'Membre – Quitter' : 'Rejoindre le groupe' ?></button>
									<a class="btn btn-light" href="messaging.php" title="Discussion du groupe"><i class="bi bi-chat-dots"></i></a>
								</form>
							</div>
						</div>
						<div class="card-footer pt-0 border-0">
							<ul class="nav nav-pills nav-fill small gap-1">
								<li class="nav-item"><a class="nav-link <?= $tab === 'about' ? 'active' : 'bg-light' ?>" href="?id=<?= (int)$g['id'] ?>&tab=about">À propos</a></li>
								<li class="nav-item"><a class="nav-link <?= $tab === 'posts' ? 'active' : 'bg-light' ?>" href="?id=<?= (int)$g['id'] ?>&tab=posts">Publications</a></li>
								<li class="nav-item"><a class="nav-link <?= $tab === 'members' ? 'active' : 'bg-light' ?>" href="?id=<?= (int)$g['id'] ?>&tab=members">Membres</a></li>
							</ul>
						</div>
					</div>

					<?php if ($tab === 'about'): ?>
					<div class="card card-body">
						<h2 class="h6">À propos</h2>
						<p><?= e($g['desc']) ?></p>
						<p class="small text-secondary mb-0">Ce groupe est régi par la charte Xomiton : respect, confidentialité, aucune photo ni information sur une personne sans son accord. Tout contenu discriminatoire sera modéré.</p>
					</div>
					<?php elseif ($tab === 'posts'): ?>
						<?php if ($in): ?>
						<div class="card card-body">
							<div class="d-flex"><div class="avatar avatar-xs me-2"><img class="avatar-img rounded-circle" src="<?= x_avatar(x_user()['avatar'] ?? 0) ?>" alt="Votre avatar" title="Votre avatar"></div>
							<button class="form-control text-start text-secondary border-0 bg-light" type="button" data-bs-toggle="modal" data-bs-target="#modalCreateFeed">Écrire dans le groupe…</button></div>
						</div>
						<?php else: ?><div class="alert alert-info mb-0">Rejoignez le groupe pour publier et commenter.</div><?php endif; ?>
						<?php foreach ($posts as $p) echo x_render_post($p); ?>
					<?php else: ?>
					<div class="card">
						<div class="card-header border-0 pb-0"><h2 class="h6 mb-0">Membres</h2></div>
						<div class="card-body">
							<div class="row g-3">
								<?php foreach ($users as $m): ?>
								<div class="col-sm-6"><div class="hstack gap-2">
									<div class="avatar"><img class="avatar-img rounded-circle" src="<?= x_avatar($m['avatar']) ?>" alt="Avatar de <?= e($m['name']) ?>" title="<?= e($m['name']) ?>"></div>
									<div class="overflow-hidden"><div class="h6 mb-0"><?= e($m['name']) ?></div><p class="small mb-0 text-secondary"><?= e(x_role_label($m['role'])) ?> · <?= e($m['zone']) ?></p></div>
								</div></div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<?php endif; ?>
				</div>

				<div class="col-lg-4">
					<div class="row g-4">
						<div class="col-sm-6 col-lg-12">
							<div class="card">
								<div class="card-header pb-0 border-0 d-flex justify-content-between"><h5 class="card-title mb-0">Membres</h5><a class="small" href="?id=<?= (int)$g['id'] ?>&tab=members">Tout voir</a></div>
								<div class="card-body">
									<div class="d-flex flex-wrap gap-2">
										<?php foreach ($members as $m): ?><div class="avatar" title="<?= e($m['name']) ?>"><img class="avatar-img rounded-circle" src="<?= x_avatar($m['avatar']) ?>" alt="Avatar de <?= e($m['name']) ?>" title="<?= e($m['name']) ?>"></div><?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-6 col-lg-12">
							<div class="card">
								<div class="card-header pb-0 border-0"><h5 class="card-title mb-0">Événements du groupe</h5></div>
								<div class="card-body">
									<?php foreach ($groupEvents as $ev): ?>
									<div class="d-flex mb-3"><div class="avatar"><img class="avatar-img rounded-2" src="<?= img($ev['image']) ?>" alt="Visuel de l’événement : <?= e($ev['title']) ?>" title="<?= e($ev['title']) ?>" loading="lazy"></div>
									<div class="ms-2"><h6 class="card-title mb-0"><a href="event-details.php?id=<?= (int)$ev['id'] ?>"><?= e($ev['title']) ?></a></h6><p class="small mb-0"><?= e(x_date_fr($ev['date'], false)) ?> · <?= e($ev['place']) ?></p></div></div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
						<div class="col-12"><a class="btn btn-light w-100" href="groups.php"><i class="bi bi-arrow-left me-1"></i>Tous les groupes</a></div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
