<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Journal d’audit'; $adminPage = 'journal';

$all = store_all('journal'); usort($all, fn($a, $b) => strcmp($b['at'], $a['at']));
$fu = trim($_GET['u'] ?? ''); $fa = $_GET['a'] ?? ''; $fd1 = $_GET['d1'] ?? ''; $fd2 = $_GET['d2'] ?? ''; $fq = trim($_GET['q'] ?? '');
$rows = array_values(array_filter($all, fn($j) => ($fu === '' || stripos($j['user'], $fu) !== false) && ($fa === '' || $j['action'] === $fa)
    && ($fd1 === '' || substr($j['at'], 0, 10) >= $fd1) && ($fd2 === '' || substr($j['at'], 0, 10) <= $fd2) && ($fq === '' || stripos($j['detail'] ?? '', $fq) !== false)));

if (($_GET['export'] ?? '') === 'csv') xa_csv('xomiton-journal-' . date('Ymd') . '.csv', ['Date', 'Utilisateur', 'Action', 'Détail'], array_map(fn($j) => [$j['at'], $j['user'], $j['action'], $j['detail'] ?? ''], $rows));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    if (($_POST['do'] ?? '') === 'purge') {
        if (!xa_is_super()) xa_back('danger', 'Seul un·e super-admin peut purger le journal.');
        $days = (int)xa_post('days', 90);
        if ($days < 7 || $days > 3650) xa_back('danger', 'Conservation : entre 7 et 3650 jours.');
        $keep = array_filter(store_all('journal'), fn($j) => strtotime($j['at']) >= strtotime('-' . $days . ' days')); $n = count(store_all('journal')) - count($keep);
        store_save('journal', $keep); x_log('Journal', 'Purge : ' . $n . ' entrée(s) de plus de ' . $days . ' jours'); xa_back('success', $n . ' entrée(s) purgée(s).');
    }
    xa_back('danger', 'Action inconnue.');
}

$per = 20; [$pages, $page] = xa_pages(count($rows), $per, (int)($_GET['p'] ?? 1));
$slice = array_slice($rows, ($page - 1) * $per, $per);
$actions = array_values(array_unique(array_column($all, 'action'))); sort($actions);
$params = array_filter(['u' => $fu, 'a' => $fa, 'd1' => $fd1, 'd2' => $fd2, 'q' => $fq], fn($v) => $v !== '');
$today = count(array_filter($all, fn($j) => substr($j['at'], 0, 10) === date('Y-m-d')));
$cls = ['Connexion' => 'success', 'Alerte' => 'danger', 'Suppression' => 'danger', 'Modération' => 'warning text-dark', 'Maintenance' => 'warning text-dark'];
$adminActions = '<a class="btn btn-outline-secondary btn-sm" href="?' . e(http_build_query($params + ['export' => 'csv'])) . '"><i class="bi bi-filetype-csv me-1"></i>CSV</a>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Entrées', count($all), 'bi-clock-history') ?>
	<?= xa_kpi('Aujourd’hui', $today, 'bi-calendar-day', 'success') ?>
	<?= xa_kpi('Utilisateurs distincts', count(array_unique(array_column($all, 'user'))), 'bi-people', 'violet') ?>
	<?= xa_kpi('Résultats du filtre', count($rows), 'bi-funnel', 'warning') ?>
</div>
<div class="card">
	<div class="card-body border-bottom">
		<form class="row g-2" method="get">
			<div class="col-12 col-md-3"><input class="form-control form-control-sm" name="u" value="<?= e($fu) ?>" placeholder="Utilisateur (e-mail)" aria-label="Utilisateur"></div>
			<div class="col-6 col-md-2"><select class="form-select form-select-sm" name="a" aria-label="Action"><option value="">Toutes actions</option><?php foreach ($actions as $a): ?><option <?= $fa === $a ? 'selected' : '' ?>><?= e($a) ?></option><?php endforeach; ?></select></div>
			<div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="d1" value="<?= e($fd1) ?>" aria-label="Du"></div>
			<div class="col-6 col-md-2"><input type="date" class="form-control form-control-sm" name="d2" value="<?= e($fd2) ?>" aria-label="Au"></div>
			<div class="col-6 col-md-2"><input class="form-control form-control-sm" name="q" value="<?= e($fq) ?>" placeholder="Détail…" aria-label="Détail"></div>
			<div class="col-12 col-md-1 d-grid"><button class="btn btn-sm btn-primary-soft">OK</button></div>
		</form>
	</div>
	<div class="xm-table-wrap"><table data-xm-nopage class="table table-hover align-middle mb-0">
		<thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Détail</th></tr></thead><tbody>
		<?php foreach ($slice as $j): ?><tr><td class="text-nowrap small"><?= e($j['at']) ?></td><td><?= e($j['user']) ?></td><td><span class="badge bg-<?= $cls[$j['action']] ?? 'primary-subtle text-primary-emphasis' ?>"><?= e($j['action']) ?></span></td><td class="small"><?= e($j['detail'] ?? '') ?></td></tr><?php endforeach; if (!$slice): ?><tr><td colspan="4" class="text-center text-secondary py-4">Aucune entrée ne correspond.</td></tr><?php endif; ?></tbody></table></div>
	<?= xa_pagebar(count($rows), $per, $page, $params) ?>
</div>

<div class="card mt-4 border-danger-subtle"><div class="card-body d-flex flex-wrap align-items-center gap-3">
	<div class="flex-grow-1"><h2 class="h6 mb-1 text-danger">Purger le journal</h2><div class="small text-secondary">Supprime définitivement les entrées plus anciennes que la durée choisie. Réservé aux super-admins.</div></div>
	<?php if (xa_is_super()): ?><form method="post" class="d-flex gap-2" data-confirm="Purger définitivement les anciennes entrées ?"><?= csrf_field() ?><input type="hidden" name="do" value="purge"><div class="input-group input-group-sm" style="width:190px"><input class="form-control" name="days" value="90" aria-label="Jours conservés"><span class="input-group-text">jours</span></div><button class="btn btn-sm btn-danger">Purger</button></form>
	<?php else: ?><span class="badge bg-secondary"><i class="bi bi-lock me-1"></i>super-admin requis</span><?php endif; ?>
</div></div>
<?php $adminScripts = xa_common_js(); include __DIR__ . '/../partials/admin-bottom.php';
