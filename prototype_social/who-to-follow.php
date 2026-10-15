<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$me = (int)x_user()['id'];

function x_invite_exists($a, $b) {
	foreach (store_all('invitations') as $i) {
		if ($i['status'] === 'refusee') continue;
		if (((int)$i['from_user'] === $a && (int)$i['to_user'] === $b) || ((int)$i['from_user'] === $b && (int)$i['to_user'] === $a)) return $i;
	}
	return null;
}
function x_send_invite($me, $to) {
	$u = x_user_by_id($to);
	if (!$u || $to === $me || $u['status'] !== 'actif') return [false, 'Cette personne ne peut pas être invitée.'];
	if (x_invite_exists($me, $to)) return [false, 'Une invitation ou une connexion existe déjà avec ' . $u['name'] . '.'];
	store_append('invitations', ['from_user' => $me, 'to_user' => $to, 'status' => 'en_attente', 'date' => time()]);
	x_log('Invitation', 'Envoyée à ' . $u['name']);
	return [true, 'Invitation envoyée à ' . $u['name'] . '.'];
}

if (isset($_GET['invite'])) {
	[$ok, $msg] = x_send_invite($me, (int)$_GET['invite']);
	x_redirect('who-to-follow.php?tab=envoyees', $ok ? 'success' : 'warning', $msg);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect('who-to-follow.php', 'danger', 'Session expirée, veuillez réessayer.');
	$act = $_POST['action'] ?? '';
	if ($act === 'invite') {
		[$ok, $msg] = x_send_invite($me, (int)($_POST['to'] ?? 0));
		x_redirect('who-to-follow.php', $ok ? 'success' : 'warning', $msg);
	}
	if (in_array($act, ['accept', 'decline', 'cancel'], true)) {
		$inv = store_find('invitations', $_POST['id'] ?? 0);
		if ($inv && $act === 'cancel' && (int)$inv['from_user'] === $me) {
			store_delete('invitations', $inv['id']);
			x_redirect('who-to-follow.php?tab=envoyees', 'success', 'Invitation annulée.');
		}
		if ($inv && (int)$inv['to_user'] === $me && $inv['status'] === 'en_attente') {
			store_update('invitations', $inv['id'], ['status' => $act === 'accept' ? 'acceptee' : 'refusee']);
			x_log('Invitation', ($act === 'accept' ? 'Acceptée' : 'Refusée') . ' #' . $inv['id']);
			x_redirect('who-to-follow.php?tab=recues', 'success', $act === 'accept' ? 'Invitation acceptée : vous êtes connecté·es.' : 'Invitation refusée.');
		}
	}
	x_redirect('who-to-follow.php', 'warning', 'Action impossible.');
}

