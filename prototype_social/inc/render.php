<?php
/** Composants d'affichage réutilisables (cartes de publication, suggestions, etc.). */

function x_user_by_id($id) {
    foreach (store_all('users') as $u) if ((int)$u['id'] === (int)$id) return $u;
    return null;
}

/** Carte de publication (style Social). $p : ligne de la collection posts. */
function x_render_post(array $p) {
    $anon = !empty($p['anonymous']) || (int)$p['user'] === 0;
    $u = $anon ? null : x_user_by_id($p['user']);
    $name = $anon ? 'Membre anonyme' : ($u['name'] ?? 'Membre');
    $avatar = $anon ? img('personnage-1.png') : x_avatar($u['avatar'] ?? 0);
    $tagCls = ['Événement' => 'primary', 'Témoignage' => 'success', 'Ressource' => 'info', 'Question' => 'warning', 'Violences' => 'danger'][$p['tag'] ?? ''] ?? 'secondary';
    ob_start(); ?>
	<div class="card" id="post-<?= (int)$p['id'] ?>">
		<div class="card-header border-0 pb-0">
			<div class="d-flex align-items-center justify-content-between">
				<div class="d-flex align-items-center">
					<div class="avatar me-2"><img class="avatar-img rounded-circle" src="<?= $avatar ?>" alt="Avatar de <?= e($name) ?>" title="<?= e($name) ?>"></div>
					<div>
						<div class="nav nav-divider">
							<h6 class="nav-item card-title mb-0"><a href="<?= BASE ?>my-profile.php"><?= e($name) ?></a></h6>
							<span class="nav-item small"><?= e(x_ago($p['ts'])) ?></span>
						</div>
						<p class="mb-0 small"><span class="badge bg-<?= $tagCls ?> bg-opacity-10 text-<?= $tagCls ?>"><?= e($p['tag'] ?? 'Publication') ?></span></p>
					</div>
				</div>
				<div class="dropdown">
					<a href="#" class="text-secondary btn btn-secondary-soft-hover py-1 px-2" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-three-dots"></i></a>
					<ul class="dropdown-menu dropdown-menu-end">
						<li><a class="dropdown-item" href="#"><i class="bi bi-bookmark fa-fw pe-2"></i>Enregistrer</a></li>
						<li><a class="dropdown-item" href="<?= BASE ?>signalement.php?post=<?= (int)$p['id'] ?>"><i class="bi bi-flag fa-fw pe-2"></i>Signaler cette publication</a></li>
						<li><a class="dropdown-item" href="#"><i class="bi bi-x-circle fa-fw pe-2"></i>Masquer</a></li>
					</ul>
				</div>
			</div>
		</div>
		<div class="card-body">
			<p><?= nl2br(e($p['text'])) ?></p>
			<?php if (!empty($p['image'])): ?><img class="card-img" src="<?= img($p['image']) ?>" alt="Illustration de la publication de <?= e($name) ?>" title="Publication de <?= e($name) ?>" loading="lazy"><?php endif; ?>
			<ul class="nav nav-stack py-3 small">
				<li class="nav-item"><a class="nav-link" href="#" data-xm-like="<?= (int)$p['id'] ?>"><i class="bi bi-hand-thumbs-up pe-1"></i>J’aime (<span><?= (int)$p['likes'] ?></span>)</a></li>
				<li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-chat-fill pe-1"></i>Commentaires (<?= (int)$p['comments'] ?>)</a></li>
				<li class="nav-item ms-sm-auto"><a class="nav-link mb-0" href="#"><i class="bi bi-reply-fill flip-horizontal ps-1"></i>Partager</a></li>
			</ul>
			<div class="d-flex">
				<div class="avatar avatar-xs me-2"><img class="avatar-img rounded-circle" src="<?= x_avatar(x_user()['avatar'] ?? 0) ?>" alt="Votre avatar" title="Votre avatar"></div>
				<form class="nav nav-item w-100 position-relative" onsubmit="XM.toast('Commentaire publié');this.reset();return false;">
					<textarea data-autoresize class="form-control pe-5 bg-light" rows="1" placeholder="Ajouter un commentaire bienveillant…"></textarea>
					<button class="nav-link bg-transparent px-3 position-absolute top-50 end-0 translate-middle-y border-0" type="submit"><i class="bi bi-send-fill"></i></button>
				</form>
			</div>
		</div>
	</div>
<?php
    return ob_get_clean();
}

/** Bloc « Qui suivre » (compact). */
function x_render_who_to_follow($limit = 5) {
    $me = x_user()['id'] ?? 0;
    $users = array_values(array_filter(store_all('users'), fn($u) => $u['status'] === 'actif' && (int)$u['id'] !== (int)$me && !in_array($u['role'], ['superadmin'], true)));
    $users = array_slice($users, 0, $limit);
    $labels = ['membre' => 'Membre', 'pair' => 'Pair animateur·rice', 'admin' => 'Équipe Xomiton'];
    ob_start(); ?>
	<div class="card">
		<div class="card-header pb-0 border-0 d-flex justify-content-between align-items-center">
			<h5 class="card-title mb-0">Qui suivre</h5>
			<a class="small" href="<?= BASE ?>who-to-follow.php">Tout voir</a>
		</div>
		<div class="card-body">
			<?php foreach ($users as $u): ?>
			<div class="hstack gap-2 mb-3">
				<div class="avatar"><a href="<?= BASE ?>my-profile.php"><img class="avatar-img rounded-circle" src="<?= x_avatar($u['avatar']) ?>" alt="Avatar de <?= e($u['name']) ?>" title="<?= e($u['name']) ?>"></a></div>
				<div class="overflow-hidden">
					<a class="h6 mb-0" href="<?= BASE ?>my-profile.php"><?= e($u['name']) ?></a>
					<p class="mb-0 small text-truncate"><?= e($labels[$u['role']] ?? 'Membre') ?><?= $u['zone'] ? ' · ' . e($u['zone']) : '' ?></p>
				</div>
				<a class="btn btn-primary-soft rounded-circle icon-md ms-auto" href="<?= BASE ?>who-to-follow.php?invite=<?= (int)$u['id'] ?>" title="Envoyer une invitation"><i class="fa-solid fa-plus"></i></a>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php
    return ob_get_clean();
}

/**
 * Pagination « Charger plus » (côté serveur, sans JS requis).
 * Retourne [éléments à afficher, HTML du bouton]. Le paramètre ?n= conserve les filtres existants.
 */
function x_paginate(array $items, int $step = 6, string $param = 'n') {
    $total = count($items);
    $n = (int)($_GET[$param] ?? $step);
    $n = max($step, (int)(ceil($n / $step) * $step));
    $slice = array_slice($items, 0, $n);
    $btn = '';
    if ($total > $n) {
        $q = $_GET; $q[$param] = $n + $step;
        $href = '?' . http_build_query($q) . '#liste';
        $btn = '<div class="xm-load-more" data-xm-more><a class="btn btn-primary-soft px-4" href="' . e($href) . '" rel="nofollow">Charger plus <span class="small text-secondary ms-1">(' . count($slice) . ' sur ' . $total . ')</span></a></div>';
    } elseif ($total > $step) {
        $btn = '<p class="text-center small text-secondary mb-0" data-xm-more>Vous avez tout vu : ' . $total . ' éléments affichés.</p>';
    }
    return [$slice, $btn];
}
