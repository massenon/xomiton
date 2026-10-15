<?php
/**
 * Gabarit administration – début. Variables attendues : $pageTitle, $adminPage (clé du menu), [$adminActions = html boutons]
 * La page doit avoir fait : require __DIR__.'/../inc/bootstrap.php'; require_admin();
 */
require_once __DIR__ . '/../inc/x_admin_ui.php';
$au = x_user();
$notifs = xa_notifications();
$notifUnread = count(array_filter($notifs, fn($nt) => !$nt['read']));
$adminPage = $adminPage ?? '';
$alertNew = count(array_filter(store_all('alertes'), fn($a) => $a['status'] === 'nouveau'));
$pendingUsers = count(array_filter(store_all('users'), fn($a) => $a['status'] === 'en_attente'));
$menu = [
    'Pilotage' => [
        ['index', 'bi-speedometer2', 'Tableau de bord'],
        ['alertes', 'bi-bell', 'Alertes & signalements', $alertNew, 'danger'],
        ['indicateurs', 'bi-bar-chart-line', 'Indicateurs'],
        ['rapports', 'bi-file-earmark-bar-graph', 'Rapports automatisés'],
    ],
    'Gestion des données' => [
        ['registres', 'bi-journal-bookmark', 'Registres numériques'],
        ['fiches', 'bi-file-earmark-medical', 'Fiches VBG / VDH'],
        ['suivi', 'bi-person-heart', 'Suivi des victimes'],
        ['annuaire', 'bi-person-lines-fill', 'Annuaire dynamique'],
        ['validation', 'bi-patch-check', 'Règles de validation'],
    ],
    'Plateforme' => [
        ['utilisateurs', 'bi-people', 'Utilisateurs & rôles', $pendingUsers, 'primary'],
        ['inscriptions', 'bi-person-plus', 'Inscriptions (on/off)'],
        ['contenu', 'bi-journal-text', 'Contenu : thématiques & ressources'],
        ['newsletter', 'bi-envelope-paper', 'Newsletter'],
        ['ccc', 'bi-collection-play', 'Outils CCC'],
        ['reseau', 'bi-diagram-3', 'Réseau social'],
        ['moderation', 'bi-shield-check', 'Modération'],
        ['assistant', 'bi-robot', 'Assistant LLM'],
        ['passerelles', 'bi-share', 'Passerelles réseaux'],
    ],
    'Système' => [
        ['synchronisation', 'bi-cloud-arrow-up', 'Hors connexion & synchro'],
        ['mises-a-jour', 'bi-arrow-repeat', 'Mises à jour & optimisation'],
        ['emails', 'bi-envelope-at', 'Templates e-mails'],
        ['parametres', 'bi-gear', 'Paramètres'],
        ['journal', 'bi-clock-history', 'Journal d’audit'],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include __DIR__ . '/title-meta.php'; ?>
	<?php include __DIR__ . '/head-css.php'; ?>
	<script src="<?= BASE ?>assets/vendor/chartjs/chart.umd.min.js"></script>
	<?= $adminHead ?? '' ?>
</head>
<body class="xm-admin">
<div class="xm-offline-bar" id="xmOfflineBar"><i class="bi bi-wifi-off me-1"></i> Hors connexion — les modifications seront synchronisées au retour du réseau.</div>

<aside class="xm-admin-sidebar" id="xmSidebar">
	<div class="brand d-flex align-items-center justify-content-between">
		<a href="<?= BASE ?>admin/index.php"><img src="<?= BASE ?>assets/images/logo-dark.svg" alt="Xomiton, logo" title="Xomiton" style="height:30px;width:auto"></a>
		<span class="badge bg-danger">Admin</span>
	</div>
	<?php foreach ($menu as $group => $items): ?>
		<div class="group"><?= e($group) ?></div>
		<?php foreach ($items as $it): ?>
			<a class="item <?= $adminPage === $it[0] ? 'active' : '' ?>" href="<?= BASE ?>admin/<?= $it[0] ?>.php">
				<i class="bi <?= $it[1] ?>"></i><span><?= e($it[2]) ?></span>
				<?php if (!empty($it[3])): ?><span class="badge bg-<?= $it[4] ?>"><?= (int)$it[3] ?></span><?php endif; ?>
			</a>
		<?php endforeach; ?>
	<?php endforeach; ?>
	<div class="group">Raccourcis</div>
	<a class="item" href="<?= BASE ?>fil.php"><i class="bi bi-box-arrow-up-right"></i><span>Voir le site membre</span></a>
	<a class="item" href="<?= BASE ?>index.php" target="_blank"><i class="bi bi-globe2"></i><span>Page d’accueil publique</span></a>
	<a class="item" href="<?= BASE ?>logout.php"><i class="bi bi-power"></i><span>Se déconnecter</span></a>
</aside>

<div class="xm-admin-main">
	<div class="xm-admin-topbar d-flex align-items-center gap-3 no-print">
		<button class="btn btn-light d-lg-none" type="button" id="xmSidebarToggle" aria-label="Menu"><i class="bi bi-list fs-5"></i></button>
		<div class="flex-grow-1">
			<h1 class="h5 mb-0 fw-bold"><?= e($pageTitle ?? 'Administration') ?></h1>
			<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0 small"><li class="breadcrumb-item"><a href="<?= BASE ?>admin/index.php">Administration</a></li><li class="breadcrumb-item active"><?= e($pageTitle ?? '') ?></li></ol></nav>
		</div>
		<form class="xm-gsearch d-none d-md-block" action="<?= BASE ?>admin/recherche.php" method="get" role="search">
			<div class="input-group input-group-sm">
				<span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search" aria-hidden="true"></i></span>
				<input class="form-control border-start-0 ps-0" type="search" name="q" id="xmGlobalSearch" value="<?= e($_GET['gq'] ?? '') ?>" placeholder="Recherche globale…" aria-label="Recherche globale" autocomplete="off">
				<span class="input-group-text bg-transparent d-none d-xxl-flex"><kbd class="small">Ctrl K</kbd></span>
			</div>
		</form>
		<a class="btn btn-light btn-sm d-md-none" href="<?= BASE ?>admin/recherche.php" aria-label="Recherche globale" title="Recherche globale"><i class="bi bi-search"></i></a>
		<span class="badge bg-success d-none d-sm-inline" data-xm-online-state>En ligne</span>
		<span class="badge bg-warning text-dark d-none" data-xm-queue-badge><i class="bi bi-cloud-arrow-up me-1"></i><span data-xm-queue-count>0</span></span>
		<?= $adminActions ?? '' ?>
		<button class="btn btn-light btn-sm position-relative" type="button" id="xmFullscreen" aria-label="Plein écran" title="Plein écran"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i></button>
		<div class="dropdown" id="xmNotif">
			<button class="btn btn-light btn-sm position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications" title="Notifications">
				<i class="bi bi-bell" aria-hidden="true"></i>
				<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger <?= $notifUnread ? '' : 'd-none' ?>" id="xmNotifCount"><?= $notifUnread ?><span class="visually-hidden"> notifications non lues</span></span>
			</button>
			<div class="dropdown-menu dropdown-menu-end p-0 shadow-lg xm-notif-menu">
				<div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
					<strong>Notifications</strong>
					<button type="button" class="btn btn-link btn-sm p-0" id="xmNotifReadAll">Tout marquer comme lu</button>
				</div>
				<ul class="nav nav-pills nav-fill small p-2 gap-1" role="tablist" aria-label="Filtrer les notifications">
					<li class="nav-item"><button class="nav-link active py-1" type="button" data-notif-tab="unread" role="tab" aria-selected="true">Non lus</button></li>
					<li class="nav-item"><button class="nav-link py-1" type="button" data-notif-tab="read" role="tab" aria-selected="false">Lues</button></li>
					<li class="nav-item"><button class="nav-link py-1" type="button" data-notif-tab="all" role="tab" aria-selected="false">Toutes les notifications</button></li>
				</ul>
				<div class="list-group list-group-flush xm-notif-list" id="xmNotifList">
					<?php foreach ($notifs as $nt): ?>
					<a class="list-group-item list-group-item-action d-flex gap-2 align-items-start" href="<?= BASE ?>admin/<?= e($nt['link']) ?>" data-notif-id="<?= e($nt['id']) ?>" data-notif-read="<?= $nt['read'] ? '1' : '0' ?>">
						<span class="icon-md rounded-circle bg-<?= e($nt['color']) ?> bg-opacity-10 text-<?= e($nt['color']) ?> flex-shrink-0"><i class="bi <?= e($nt['icon']) ?>" aria-hidden="true"></i></span>
						<span class="small flex-grow-1"><span class="d-block"><?= e($nt['text']) ?></span><span class="text-secondary">il y a <?= e(x_ago($nt['ts'])) ?></span></span>
						<span class="xm-notif-dot" aria-hidden="true"></span>
					</a>
					<?php endforeach; unset($nt); ?>
					<div class="list-group-item text-center text-secondary small d-none" id="xmNotifEmpty">Aucune notification dans cette liste.</div>
				</div>
				<div class="p-2 border-top text-center"><a class="small" href="<?= BASE ?>admin/journal.php">Voir le journal d’audit</a></div>
			</div>
		</div>
		<div class="dropdown">
			<a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
				<img src="<?= x_avatar($au['avatar'] ?? 0) ?>" class="rounded-circle" style="width:36px;height:36px;object-fit:cover" alt="Avatar de <?= e($au['name']) ?>" title="<?= e($au['name']) ?>">
				<span class="ms-2 small d-none d-md-inline"><?= e($au['name']) ?></span>
			</a>
			<ul class="dropdown-menu dropdown-menu-end">
				<li><a class="dropdown-item" href="<?= BASE ?>my-profile.php">Mon profil</a></li>
				<li><a class="dropdown-item" href="<?= BASE ?>admin/parametres.php">Paramètres</a></li>
				<li><hr class="dropdown-divider"></li>
				<li><a class="dropdown-item text-danger" href="<?= BASE ?>logout.php">Se déconnecter</a></li>
			</ul>
		</div>
	</div>
	<div class="xm-admin-content">
		<?= flash_html() ?>
