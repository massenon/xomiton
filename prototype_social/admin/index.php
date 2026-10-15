<?php
require __DIR__ . '/../inc/bootstrap.php';
require_admin();
require_once __DIR__ . '/../inc/x_admin1.php';
$pageTitle = 'Tableau de bord';
$adminPage = 'index';

$cases = array_values(array_filter(store_all('cases'), fn($c) => $c['status'] !== 'archive'));
$alertes = store_all('alertes');
usort($alertes, fn($a, $b) => $b['created'] <=> $a['created']);
$users = store_all('users'); $ann = xa_annuaire_map();
$st = array_count_values(array_column($cases, 'status'));
$nNew = count(array_filter($alertes, fn($a) => $a['status'] === 'nouveau'));
$sosActifs = array_values(array_filter($alertes, fn($a) => $a['type'] === 'sos' && $a['status'] !== 'traite'));
$syncRate = 96; /* simulé : part des enregistrements hors-ligne synchronisés */

$months = xa_last_months(12);
$perMonth = array_fill_keys(array_keys($months), 0);
foreach ($cases as $c) { $k = substr($c['date'], 0, 7); if (isset($perMonth[$k])) $perMonth[$k]++; }
$viol = xa_count_by($cases, 'type'); $violL = xa_viol_map();
$zones = xa_count_by($cases, 'zone'); $pops = xa_count_by($cases, 'population');
$ages = x_age_ranges(); $sexes = x_sexes(); $sa = [];
foreach ($sexes as $s) { $sa[$s] = []; foreach ($ages as $a) $sa[$s][$a] = 0; }
foreach ($cases as $c) if (isset($sa[$c['sexe']][$c['age']])) $sa[$c['sexe']][$c['age']]++;
$latest = array_slice($alertes, 0, 8);

$adminActions = '<a class="btn btn-outline-primary btn-sm d-none d-md-inline-flex" href="rapports.php"><i class="bi bi-file-earmark-bar-graph me-1"></i>Rapports</a>';
include __DIR__ . '/../partials/admin-top.php';
$kpis = [
    ['Nouvelles alertes', $nNew, 'danger', 'bi-bell', 'alertes.php?statut=nouveau'],
    ['SOS actifs', count($sosActifs), 'danger', 'bi-geo-alt-fill', 'alertes.php?type=sos'],
    ['Cas ouverts', $st['ouvert'] ?? 0, '', 'bi-folder2-open', 'registres.php?statut=ouvert'],
    ['Cas en cours', $st['en_cours'] ?? 0, 'warning', 'bi-hourglass-split', 'registres.php?statut=en_cours'],
    ['Cas clos', $st['clos'] ?? 0, 'success', 'bi-check2-circle', 'registres.php?statut=clos'],
    ['Utilisateurs', count($users), 'violet', 'bi-people', 'utilisateurs.php'],
    ['Taux de synchro', $syncRate . ' %', 'success', 'bi-cloud-check', 'synchronisation.php'],
];
?>
<div class="row g-3 mb-4">
<?php foreach ($kpis as $k): ?>
	<div class="col-6 col-md-4 col-xl-3"><a href="<?= $k[4] ?>" class="text-decoration-none text-body"><div class="card xm-stat <?= $k[2] ?> xm-card-hover h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="small text-secondary"><?= e($k[0]) ?></div><div class="kpi-value"><?= e($k[1]) ?></div></div><i class="bi <?= $k[3] ?> fs-2 text-secondary opacity-50"></i></div></div></a></div>
<?php endforeach; ?>
</div>

<div class="row g-4 mb-4">
	<div class="col-xl-8"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Cas enregistrés par mois (12 derniers mois)</h2></div><div class="card-body" style="height:290px"><canvas id="chMonth"></canvas></div></div></div>
	<div class="col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Par type de violence</h2></div><div class="card-body" style="height:290px"><canvas id="chViol"></canvas></div></div></div>
	<div class="col-lg-6 col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Par département</h2></div><div class="card-body" style="height:280px"><canvas id="chZone"></canvas></div></div></div>
	<div class="col-lg-6 col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Par population</h2></div><div class="card-body" style="height:280px"><canvas id="chPop"></canvas></div></div></div>
	<div class="col-xl-4"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Sexe et tranche d’âge</h2></div><div class="card-body" style="height:280px"><canvas id="chAge"></canvas></div></div></div>
