<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$forums = x_forums();
$fmap = [];
foreach ($forums as $f) $fmap[$f[0]] = $f;
x_forum_topics();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'topic') {
	if (!csrf_ok()) x_redirect('forums.php', 'danger', 'Session expirée, veuillez réessayer.');
	$title = trim($_POST['title'] ?? ''); $body = trim($_POST['body'] ?? ''); $fid = $_POST['forum'] ?? '';
	if (mb_strlen($title) < 5 || mb_strlen($body) < 10 || !isset($fmap[$fid])) x_redirect('forums.php', 'danger', 'Renseignez un titre (5 caractères min.), un message (10 min.) et un forum.');
	$row = store_append('forum_topics', ['forum' => $fid, 'title' => $title, 'body' => $body, 'user' => !empty($_POST['anonymous']) ? 0 : x_user()['id'], 'views' => 0, 'ts' => time()]);
	x_log('Forum', 'Nouveau sujet : ' . $title);
	x_redirect('forum-topic.php?id=' . $row['id'], 'success', 'Votre sujet a été publié.');
}
$pageTitle = 'Forums';
$pageDesc = 'Posez vos questions et échangez avec la communauté Xomiton, dans le respect de la charte : droits, santé, soutien et autonomie.';
$nav = 'forums';
$cur = $_GET['forum'] ?? '';
$q = trim($_GET['q'] ?? '');
$topics = store_all('forum_topics');
usort($topics, fn($a, $b) => $b['ts'] <=> $a['ts']);
$replies = store_all('forum_replies');
$rc = [];
foreach ($replies as $r) $rc[$r['topic']] = ($rc[$r['topic']] ?? 0) + 1;
$tcount = [];
foreach ($topics as $t) $tcount[$t['forum']] = ($tcount[$t['forum']] ?? 0) + 1;
$topics = array_values(array_filter($topics, fn($t) => ($cur === '' || $t['forum'] === $cur) && ($q === '' || mb_stripos($t['title'] . ' ' . $t['body'], $q) !== false)));
[$topics, $moreBtn] = x_paginate($topics, 6);
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
					<?= flash_html() ?>
					<div class="card card-body">
						<div class="d-sm-flex justify-content-between align-items-center">
							<div><h1 class="h4 mb-1">Forums</h1><p class="small text-secondary mb-0">Posez vos questions et échangez avec la communauté, dans le respect de la charte.</p></div>
							<button class="btn btn-primary mt-3 mt-sm-0" type="button" data-bs-toggle="modal" data-bs-target="#modalTopic"><i class="bi bi-plus-circle me-1"></i>Nouveau sujet</button>
						</div>
					</div>

					<div class="row g-3">
						<?php foreach ($forums as $f): ?>
						<div class="col-sm-6">
							<a class="card card-body h-100 xm-card-hover text-decoration-none <?= $cur === $f[0] ? 'border-primary' : '' ?>" href="?forum=<?= e($f[0]) ?>">
								<div class="d-flex gap-3"><span class="xm-feature-icon"><i class="bi <?= e($f[3]) ?> fs-4 text-primary"></i></span>
									<div><h2 class="h6 mb-1"><?= e($f[1]) ?></h2><p class="small text-secondary mb-1"><?= e($f[2]) ?></p><p class="small mb-0"><?= (int)$f[4] + ($tcount[$f[0]] ?? 0) ?> sujets · <?= (int)$f[5] ?> messages</p></div>
								</div>
							</a>
						</div>
						<?php endforeach; ?>
					</div>

					<div class="card">
						<div class="card-header border-0 d-md-flex justify-content-between align-items-center">
							<h2 class="h6 mb-2 mb-md-0">Sujets récents<?= $cur && isset($fmap[$cur]) ? ' – ' . e($fmap[$cur][1]) . ' <a class="small ms-2" href="forums.php">Tous les forums</a>' : '' ?></h2>
							<form method="get" role="search"><?php if ($cur): ?><input type="hidden" name="forum" value="<?= e($cur) ?>"><?php endif; ?>
								<div class="input-group input-group-sm"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher un sujet…" aria-label="Rechercher"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button></div>
							</form>
						</div>
						<div class="list-group list-group-flush" id="liste">
							<?php foreach ($topics as $t): $u = $t['user'] ? x_user_by_id($t['user']) : null; ?>
							<a class="list-group-item list-group-item-action d-flex gap-3 py-3" href="forum-topic.php?id=<?= (int)$t['id'] ?>">
								<div class="avatar flex-shrink-0"><img class="avatar-img rounded-circle" src="<?= $u ? x_avatar($u['avatar']) : img('personnage-1.png') ?>" alt="Avatar de <?= e($u['name'] ?? 'Membre anonyme') ?>" title="<?= e($u['name'] ?? 'Membre anonyme') ?>" loading="lazy"></div>
								<div class="flex-grow-1 overflow-hidden">
									<div class="fw-bold"><?= e($t['title']) ?></div>
									<div class="small text-secondary"><?= e($u['name'] ?? 'Membre anonyme') ?> · <?= e($fmap[$t['forum']][1] ?? '') ?> · il y a <?= e(x_ago($t['ts'])) ?></div>
								</div>
								<div class="text-end small flex-shrink-0"><span class="d-block"><i class="bi bi-chat me-1"></i><?= (int)($rc[$t['id']] ?? 0) ?></span><span class="d-block text-secondary"><i class="bi bi-eye me-1"></i><?= (int)$t['views'] ?></span></div>
							</a>
							<?php endforeach; ?>
							<?php if (!$topics): ?><div class="list-group-item text-center text-secondary py-4">Aucun sujet pour le moment. Lancez la discussion !</div><?php endif; ?>
						</div>
					</div>
						<?= $moreBtn ?>
				</div>
				<?= x_right_column() ?>
			</div>
		</div>
	</main>

	<div class="modal fade" id="modalTopic" tabindex="-1" aria-labelledby="modalTopicLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content">
				<form method="post" data-xm-validate>
					<?= csrf_field() ?><input type="hidden" name="action" value="topic">
					<div class="modal-header"><h5 class="modal-title" id="modalTopicLabel">Nouveau sujet</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
					<div class="modal-body">
						<div class="mb-3"><label class="form-label" for="tforum">Forum</label><select class="form-select" id="tforum" name="forum"><?php foreach ($forums as $f): ?><option value="<?= e($f[0]) ?>" <?= $cur === $f[0] ? 'selected' : '' ?>><?= e($f[1]) ?></option><?php endforeach; ?></select></div>
						<div class="mb-3"><label class="form-label" for="ttitle">Titre</label><input class="form-control" id="ttitle" name="title" required minlength="5" maxlength="140"></div>
						<div class="mb-3"><label class="form-label" for="tbody">Message</label><textarea class="form-control" id="tbody" name="body" rows="5" required minlength="10"></textarea></div>
						<div class="form-check form-switch small"><input class="form-check-input" type="checkbox" role="switch" id="tanon" name="anonymous" value="1"><label class="form-check-label" for="tanon">Publier de façon anonyme</label></div>
					</div>
					<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Publier</button></div>
				</form>
			</div>
		</div>
	</div>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
