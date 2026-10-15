<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
x_forum_topics();
$t = store_find('forum_topics', $_GET['id'] ?? 0);
if (!$t) x_redirect('forums.php', 'warning', 'Ce sujet est introuvable.');
$self = 'forum-topic.php?id=' . (int)$t['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect($self, 'danger', 'Session expirée, veuillez réessayer.');
	$text = trim($_POST['text'] ?? '');
	if (mb_strlen($text) < 2) x_redirect($self, 'danger', 'Votre réponse est vide.');
	store_append('forum_replies', ['topic' => (int)$t['id'], 'user' => !empty($_POST['anonymous']) ? 0 : x_user()['id'], 'text' => $text, 'ts' => time()]);
	x_log('Forum', 'Réponse au sujet #' . $t['id']);
	x_redirect($self . '#reponses', 'success', 'Votre réponse a été publiée.');
}
if (empty($_SESSION['x_seen_topic'][$t['id']])) { $_SESSION['x_seen_topic'][$t['id']] = 1; store_update('forum_topics', $t['id'], ['views' => (int)$t['views'] + 1]); $t['views']++; }
$fmap = [];
foreach (x_forums() as $f) $fmap[$f[0]] = $f;
$replies = array_values(array_filter(store_all('forum_replies'), fn($r) => (int)$r['topic'] === (int)$t['id']));
usort($replies, fn($a, $b) => $a['ts'] <=> $b['ts']);
$author = $t['user'] ? x_user_by_id($t['user']) : null;
$pageTitle = $t['title'];
$nav = 'forums';
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
					<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="forums.php">Forums</a></li><li class="breadcrumb-item"><a href="forums.php?forum=<?= e($t['forum']) ?>"><?= e($fmap[$t['forum']][1] ?? 'Forum') ?></a></li></ol></nav>
					<div class="card">
						<div class="card-body">
							<h1 class="h5"><?= e($t['title']) ?></h1>
							<div class="d-flex align-items-center mb-3">
								<div class="avatar avatar-xs me-2"><img class="avatar-img rounded-circle" src="<?= $author ? x_avatar($author['avatar']) : img('personnage-1.png') ?>" alt=""></div>
								<div class="small"><strong><?= e($author['name'] ?? 'Membre anonyme') ?></strong> · il y a <?= e(x_ago($t['ts'])) ?> · <i class="bi bi-eye"></i> <?= (int)$t['views'] ?></div>
							</div>
							<p class="mb-0"><?= nl2br(e($t['body'])) ?></p>
						</div>
					</div>

					<h2 class="h6 mb-0" id="reponses"><?= count($replies) ?> réponse<?= count($replies) > 1 ? 's' : '' ?></h2>
					<?php foreach ($replies as $r): $ru = $r['user'] ? x_user_by_id($r['user']) : null; ?>
					<div class="card"><div class="card-body d-flex gap-3">
						<div class="avatar flex-shrink-0"><img class="avatar-img rounded-circle" src="<?= $ru ? x_avatar($ru['avatar']) : img('personnage-1.png') ?>" alt=""></div>
						<div><div class="small mb-1"><strong><?= e($ru['name'] ?? 'Membre anonyme') ?></strong><?php if ($ru && in_array($ru['role'], ['pair', 'admin', 'superadmin'], true)): ?> <span class="badge bg-primary bg-opacity-10 text-primary"><?= e(x_role_label($ru['role'])) ?></span><?php endif; ?> · il y a <?= e(x_ago($r['ts'])) ?></div><p class="mb-0"><?= nl2br(e($r['text'])) ?></p></div>
					</div></div>
					<?php endforeach; ?>

					<div class="card"><div class="card-body">
						<form method="post" data-xm-validate><?= csrf_field() ?>
							<label class="form-label" for="rtext">Votre réponse</label>
							<textarea class="form-control mb-3" id="rtext" name="text" rows="3" required minlength="2" placeholder="Répondez avec bienveillance…"></textarea>
							<div class="d-flex justify-content-between align-items-center">
								<div class="form-check form-switch small"><input class="form-check-input" type="checkbox" role="switch" id="ranon" name="anonymous" value="1"><label class="form-check-label" for="ranon">Anonyme</label></div>
								<button class="btn btn-primary" type="submit">Répondre</button>
							</div>
						</form>
					</div></div>
				</div>
				<?= x_right_column() ?>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