</div>

<div class="row g-4 mb-4">
	<div class="col-xl-8">
		<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Dernières alertes</h2><a href="alertes.php" class="small">Tout voir</a></div>
		<div class="xm-table-wrap"><table class="table table-hover align-middle mb-0">
			<thead><tr><th>N°</th><th>Type</th><th>Zone</th><th>Priorité</th><th>Statut</th><th>Reçue</th><th></th></tr></thead><tbody>
			<?php foreach ($latest as $a): ?>
				<tr>
					<td>#<?= (int)$a['id'] ?></td>
					<td><span class="badge <?= $a['type'] === 'sos' ? 'bg-danger' : 'bg-primary bg-opacity-10 text-primary' ?>"><?= e(xa_types_alert()[$a['type']][0]) ?></span></td>
					<td><?= e($a['zone']) ?> <span class="small text-secondary"><?= e($a['commune'] ?? '') ?></span></td>
					<td><?= xa_badge(xa_prio(), $a['priority']) ?></td>
					<td><?= xa_badge(xa_status_alert(), $a['status']) ?></td>
					<td class="small text-nowrap">il y a <?= e(x_ago($a['created'])) ?></td>
					<td class="text-end text-nowrap">
						<?php if ($a['status'] === 'nouveau'): ?>
						<form method="post" action="alertes.php" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="take"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="back" value="index.php"><button class="btn btn-sm btn-primary">Prendre en charge</button></form>
						<?php else: ?><a class="btn btn-sm btn-light" href="alertes.php?q=%23<?= (int)$a['id'] ?>">Voir</a><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody></table></div></div>
	</div>
	<div class="col-xl-4">
		<div class="card h-100"><div class="card-header"><h2 class="h6 mb-0"><i class="bi bi-geo-alt text-danger me-1"></i>SOS actifs — localisation</h2></div><div class="card-body">
			<div class="xm-map mb-3" style="height:200px" role="img" aria-label="Carte schématique des SOS actifs">
				<?php foreach ($sosActifs as $a): if (!isset($a['lat'])) continue; $left = max(4, min(96, ($a['lng'] - 0.7) / 3.2 * 100)); $top = max(4, min(96, (12.5 - $a['lat']) / 6.4 * 100)); ?>
					<span class="pin" style="left:<?= round($left, 1) ?>%;top:<?= round($top, 1) ?>%" title="SOS #<?= (int)$a['id'] ?> — <?= e($a['commune'] ?? $a['zone']) ?>"></span>
				<?php endforeach; ?>
				<span class="position-absolute bottom-0 start-0 m-2 small text-secondary">Bénin (schéma)</span>
			</div>
			<ul class="list-unstyled small mb-0">
				<?php foreach ($sosActifs as $a): ?><li class="d-flex justify-content-between mb-1"><span><i class="bi bi-geo-alt-fill text-danger"></i> #<?= (int)$a['id'] ?> <?= e($a['commune'] ?? $a['zone']) ?></span><a target="_blank" rel="noopener" href="<?= e(xa_maps_link($a['lat'], $a['lng'])) ?>">Maps</a></li><?php endforeach; ?>
				<?php if (!$sosActifs): ?><li class="text-secondary">Aucun SOS actif.</li><?php endif; ?>
			</ul>
		</div></div>
	</div>
</div>