$pageTitle = 'Qui suivre et invitations';
$nav = 'follow';
$tab = in_array($_GET['tab'] ?? '', ['recues', 'envoyees'], true) ? $_GET['tab'] : 'suggestions';
$q = trim($_GET['q'] ?? ''); $zone = $_GET['zone'] ?? ''; $role = $_GET['role'] ?? '';
$inv = store_all('invitations');
$recues = array_values(array_filter($inv, fn($i) => (int)$i['to_user'] === $me && $i['status'] === 'en_attente'));
$envoyees = array_values(array_filter($inv, fn($i) => (int)$i['from_user'] === $me && $i['status'] === 'en_attente'));
$related = [];
foreach ($inv as $i) {
	if ($i['status'] === 'refusee') continue;
	if ((int)$i['from_user'] === $me) $related[(int)$i['to_user']] = $i['status'];
	if ((int)$i['to_user'] === $me) $related[(int)$i['from_user']] = $i['status'];
}
$users = array_values(array_filter(store_all('users'), function ($u) use ($me, $q, $zone, $role, $related) {
	if ($u['status'] !== 'actif' || (int)$u['id'] === $me || $u['role'] === 'superadmin' || isset($related[(int)$u['id']])) return false;
	if ($zone !== '' && $u['zone'] !== $zone) return false;
	if ($role !== '' && $u['role'] !== $role) return false;
	return $q === '' || mb_stripos($u['name'] . ' ' . $u['pseudo'], $q) !== false;
}));
$qs = fn($t) => '?tab=' . $t;
$person = function ($uid, $sub) {
	$u = x_user_by_id($uid);
	if (!$u) return '';
	return '<div class="hstack gap-3 flex-grow-1 overflow-hidden"><div class="avatar"><img class="avatar-img rounded-circle" src="' . x_avatar($u['avatar']) . '" alt=""></div><div class="overflow-hidden"><div class="h6 mb-0">' . e($u['name']) . '</div><p class="small text-secondary mb-0 text-truncate">' . e($sub($u)) . '</p></div></div>';
};
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
						<h1 class="h4 mb-1">Qui suivre et invitations</h1>
						<p class="small text-secondary mb-3">Élargissez votre réseau de soutien : invitez des pair·es, des pair animateur·rices et des membres de votre zone.</p>
						<ul class="nav nav-pills gap-2">
							<li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'suggestions' ? 'active' : 'bg-light' ?>" href="<?= $qs('suggestions') ?>">Suggestions</a></li>
							<li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'recues' ? 'active' : 'bg-light' ?>" href="<?= $qs('recues') ?>">Invitations reçues <span class="badge bg-danger ms-1"><?= count($recues) ?></span></a></li>
							<li class="nav-item"><a class="nav-link py-1 px-3 <?= $tab === 'envoyees' ? 'active' : 'bg-light' ?>" href="<?= $qs('envoyees') ?>">Invitations envoyées <span class="badge bg-secondary ms-1"><?= count($envoyees) ?></span></a></li>
						</ul>
					</div>

					<?php if ($tab === 'suggestions'): ?>
					<form class="card card-body" method="get" role="search">
						<div class="row g-2">
							<div class="col-md-5"><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher par nom ou pseudonyme…" aria-label="Rechercher"></div>
							<div class="col-6 col-md-3"><select class="form-select" name="zone" aria-label="Zone"><option value="">Toutes les zones</option><?php foreach (array_keys(x_zones()) as $z): ?><option <?= $zone === $z ? 'selected' : '' ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
							<div class="col-6 col-md-2"><select class="form-select" name="role" aria-label="Profil"><option value="">Tous profils</option><option value="membre" <?= $role === 'membre' ? 'selected' : '' ?>>Membres</option><option value="pair" <?= $role === 'pair' ? 'selected' : '' ?>>Pair animateur·rices</option><option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Équipe</option></select></div>
							<div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Filtrer</button></div>
						</div>
					</form>
					<div class="row g-3">
						<?php foreach ($users as $u): ?>
						<div class="col-sm-6 col-xl-4"><div class="card h-100"><div class="card-body text-center">
							<div class="avatar avatar-xl mb-2"><img class="avatar-img rounded-circle" src="<?= x_avatar($u['avatar']) ?>" alt=""></div>
							<h2 class="h6 mb-0"><?= e($u['name']) ?></h2>
							<p class="small text-secondary mb-3"><?= e(x_role_label($u['role'])) ?> · <?= e($u['zone']) ?></p>
							<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="invite"><input type="hidden" name="to" value="<?= (int)$u['id'] ?>"><button class="btn btn-sm btn-primary-soft w-100" type="submit"><i class="bi bi-person-plus me-1"></i>Envoyer une invitation</button></form>
						</div></div></div>
						<?php endforeach; ?>
						<?php if (!$users): ?><div class="col-12"><div class="card card-body text-center text-secondary">Aucune suggestion pour ces critères.</div></div><?php endif; ?>
					</div>

					<?php elseif ($tab === 'recues'): ?>
					<div class="card"><div class="list-group list-group-flush">
						<?php foreach ($recues as $i): ?>
						<div class="list-group-item d-flex flex-wrap gap-3 align-items-center py-3">
							<?= $person($i['from_user'], fn($u) => x_role_label($u['role']) . ' · ' . $u['zone'] . ' · il y a ' . x_ago($i['date'])) ?>
							<form method="post" class="d-flex gap-2"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
								<button class="btn btn-sm btn-primary" name="action" value="accept" type="submit">Accepter</button>
								<button class="btn btn-sm btn-danger-soft" name="action" value="decline" type="submit">Refuser</button></form>
						</div>
						<?php endforeach; ?>
						<?php if (!$recues): ?><div class="list-group-item text-center text-secondary py-4">Aucune invitation en attente.</div><?php endif; ?>
					</div></div>

					<?php else: ?>
					<div class="card"><div class="list-group list-group-flush">
						<?php foreach ($envoyees as $i): ?>
						<div class="list-group-item d-flex flex-wrap gap-3 align-items-center py-3">
							<?= $person($i['to_user'], fn($u) => x_role_label($u['role']) . ' · envoyée il y a ' . x_ago($i['date'])) ?>
							<form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button class="btn btn-sm btn-light" type="submit">Annuler</button></form>
						</div>
						<?php endforeach; ?>
						<?php if (!$envoyees): ?><div class="list-group-item text-center text-secondary py-4">Aucune invitation envoyée. Découvrez les <a href="?tab=suggestions">suggestions</a>.</div><?php endif; ?>
					</div></div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); ?>
	<?php include("partials/footer-scripts.php"); ?>
</body>
</html>
