<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
x_require_pair();
$pageTitle = 'Espace pair animateur·rice';
$nav = 'pair';
$me = x_user();
$cases = store_all('cases');
$mine = array_values(array_filter($cases, fn($c) => ($c['pair'] ?? '') === $me['name']));
$scopeAll = !$mine;
$base = $mine ?: $cases;
$month = date('Y-m');
$kpi = [
    ['Dossiers collectés', count($base), 'bi-folder2-open', ''],
    ['Ce mois-ci', count(array_filter($base, fn($c) => strpos($c['date'], $month) === 0)), 'bi-calendar-check', 'success'],
    ['En cours', count(array_filter($base, fn($c) => in_array($c['status'], ['ouvert', 'en_cours'], true))), 'bi-hourglass-split', 'warning'],
    ['Clos', count(array_filter($base, fn($c) => $c['status'] === 'clos')), 'bi-check2-circle', 'success'],
];
/* Alertes routées vers moi (annuaire : même e-mail ou même nom), sinon dernières alertes */
$myAnn = null;
foreach (store_all('annuaire') as $r) if (strcasecmp($r['email'] ?? '', $me['email']) === 0 || strcasecmp($r['name'], $me['name']) === 0) { $myAnn = $r; break; }
$alerts = store_all('alertes');
if ($myAnn) $alerts = array_filter($alerts, fn($a) => (int)($a['routed_to'] ?? 0) === (int)$myAnn['id']);
usort($alerts, fn($a, $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));
$alerts = array_slice($alerts, 0, 5);
$tasks = [];
$open = array_filter($base, fn($c) => $c['status'] === 'ouvert');
if ($open) $tasks[] = [count($open) . ' dossier(s) ouvert(s) à contacter', 'collecte.php', 'bi-person-check'];
$newAl = array_filter(store_all('alertes'), fn($a) => ($a['status'] ?? '') === 'nouveau');
if ($newAl) $tasks[] = [count($newAl) . ' alerte(s) nouvelle(s) à traiter', '#alertes', 'bi-bell'];
$tasks[] = ['Préparer la prochaine séance : boîtes à images', 'boites-a-images.php', 'bi-images'];
$sh = [['Nouvelle collecte', 'collecte.php', 'bi-pencil-square', 'primary'], ['Signalement', 'signalement.php', 'bi-megaphone', 'warning'], ['Annuaire', 'annuaire.php', 'bi-person-lines-fill', 'info'],
    ['Outils CCC', 'ccc.php', 'bi-collection-play', 'success'], ['Ressources', 'ressources.php', 'bi-book', 'secondary']];
$prCls = ['critique' => 'danger', 'haute' => 'warning', 'normale' => 'secondary'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
	<?php include("partials/title-meta.php"); ?>
	<?php include("partials/head-css.php"); ?>
	<style>.vstack > * { flex-grow:0 !important; }</style>
</head>
<body>
	<?php include("partials/header.php"); ?>
	<main id="contenu" tabindex="-1">
		<div class="container">
			<div class="row g-4">
				<?php include("partials/sidenav.php"); ?>
				<div class="col-lg-9 vstack gap-4">
					<?= flash_html() ?>
					<div class="card overflow-hidden">
						<div class="row g-0">
							<div class="col-md-8 card-body p-4">
								<span class="badge bg-primary-soft text-primary mb-2">Pair animateur·rice</span>
								<h1 class="h3">Bonjour <?= e(explode(' ', $me['name'])[0]) ?></h1>
								<p class="text-secondary mb-2">Votre tableau de bord de terrain : collecte, orientation, alertes et outils d’animation.</p>
								<div class="small"><span data-xm-online-state class="badge bg-success">En ligne</span> <span class="ms-2"><i class="bi bi-cloud-arrow-up me-1"></i><span data-xm-queue-count>0</span> élément(s) en attente de synchronisation</span></div>
							</div>
							<div class="col-md-4 d-none d-md-block"><div class="h-100" style="min-height:140px;background:url(<?= img('hero-05.jpg') ?>) center/cover"></div></div>
						</div>
					</div>

					<?php if ($scopeAll): ?><div class="alert alert-info mb-0 small"><i class="bi bi-info-circle me-1"></i>Aucun dossier à votre nom : les chiffres ci-dessous concernent l’ensemble des dossiers.</div><?php endif; ?>
					<div class="row g-3">
						<?php foreach ($kpi as $k): ?>
						<div class="col-6 col-md-3"><div class="card card-body xm-stat <?= $k[3] ?>"><div class="d-flex justify-content-between"><small class="text-secondary"><?= e($k[0]) ?></small><i class="bi <?= $k[2] ?> text-secondary"></i></div><div class="h2 mb-0"><?= (int)$k[1] ?></div></div></div>
						<?php endforeach; ?>
					</div>

					<div class="row g-3">
						<?php foreach ($sh as $s): ?>
						<div class="col-4 col-md-2"><a class="card card-body text-center p-2 py-3 h-100 xm-card-hover text-decoration-none" href="<?= e($s[1]) ?>"><i class="bi <?= $s[2] ?> fs-3 text-<?= $s[3] ?>"></i><span class="small fw-semibold mt-1"><?= e($s[0]) ?></span></a></div>
						<?php endforeach; ?>
					</div>

					<div class="row g-4">
						<div class="col-lg-5">
							<div class="card h-100">
								<div class="card-header"><h2 class="h5 mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Mes tâches du jour</h2></div>
								<div class="list-group list-group-flush">
									<?php foreach ($tasks as $t): ?><a class="list-group-item list-group-item-action d-flex align-items-center gap-3" href="<?= e($t[1]) ?>"><i class="bi <?= $t[2] ?> text-primary fs-5"></i><span class="small"><?= e($t[0]) ?></span><i class="bi bi-chevron-right ms-auto text-secondary"></i></a><?php endforeach; ?>
								</div>
							</div>
						</div>
						<div class="col-lg-7" id="alertes">
							<div class="card h-100">
								<div class="card-header d-flex justify-content-between"><h2 class="h5 mb-0"><i class="bi bi-bell me-2 text-danger"></i>Alertes <?= $myAnn ? 'qui me sont routées' : 'récentes' ?></h2><span class="badge bg-danger"><?= count($alerts) ?></span></div>
								<div class="list-group list-group-flush">
									<?php foreach ($alerts as $a): ?>
									<div class="list-group-item">
										<div class="d-flex justify-content-between"><span class="fw-semibold small text-capitalize"><?= e($a['type']) ?> · <?= e($a['commune'] ?: $a['zone'] ?: 'zone inconnue') ?></span><span class="badge bg-<?= $prCls[$a['priority'] ?? 'normale'] ?? 'secondary' ?>"><?= e($a['priority'] ?? 'normale') ?></span></div>
										<div class="small text-secondary text-truncate"><?= e($a['description'] ?? '') ?></div>
										<div class="small text-secondary">Il y a <?= e(x_ago($a['created'] ?? time())) ?></div>
									</div>
									<?php endforeach; if (!$alerts): ?><div class="list-group-item text-secondary small py-4 text-center">Aucune alerte pour le moment.</div><?php endif; ?>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
</body>
</html>
