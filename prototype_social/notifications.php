<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_once __DIR__ . '/inc/x_reseau.php';
$me = x_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_ok()) x_redirect('notifications.php', 'danger', 'Session expirée, veuillez réessayer.');
	if (($_POST['action'] ?? '') === 'read_all') { $_SESSION['x_notif_read_all'] = time(); x_redirect('notifications.php', 'success', 'Toutes les notifications sont marquées comme lues.'); }
	if (($_POST['action'] ?? '') === 'read_one') { x_set_toggle('notif_read', (int)($_POST['n'] ?? 0)); x_redirect('notifications.php?f=' . urlencode($_POST['f'] ?? '')); }
}
$readAll = (int)($_SESSION['x_notif_read_all'] ?? 0);
$items = []; $n = 0;
foreach (store_all('invitations') as $i) {
	if ((int)$i['to_user'] === (int)$me['id'] && $i['status'] === 'en_attente') {
		$u = x_user_by_id($i['from_user']);
		if ($u) $items[] = ['type' => 'invit', 'ts' => (int)$i['date'], 'icon' => 'bi-person-plus', 'cls' => 'primary', 'text' => '<strong>' . e($u['name']) . '</strong> vous a envoyé une invitation.', 'href' => 'who-to-follow.php?tab=recues', 'avatar' => x_avatar($u['avatar']), 'id' => 1000 + (int)$i['id']];
	}
}
$evs = store_all('events'); usort($evs, fn($a, $b) => strcmp($a['date'], $b['date']));
foreach (array_slice($evs, 0, 3) as $ev) $items[] = ['type' => 'events', 'ts' => time() - 3600 * (2 + $ev['id'] * 5), 'icon' => 'bi-calendar-event', 'cls' => 'success', 'text' => 'Rappel : <strong>' . e($ev['title']) . '</strong> le ' . e(x_date_fr($ev['date'], false)) . ' à ' . e($ev['time']) . '.', 'href' => 'event-details.php?id=' . (int)$ev['id'], 'id' => 2000 + (int)$ev['id']];
x_forum_topics();
foreach (array_slice(array_reverse(store_all('forum_replies')), 0, 3) as $r) {
	$t = store_find('forum_topics', $r['topic']); $u = $r['user'] ? x_user_by_id($r['user']) : null;
	if ($t) $items[] = ['type' => 'messages', 'ts' => (int)$r['ts'], 'icon' => 'bi-chat-dots', 'cls' => 'info', 'text' => '<strong>' . e($u['name'] ?? 'Un·e membre') . '</strong> a répondu au sujet « ' . e($t['title']) . ' ».', 'href' => 'forum-topic.php?id=' . (int)$t['id'], 'avatar' => $u ? x_avatar($u['avatar']) : img('personnage-1.png'), 'id' => 3000 + (int)$r['id']];
}
foreach (array_slice(store_all('posts'), 0, 2) as $p) { $u = x_user_by_id($p['user']); $items[] = ['type' => 'messages', 'ts' => (int)$p['ts'], 'icon' => 'bi-hand-thumbs-up', 'cls' => 'warning', 'text' => '<strong>' . e($u['name'] ?? 'Un·e membre') . '</strong> a publié : ' . e(mb_strimwidth($p['text'], 0, 70, '…')), 'href' => 'post-details.php?id=' . (int)$p['id'], 'avatar' => x_avatar($u['avatar'] ?? 0), 'id' => 4000 + (int)$p['id']]; }
$items[] = ['type' => 'system', 'ts' => time() - 86400 * 2, 'icon' => 'bi-arrow-repeat', 'cls' => 'secondary', 'text' => 'Mise à jour de la plateforme : synchronisation hors connexion améliorée.', 'href' => 'help.php', 'id' => 5001];
$items[] = ['type' => 'system', 'ts' => time() - 86400 * 4, 'icon' => 'bi-shield-check', 'cls' => 'secondary', 'text' => 'Rappel sécurité : activez le code PIN dans vos paramètres.', 'href' => 'settings.php#securite', 'id' => 5002];
usort($items, fn($a, $b) => $b['ts'] <=> $a['ts']);
$f = $_GET['f'] ?? '';
$filters = ['' => 'Toutes', 'invit' => 'Invitations', 'events' => 'Événements', 'messages' => 'Échanges', 'system' => 'Système'];
$isRead = fn($it) => $it['ts'] <= $readAll || x_set_has('notif_read', $it['id']);
$unread = count(array_filter($items, fn($it) => !$isRead($it)));
if ($f !== '' && isset($filters[$f])) $items = array_values(array_filter($items, fn($it) => $it['type'] === $f));
$pageTitle = 'Notifications';
$nav = '';
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
							<div><h1 class="h4 mb-1">Notifications <?php if ($unread): ?><span class="badge bg-danger"><?= $unread ?></span><?php endif; ?></h1><p class="small text-secondary mb-0">Votre activité récente sur Xomiton.</p></div>
							<form method="post" class="mt-3 mt-sm-0"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-primary-soft btn-sm" type="submit"><i class="bi bi-check2-all me-1"></i>Tout marquer comme lu</button></form>
						</div>
						<ul class="nav nav-pills gap-2 mt-3"><?php foreach ($filters as $k => $l): ?><li class="nav-item"><a class="nav-link py-1 px-3 <?= $f === $k ? 'active' : 'bg-light' ?>" href="?f=<?= $k ?>"><?= $l ?></a></li><?php endforeach; ?></ul>
					</div>
					<div class="card"><div class="list-group list-group-flush">
						<?php foreach ($items as $it): $r = $isRead($it); ?>
						<div class="list-group-item d-flex gap-3 align-items-start py-3 <?= $r ? '' : 'badge-unread bg-primary bg-opacity-10' ?>">
							<?php if (!empty($it['avatar'])): ?><div class="avatar flex-shrink-0"><img class="avatar-img rounded-circle" src="<?= $it['avatar'] ?>" alt=""></div>
							<?php else: ?><span class="icon-lg rounded-circle bg-<?= $it['cls'] ?> bg-opacity-10 text-<?= $it['cls'] ?> flex-shrink-0 d-flex align-items-center justify-content-center" style="width:48px;height:48px"><i class="bi <?= $it['icon'] ?> fs-5"></i></span><?php endif; ?>
							<div class="flex-grow-1"><a class="text-body" href="<?= e($it['href']) ?>"><?= $it['text'] ?></a><div class="small text-secondary">il y a <?= e(x_ago($it['ts'])) ?></div></div>
							<?php if (!$r): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_one"><input type="hidden" name="n" value="<?= (int)$it['id'] ?>"><input type="hidden" name="f" value="<?= e($f) ?>"><button class="btn btn-sm btn-link text-secondary p-0" type="submit" title="Marquer comme lu" aria-label="Marquer comme lu"><i class="bi bi-check2"></i></button></form><?php endif; ?>
						</div>
						<?php endforeach; ?>
						<?php if (!$items): ?><div class="list-group-item text-center text-secondary py-4">Aucune notification.</div><?php endif; ?>
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