<div class="row g-4">
	<div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">Raccourcis</h2></div><div class="card-body"><div class="row g-2">
		<?php foreach ([['registres.php', 'bi-journal-plus', 'Nouveau dossier'], ['alertes.php', 'bi-bell', 'Traiter les alertes'], ['rapports.php', 'bi-file-earmark-bar-graph', 'Générer un rapport'], ['suivi.php', 'bi-person-heart', 'Suivi des victimes'], ['newsletter.php', 'bi-envelope-paper', 'Newsletter'], ['annuaire.php', 'bi-person-lines-fill', 'Annuaire'], ['fiches.php', 'bi-file-earmark-medical', 'Fiche de référence'], ['inscriptions.php', 'bi-person-plus', 'Inscriptions']] as $s): ?>
			<div class="col-6"><a class="btn btn-light w-100 text-start" href="<?= $s[0] ?>"><i class="bi <?= $s[1] ?> me-2 text-primary"></i><?= e($s[2]) ?></a></div>
		<?php endforeach; ?></div></div></div></div>
	<div class="col-lg-6"><div class="card h-100"><div class="card-header"><h2 class="h6 mb-0">État de la plateforme</h2></div><div class="card-body">
		<ul class="list-unstyled mb-0">
			<li class="d-flex justify-content-between py-1 border-bottom"><span>Version</span><b><?= e(setting('platform_version', '1.0')) ?></b></li>
			<li class="d-flex justify-content-between py-1 border-bottom"><span>Dernière mise à jour</span><b><?= e(date('d/m/Y', strtotime(setting('last_update', date('Y-m-d'))))) ?></b></li>
			<li class="d-flex justify-content-between py-1 border-bottom"><span>Inscriptions</span><?= setting('registrations_open') ? '<span class="badge bg-success">Ouvertes</span>' : '<span class="badge bg-secondary">Fermées</span>' ?></li>
			<li class="d-flex justify-content-between py-1 border-bottom"><span>Mode maintenance</span><?= setting('maintenance') ? '<span class="badge bg-warning text-dark">Actif</span>' : '<span class="badge bg-success">Inactif</span>' ?></li>
			<li class="d-flex justify-content-between py-1 border-bottom"><span>Synchronisation hors ligne</span><?= setting('offline_sync') ? '<span class="badge bg-success">Activée</span>' : '<span class="badge bg-secondary">Désactivée</span>' ?></li>
			<li class="d-flex justify-content-between py-1"><span>Comptes en attente de validation</span><b><?= count(array_filter($users, fn($u) => $u['status'] === 'en_attente')) ?></b></li>
		</ul></div></div></div>
</div>

<?php
$labels = ['m' => array_values($months), 'viol' => array_map(fn($k) => $violL[$k] ?? $k, array_keys($viol))];
$adminScripts = '<script>
(function(){
  const C = XM_COLORS, opt = (extra) => Object.assign({ responsive: true, maintainAspectRatio: false }, extra || {});
  const yInt = { y: { beginAtZero: true, ticks: { precision: 0 } } };
  new Chart(document.getElementById("chMonth"), { type: "line", data: { labels: ' . json_encode($labels['m'], JSON_UNESCAPED_UNICODE) . ', datasets: [{ label: "Cas", data: ' . json_encode(array_values($perMonth)) . ', borderColor: C[0], backgroundColor: "rgba(4,51,195,.12)", fill: true, tension: .3 }] }, options: opt({ plugins: { legend: { display: false } }, scales: yInt }) });
  new Chart(document.getElementById("chViol"), { type: "doughnut", data: { labels: ' . json_encode($labels['viol'], JSON_UNESCAPED_UNICODE) . ', datasets: [{ data: ' . json_encode(array_values($viol)) . ', backgroundColor: C }] }, options: opt() });
  new Chart(document.getElementById("chZone"), { type: "bar", data: { labels: ' . json_encode(array_keys($zones), JSON_UNESCAPED_UNICODE) . ', datasets: [{ label: "Cas", data: ' . json_encode(array_values($zones)) . ', backgroundColor: C[0] }] }, options: opt({ indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }) });
  new Chart(document.getElementById("chPop"), { type: "bar", data: { labels: ' . json_encode(array_keys($pops), JSON_UNESCAPED_UNICODE) . ', datasets: [{ label: "Cas", data: ' . json_encode(array_values($pops)) . ', backgroundColor: C[2] }] }, options: opt({ indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }) });
  const sa = ' . json_encode($sa, JSON_UNESCAPED_UNICODE) . ', ages = ' . json_encode($ages) . ';
  new Chart(document.getElementById("chAge"), { type: "bar", data: { labels: ages, datasets: Object.keys(sa).map((s, i) => ({ label: s, data: ages.map(a => sa[s][a]), backgroundColor: C[i === 0 ? 2 : i === 1 ? 0 : 4] })) }, options: opt({ scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }) });
})();
</script>';
include __DIR__ . '/../partials/admin-bottom.php';
