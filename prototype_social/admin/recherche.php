<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin_sys.php';
require_once __DIR__ . '/../inc/x_reseau.php';
$pageTitle = 'Recherche globale';
$adminPage = '';
$q = trim($_GET['q'] ?? '');
$has = fn($hay) => $q !== '' && mb_stripos((string)$hay, $q) !== false;
$G = []; // [titre, icône, couleur, lien de la page, [[libellé, détail, lien]…]]
if (mb_strlen($q) >= 2) {
	$rows = [];
	foreach (store_all('users') as $u) if ($has($u['name'] . ' ' . $u['email'] . ' ' . $u['pseudo'] . ' ' . ($u['zone'] ?? '')))
		$rows[] = [$u['name'], $u['email'] . ' · ' . x_role_label($u['role']) . ' · ' . $u['status'], 'utilisateurs.php?q=' . urlencode($u['email'])];
	$G[] = ['Utilisateurs', 'bi-people', 'primary', 'utilisateurs.php', $rows];

	$rows = [];
	foreach (store_all('cases') as $c) if ($has($c['code'] . ' ' . $c['type'] . ' ' . ($c['commune'] ?? '') . ' ' . ($c['zone'] ?? '') . ' ' . ($c['pair'] ?? '')))
		$rows[] = [$c['code'], ($c['kind'] ?? '') . ' · ' . ($c['type'] ?? '') . ' · ' . ($c['commune'] ?? '') . ' · ' . ($c['status'] ?? ''), 'registres.php?q=' . urlencode($c['code'])];
	$G[] = ['Dossiers (registres)', 'bi-journal-bookmark', 'success', 'registres.php?q=' . urlencode($q), $rows];

	$rows = [];
	foreach (store_all('alertes') as $a) if ($has(($a['category'] ?? '') . ' ' . ($a['description'] ?? '') . ' ' . ($a['pseudo'] ?? '') . ' ' . ($a['commune'] ?? '')))
		$rows[] = ['Alerte n° ' . $a['id'] . ' · ' . ($a['category'] ?? ''), mb_strimwidth((string)($a['description'] ?? ''), 0, 110, '…') . ' · ' . ($a['status'] ?? ''), 'alertes.php?q=' . urlencode($q)];
	$G[] = ['Alertes & signalements', 'bi-bell', 'danger', 'alertes.php?q=' . urlencode($q), $rows];

	$rows = [];
	foreach (store_all('annuaire') as $a) if ($has($a['name'] . ' ' . $a['role'] . ' ' . ($a['org'] ?? '') . ' ' . ($a['commune'] ?? '')))
		$rows[] = [$a['name'], $a['role'] . ' · ' . ($a['org'] ?? '') . ' · ' . ($a['commune'] ?? ''), 'annuaire.php?q=' . urlencode($a['name'])];
	$G[] = ['Annuaire', 'bi-person-lines-fill', 'info', 'annuaire.php', $rows];

	$rows = [];
	foreach (store_all('stock') as $s) if ($has($s['item'] . ' ' . ($s['category'] ?? '') . ' ' . ($s['location'] ?? '')))
		$rows[] = [$s['item'], ($s['category'] ?? '') . ' · ' . $s['qty'] . ' en stock (seuil ' . $s['min'] . ')', 'stock.php?q=' . urlencode($s['item'])];
	$G[] = ['Stock', 'bi-box-seam', 'warning', 'stock.php', $rows];

	$rows = [];
	foreach (x_thematiques() as $t) if ($has($t['title'] . ' ' . $t['summary']))
		$rows[] = [$t['title'], mb_strimwidth($t['summary'], 0, 110, '…'), 'contenu.php?tab=thematiques'];
	$G[] = ['Thématiques', 'bi-journal-text', 'violet', 'contenu.php?tab=thematiques', $rows];

	$rows = [];
	foreach (store_all('events') as $ev) if ($has($ev['title'] . ' ' . ($ev['place'] ?? '') . ' ' . ($ev['description'] ?? '')))
		$rows[] = [$ev['title'], x_date_fr($ev['date']) . ' · ' . ($ev['place'] ?? ''), '../event-details.php?id=' . (int)$ev['id']];
	foreach (store_all('groups') as $g) if ($has($g['name'] . ' ' . ($g['desc'] ?? '')))
		$rows[] = ['Groupe : ' . $g['name'], ($g['privacy'] ?? '') . ' · ' . ($g['zone'] ?? ''), '../group-details.php?id=' . (int)$g['id']];
	$G[] = ['Réseau social (événements, groupes)', 'bi-diagram-3', 'primary', 'reseau.php', $rows];

	$rows = [];
	foreach (store_all('journal') as $j) if ($has($j['user'] . ' ' . $j['action'] . ' ' . ($j['detail'] ?? '')))
		$rows[] = [$j['action'] . ' · ' . $j['user'], ($j['detail'] ?? '') . ' · ' . $j['at'], 'journal.php?q=' . urlencode($q)];
	$G[] = ['Journal d’audit', 'bi-clock-history', 'secondary', 'journal.php?q=' . urlencode($q), $rows];
}
$total = array_sum(array_map(fn($g) => count($g[4]), $G));
include __DIR__ . '/../partials/admin-top.php';
?>
<form class="card card-body mb-4" method="get" role="search">
	<label class="form-label fw-semibold" for="rq">Rechercher dans toute l’administration</label>
	<div class="input-group">
		<input class="form-control" type="search" id="rq" name="q" value="<?= e($q) ?>" placeholder="Nom, e-mail, code dossier, thématique, stock, annuaire…" minlength="2" required autofocus>
		<button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Rechercher</button>
	</div>
	<div class="form-text">Utilisateurs, dossiers, alertes, annuaire, stock, thématiques, réseau social et journal d’audit. Raccourci : Ctrl K.</div>
