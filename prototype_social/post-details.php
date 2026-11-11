<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$p = store_find('posts', $_GET['id'] ?? 0);
if (!$p) x_redirect('fil.php', 'warning', 'Cette publication est introuvable.');
$self = 'post-details.php?id=' . (int)$p['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect($self, 'danger', 'Session expirée, veuillez réessayer.');
	$text = trim($_POST['text'] ?? '');
	if (mb_strlen($text) < 2) x_redirect($self, 'danger', 'Le commentaire est vide.');
	store_append('post_comments', ['post' => (int)$p['id'], 'user' => x_user()['id'], 'text' => $text, 'ts' => time()]);
	store_update('posts', $p['id'], ['comments' => (int)$p['comments'] + 1]);
	x_log('Publication', 'Commentaire sur #' . $p['id']);
	x_redirect($self . '#commentaires', 'success', 'Commentaire publié.');
}
$p = store_find('posts', $p['id']);
$base = [
	[12, 'Merci pour ce partage, ça fait du bien de se sentir entourée. 💜'],
	[4, 'N’hésitez pas à nous écrire en message privé si vous avez besoin d’un accompagnement.'],
	[10, 'Très utile, je partage avec mon groupe.'],
];
$comments = [];
foreach ($base as $i => $b) $comments[] = ['user' => $b[0], 'text' => $b[1], 'ts' => (int)$p['ts'] + 1800 * ($i + 1)];
foreach (store_all('post_comments') as $c) if ((int)$c['post'] === (int)$p['id']) $comments[] = $c;
$pageTitle = 'Publication';
$nav = 'fil';
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
					<a class="small" href="fil.php"><i class="bi bi-arrow-left me-1"></i>Retour au fil</a>
					<?= x_render_post($p) ?>
					<div class="card" id="commentaires">
						<div class="card-header border-0 pb-0"><h2 class="h6 mb-0"><?= count($comments) ?> commentaire<?= count($comments) > 1 ? 's' : '' ?></h2></div>
						<div class="card-body">
							<?php foreach ($comments as $c): $u = x_user_by_id($c['user']); ?>
							<div class="d-flex gap-2 mb-3">
								<div class="avatar avatar-xs flex-shrink-0"><img class="avatar-img rounded-circle" src="<?= x_avatar($u['avatar'] ?? 0) ?>" alt=""></div>
								<div class="bg-light rounded p-2 px-3 flex-grow-1"><div class="d-flex justify-content-between"><strong class="small"><?= e($u['name'] ?? 'Membre') ?></strong><span class="small text-secondary">il y a <?= e(x_ago($c['ts'])) ?></span></div><p class="small mb-0"><?= nl2br(e($c['text'])) ?></p></div>
							</div>
							<?php endforeach; ?>
							<form method="post" class="d-flex gap-2 mt-3" data-xm-validate><?= csrf_field() ?>
								<div class="avatar avatar-xs flex-shrink-0"><img class="avatar-img rounded-circle" src="<?= x_avatar(x_user()['avatar'] ?? 0) ?>" alt=""></div>
								<textarea class="form-control bg-light" name="text" rows="1" required minlength="2" data-autoresize placeholder="Ajouter un commentaire bienveillant…" aria-label="Commentaire"></textarea>
								<button class="btn btn-primary" type="submit">Publier</button>
							</form>
						</div>
					</div>
				</div>
				<?= x_right_column() ?>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
