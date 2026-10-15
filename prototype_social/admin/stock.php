<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Gestion de stock'; $adminPage = 'stock';
$cats = ['Prévention', 'Dépistage', 'Dignité', 'Santé', 'Communication', 'Autre'];
$locs = array_keys(x_zones());

/* ---- Exports CSV ---- */
if (($_GET['export'] ?? '') === 'stock') {
    xa_csv('xomiton-stock-' . date('Ymd') . '.csv', ['Article', 'Catégorie', 'Unité', 'Quantité', 'Seuil min.', 'Lieu', 'Mise à jour'],
        array_map(fn($s) => [$s['item'], $s['category'], $s['unit'], $s['qty'], $s['min'], $s['location'], $s['updated']], store_all('stock')));
}
if (($_GET['export'] ?? '') === 'mouvements') {
    $items = array_column(store_all('stock'), null, 'id');
    xa_csv('xomiton-mouvements-' . date('Ymd') . '.csv', ['Date', 'Article', 'Type', 'Quantité', 'Par', 'Note'],
        array_map(fn($m) => [$m['date'], $items[$m['item_id']]['item'] ?? '#' . $m['item_id'], $m['type'], $m['qty'], $m['by'], $m['note'] ?? ''], store_all('stock_mouvements')));
}

/* ---- Actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'save_item') {
        $name = xa_post('item'); $qty = xa_post('qty', '0'); $min = xa_post('min', '0'); $loc = xa_post('location');
        if (mb_strlen($name) < 2 || !ctype_digit($qty) || !ctype_digit($min) || $loc === '') xa_back('danger', 'Données invalides : nom, quantité (entier positif), seuil et lieu sont requis.');
        $row = ['item' => $name, 'category' => xa_post('category', 'Autre'), 'unit' => xa_post('unit', 'unité'), 'qty' => (int)$qty, 'min' => (int)$min, 'location' => $loc, 'updated' => date('Y-m-d')];
        if (xa_post('id') !== '') { store_update('stock', xa_post('id'), $row); x_log('Stock', 'Article modifié : ' . $name); xa_back('success', 'Article mis à jour.'); }
        store_append('stock', $row); x_log('Stock', 'Article créé : ' . $name); xa_back('success', 'Article ajouté au stock.');
    }
    if ($do === 'delete_item') { $it = store_find('stock', xa_post('id')); store_delete('stock', xa_post('id')); x_log('Stock', 'Article supprimé : ' . ($it['item'] ?? '')); xa_back('success', 'Article supprimé.'); }
    if ($do === 'move') {
        $it = store_find('stock', xa_post('item_id')); $type = xa_post('type'); $q = xa_post('qty');
        if (!$it || !in_array($type, ['entrée', 'sortie', 'transfert'], true) || !ctype_digit($q) || (int)$q < 1) xa_back('danger', 'Mouvement invalide : article, type et quantité (≥ 1) requis.');
        $q = (int)$q;
        $by = x_user()['name'] ?? 'admin'; $note = xa_post('note');
        if ($type === 'entrée') {
            store_update('stock', $it['id'], ['qty' => $it['qty'] + $q, 'updated' => date('Y-m-d')]);
        } else {
            if ($q > $it['qty']) xa_back('danger', 'Refusé : la sortie (' . $q . ') dépasse le stock disponible (' . $it['qty'] . ').');
            store_update('stock', $it['id'], ['qty' => $it['qty'] - $q, 'updated' => date('Y-m-d')]);
            if ($type === 'transfert') {
                $dest = xa_post('dest');
                if ($dest === '' || $dest === $it['location']) xa_back('danger', 'Choisissez un lieu de destination différent du lieu source.');
                $found = null;
                foreach (store_all('stock') as $s) if ($s['item'] === $it['item'] && $s['location'] === $dest) $found = $s;
                if ($found) store_update('stock', $found['id'], ['qty' => $found['qty'] + $q, 'updated' => date('Y-m-d')]);
                else store_append('stock', ['item' => $it['item'], 'category' => $it['category'], 'unit' => $it['unit'], 'qty' => $q, 'min' => 0, 'location' => $dest, 'updated' => date('Y-m-d')]);
                $note = trim('Vers ' . $dest . '. ' . $note);
            }
        }
        store_append('stock_mouvements', ['item_id' => (int)$it['id'], 'type' => $type, 'qty' => $q, 'date' => date('Y-m-d H:i'), 'by' => $by, 'note' => $note]);
        x_log('Stock', ucfirst($type) . ' ' . $q . ' × ' . $it['item']);
        xa_back('success', 'Mouvement enregistré : ' . $type . ' de ' . $q . ' × ' . $it['item'] . '.');
    }
    xa_back('danger', 'Action inconnue.');
}

$all = store_all('stock');
$fq = trim($_GET['q'] ?? ''); $floc = $_GET['loc'] ?? ''; $fcat = $_GET['cat'] ?? '';
$rows = array_values(array_filter($all, fn($s) => ($fq === '' || stripos($s['item'], $fq) !== false) && ($floc === '' || $s['location'] === $floc) && ($fcat === '' || $s['category'] === $fcat)));
usort($rows, fn($a, $b) => ($a['qty'] / max(1, $a['min'])) <=> ($b['qty'] / max(1, $b['min'])));
$low = array_filter($all, fn($s) => $s['qty'] < $s['min']);
$out = array_filter($all, fn($s) => $s['qty'] <= 0);
$mv = array_reverse(store_all('stock_mouvements'));
$byId = array_column($all, null, 'id');
$allLocs = array_values(array_unique(array_merge($locs, array_column($all, 'location'))));
sort($allLocs);
$perLoc = [];
foreach ($all as $s) { $l = $s['location']; $perLoc[$l] = $perLoc[$l] ?? ['n' => 0, 'qty' => 0, 'low' => 0]; $perLoc[$l]['n']++; $perLoc[$l]['qty'] += $s['qty']; if ($s['qty'] < $s['min']) $perLoc[$l]['low']++; }
ksort($perLoc);
$m30 = count(array_filter($mv, fn($m) => strtotime($m['date']) > time() - 30 * 86400));

$adminActions = '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mItem"><i class="bi bi-plus-lg me-1"></i>Article</button>';
include __DIR__ . '/../partials/admin-top.php';
function stk_state($s) { if ($s['qty'] <= 0) return ['danger', 'Rupture']; if ($s['qty'] < $s['min']) return ['danger', 'Sous le seuil']; if ($s['qty'] < $s['min'] * 1.25) return ['warning text-dark', 'Limite']; return ['success', 'OK']; }
?>
<div class="no-print">
<div class="row g-3 mb-4">
	<?= xa_kpi('Articles suivis', count($all), 'bi-box-seam') ?>
	<?= xa_kpi('Sous le seuil', count($low), 'bi-exclamation-triangle', 'warning') ?>
	<?= xa_kpi('Ruptures', count($out), 'bi-x-octagon', 'danger') ?>
	<?= xa_kpi('Mouvements (30 j)', $m30, 'bi-arrow-left-right', 'success') ?>
</div>

<?php if ($low): ?>
<div class="alert alert-warning d-flex gap-3 align-items-start">
	<i class="bi bi-bell-fill fs-4"></i>
	<div><strong>Alertes de rupture / réapprovisionnement</strong>
		<ul class="mb-0 mt-1 small">
		<?php foreach ($low as $s): ?><li><strong><?= e($s['item']) ?></strong> (<?= e($s['location']) ?>) : <?= (int)$s['qty'] ?> en stock pour un seuil de <?= (int)$s['min'] ?> — à réapprovisionner de <?= max(0, $s['min'] * 2 - $s['qty']) ?> <?= e($s['unit']) ?>.</li><?php endforeach; ?>
		</ul></div>
</div>
<?php endif; ?>

<div class="row g-4">
	<div class="col-xl-9">
		<div class="card mb-4">
			<div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
				<h2 class="h6 mb-0">Inventaire</h2>
				<div class="d-flex flex-wrap gap-2">
					<button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mMove"><i class="bi bi-arrow-left-right me-1"></i>Mouvement</button>
					<a class="btn btn-outline-secondary btn-sm" href="?export=stock"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
					<button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Fiche d’inventaire</button>
				</div>
			</div>
			<div class="card-body border-bottom">
				<form class="row g-2" method="get">
					<div class="col-12 col-md-4"><input type="search" name="q" value="<?= e($fq) ?>" class="form-control form-control-sm" placeholder="Rechercher un article…"></div>
					<div class="col-6 col-md-3"><select name="loc" class="form-select form-select-sm"><option value="">Tous les lieux</option><?php foreach ($allLocs as $l): ?><option <?= $floc === $l ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
					<div class="col-6 col-md-3"><select name="cat" class="form-select form-select-sm"><option value="">Toutes catégories</option><?php foreach ($cats as $c): ?><option <?= $fcat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></div>
					<div class="col-12 col-md-2 d-grid"><button class="btn btn-sm btn-primary-soft">Filtrer</button></div>
				</form>
			</div>
			<div class="xm-table-wrap">
				<table class="table table-hover align-middle mb-0">
					<thead><tr><th>Article</th><th>Lieu</th><th style="min-width:170px">Niveau</th><th>État</th><th class="text-end">Actions</th></tr></thead>
					<tbody>
					<?php foreach ($rows as $s): [$bc, $bl] = stk_state($s); $pct = (int)min(100, round($s['qty'] / max(1, $s['min'] * 2) * 100)); ?>
						<tr>
							<td><div class="fw-semibold"><?= e($s['item']) ?></div><div class="small text-secondary"><?= e($s['category']) ?> · <?= e($s['unit']) ?> · maj <?= e(xa_fr_date($s['updated'])) ?></div></td>
							<td><?= e($s['location']) ?></td>
							<td><div class="d-flex justify-content-between small"><strong><?= (int)$s['qty'] ?></strong><span class="text-secondary">min <?= (int)$s['min'] ?></span></div>
								<div class="progress" style="height:8px" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-<?= explode(' ', $bc)[0] ?>" style="width:<?= $pct ?>%"></div></div></td>
							<td><span class="badge bg-<?= $bc ?>"><?= $bl ?></span></td>
							<td class="text-end text-nowrap">
								<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mMove" data-fill='<?= e(json_encode(['item_id' => $s['id']])) ?>' title="Mouvement"><i class="bi bi-arrow-left-right"></i></button>
								<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mItem" data-fill='<?= e(json_encode($s, JSON_UNESCAPED_UNICODE)) ?>' title="Modifier"><i class="bi bi-pencil"></i></button>
								<form method="post" class="d-inline" data-confirm="Supprimer cet article ?"><?= csrf_field() ?><input type="hidden" name="do" value="delete_item"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-sm btn-light text-danger" title="Supprimer"><i class="bi bi-trash"></i></button></form>
							</td>
						</tr>
					<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="text-center text-secondary py-4">Aucun article ne correspond.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center"><h2 class="h6 mb-0">Historique des mouvements</h2><a class="btn btn-outline-secondary btn-sm" href="?export=mouvements"><i class="bi bi-filetype-csv me-1"></i>CSV</a></div>
			<div class="xm-table-wrap">
				<table class="table align-middle mb-0">
					<thead><tr><th>Date</th><th>Article</th><th>Type</th><th class="text-end">Qté</th><th>Par</th><th>Note</th></tr></thead>
					<tbody>
					<?php foreach (array_slice($mv, 0, 25) as $m): $tc = ['entrée' => 'success', 'sortie' => 'danger', 'transfert' => 'info'][$m['type']] ?? 'secondary'; ?>
						<tr><td class="text-nowrap"><?= e($m['date']) ?></td><td><?= e($byId[$m['item_id']]['item'] ?? '#' . $m['item_id']) ?></td><td><span class="badge bg-<?= $tc ?>"><?= e($m['type']) ?></span></td><td class="text-end"><?= (int)$m['qty'] ?></td><td><?= e($m['by']) ?></td><td class="small text-secondary"><?= e($m['note'] ?? '') ?></td></tr>
					<?php endforeach; if (!$mv): ?><tr><td colspan="6" class="text-center text-secondary py-4">Aucun mouvement enregistré. Utilisez « Mouvement » pour une entrée, sortie ou un transfert.</td></tr><?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<div class="col-xl-3">
		<div class="card">
			<div class="card-header"><h2 class="h6 mb-0">Synthèse par lieu</h2></div>
			<ul class="list-group list-group-flush">
				<?php foreach ($perLoc as $l => $d): ?>
				<li class="list-group-item d-flex justify-content-between align-items-center">
					<div><div class="fw-semibold"><?= e($l) ?></div><div class="small text-secondary"><?= $d['n'] ?> articles · <?= $d['qty'] ?> unités</div></div>
					<?php if ($d['low']): ?><span class="badge bg-danger"><?= $d['low'] ?> alerte<?= $d['low'] > 1 ? 's' : '' ?></span><?php else: ?><span class="badge bg-success">OK</span><?php endif; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</div>
</div>

<!-- Fiche d'inventaire imprimable -->
<div class="xm-print-only">
	<img src="../assets/images/logo.svg" height="36" alt="Xomiton">
	<h2 class="h4 mt-3">Fiche d’inventaire — <?= date('d/m/Y') ?></h2>
	<table class="table table-bordered table-sm">
		<thead><tr><th>Article</th><th>Lieu</th><th>Unité</th><th>Théorique</th><th>Compté</th><th>Écart</th></tr></thead>
		<tbody><?php foreach ($all as $s): ?><tr><td><?= e($s['item']) ?></td><td><?= e($s['location']) ?></td><td><?= e($s['unit']) ?></td><td><?= (int)$s['qty'] ?></td><td style="width:90px"></td><td style="width:90px"></td></tr><?php endforeach; ?></tbody>
	</table>
	<p class="mt-4">Responsable : ______________________ Signature : ______________________</p>
</div>

<!-- Modale article -->
<div class="modal fade" id="mItem" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" data-xm-validate data-fill-form>
	<?= csrf_field() ?><input type="hidden" name="do" value="save_item"><input type="hidden" name="id">
	<div class="modal-header"><h5 class="modal-title" data-title-edit="Modifier l’article" data-title-new="Nouvel article">Nouvel article</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-12"><label class="form-label">Article *</label><input class="form-control" name="item" required></div>
		<div class="col-6"><label class="form-label">Catégorie</label><select class="form-select" name="category"><?php foreach ($cats as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
		<div class="col-6"><label class="form-label">Unité</label><input class="form-control" name="unit" value="unité"></div>
		<div class="col-6"><label class="form-label">Quantité *</label><input class="form-control" name="qty" value="0" required data-rule="positive_int"></div>
		<div class="col-6"><label class="form-label">Seuil minimum *</label><input class="form-control" name="min" value="10" required data-rule="positive_int"></div>
		<div class="col-12"><label class="form-label">Lieu de stockage *</label><select class="form-select" name="location" required><option value="">—</option><?php foreach ($allLocs as $l): ?><option><?= e($l) ?></option><?php endforeach; ?></select></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>

<!-- Modale mouvement -->
<div class="modal fade" id="mMove" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" data-xm-validate data-fill-form>
	<?= csrf_field() ?><input type="hidden" name="do" value="move">
	<div class="modal-header"><h5 class="modal-title">Enregistrer un mouvement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-12"><label class="form-label">Article *</label><select class="form-select" name="item_id" required><option value="">—</option><?php foreach ($all as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['item'] . ' — ' . $s['location'] . ' (' . $s['qty'] . ')') ?></option><?php endforeach; ?></select></div>
		<div class="col-6"><label class="form-label">Type *</label><select class="form-select" name="type" id="mvType"><option>entrée</option><option>sortie</option><option>transfert</option></select></div>
		<div class="col-6"><label class="form-label">Quantité *</label><input class="form-control" name="qty" required data-rule="positive_int" inputmode="numeric"></div>
		<div class="col-12 d-none" id="mvDest"><label class="form-label">Lieu de destination</label><select class="form-select" name="dest"><option value="">—</option><?php foreach ($allLocs as $l): ?><option><?= e($l) ?></option><?php endforeach; ?></select></div>
		<div class="col-12"><label class="form-label">Note</label><input class="form-control" name="note" placeholder="Ex. distribution atelier, réception fournisseur…"></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Valider le mouvement</button></div>
</form></div></div>
<?php
$adminScripts = xa_common_js() . '<script>
(function(){ var t=document.getElementById("mvType"), d=document.getElementById("mvDest"); if(!t) return;
t.addEventListener("change",function(){ d.classList.toggle("d-none", t.value!=="transfert"); });
document.getElementById("mMove").addEventListener("show.bs.modal",function(){ setTimeout(function(){ d.classList.add("d-none"); },0); }); })();
</script>';
include __DIR__ . '/../partials/admin-bottom.php';