</form>

<?php if ($q === ''): ?>
	<div class="card card-body text-center text-secondary py-5"><i class="bi bi-search fs-1 mb-2"></i>Saisissez au moins 2 caractères pour lancer la recherche.</div>
<?php elseif (mb_strlen($q) < 2): ?>
	<div class="alert alert-warning">Saisissez au moins 2 caractères.</div>
<?php else: ?>
	<p class="text-secondary" role="status"><strong><?= $total ?></strong> résultat<?= $total > 1 ? 's' : '' ?> pour « <?= e($q) ?> ».</p>
	<div class="row g-3">
		<?php foreach ($G as $g): if (!$g[4]) continue; ?>
		<div class="col-12 col-xl-6">
			<div class="card h-100">
				<div class="card-header d-flex align-items-center gap-2">
					<span class="icon-md rounded-circle bg-<?= $g[2] ?> bg-opacity-10 text-<?= $g[2] ?>"><i class="bi <?= $g[1] ?>" aria-hidden="true"></i></span>
					<h2 class="h6 mb-0 flex-grow-1"><?= e($g[0]) ?></h2>
					<span class="badge bg-secondary-subtle text-secondary-emphasis"><?= count($g[4]) ?></span>
				</div>
				<div class="list-group list-group-flush">
					<?php foreach (array_slice($g[4], 0, 6) as $r): ?>
					<a class="list-group-item list-group-item-action" href="<?= e($r[2]) ?>"><div class="fw-semibold small"><?= e($r[0]) ?></div><div class="small text-secondary text-truncate"><?= e($r[1]) ?></div></a>
					<?php endforeach; ?>
				</div>
				<?php if (count($g[4]) > 6): ?><div class="card-footer text-center"><a class="small" href="<?= e($g[3]) ?>">Voir les <?= count($g[4]) ?> résultats dans « <?= e($g[0]) ?> »</a></div><?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
		<?php if (!$total): ?><div class="col-12"><div class="card card-body text-center text-secondary py-5"><i class="bi bi-emoji-neutral fs-1 mb-2"></i>Aucun résultat. Essayez un autre mot-clé ou une orthographe différente.</div></div><?php endif; ?>
	</div>
<?php endif; ?>
<?php include __DIR__ . '/../partials/admin-bottom.php'; ?>
