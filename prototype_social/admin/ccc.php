<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Outils CCC'; $adminPage = 'ccc';

/* Catalogue unifié des outils CCC : clé => [type, titre, détail, image] */
$items = [];
foreach (x_ccc_manuel() as $m) $items['manuel-' . $m[0]] = ['manuel', $m[1], count($m[2]) . ' chapitres · ' . $m[3] . ' min', 'hero-04.jpg'];
foreach (x_ccc_boites() as $i => $b) $items['boite-' . ($i + 1)] = ['boite', $b[0], $b[2], $b[1]];
foreach (x_ccc_bd() as $b) $items['bd-' . $b[0]] = ['bd', $b[1], count($b[3]) . ' planches', $b[2]];
foreach (x_lois() as $l) $items['loi-' . $l[0]] = ['loi', $l[1], $l[2] . ' · ' . $l[4], 'hero-13.jpg'];
$types = ['manuel' => ['Manuel du pair animateur', 'bi-journal-richtext'], 'boite' => ['Boîtes à images', 'bi-images'], 'bd' => ['Bandes dessinées SSR', 'bi-chat-square-text'], 'loi' => ['Lois et codes du Bénin', 'bi-bank']];

$stored = []; foreach (store_all('ccc_items') as $r) $stored[$r['key']] = $r;
function ccc_set($key, $patch) {
    foreach (store_all('ccc_items') as $r) if ($r['key'] === $key) { store_update('ccc_items', $r['id'], $patch); return; }
    store_append('ccc_items', ['key' => $key, 'published' => true] + $patch);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? ''; $key = xa_post('key'); $t = xa_post('t', 'manuel');
    if ($do === 'toggle' && isset($items[$key])) {
        $cur = $stored[$key]['published'] ?? true; ccc_set($key, ['published' => !$cur]);
        x_log('CCC', ($cur ? 'Dépublication : ' : 'Publication : ') . $items[$key][1]); xa_back('success', $cur ? 'Outil retiré de la publication.' : 'Outil publié.', 't=' . $t);
    }
    if ($do === 'upload' && isset($items[$key])) {
        $f = $_FILES['img'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) xa_back('danger', 'Aucun fichier reçu.', 't=' . $t);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $info = @getimagesize($f['tmp_name']);
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || !$info) xa_back('danger', 'Format non accepté (JPG, PNG ou WebP uniquement).', 't=' . $t);
        if ($f['size'] > 3 * 1024 * 1024) xa_back('danger', 'Image trop lourde (3 Mo maximum).', 't=' . $t);
        $dir = X_ROOT . '/assets/uploads/ccc'; if (!is_dir($dir)) mkdir($dir, 0777, true);
        $name = preg_replace('/[^a-z0-9-]/', '', $key) . '-' . time() . '.' . $ext;
        move_uploaded_file($f['tmp_name'], $dir . '/' . $name);
        ccc_set($key, ['image' => 'assets/uploads/ccc/' . $name]); x_log('CCC', 'Image remplacée : ' . $items[$key][1]);
        xa_back('success', 'Image téléversée.', 't=' . $t);
    }
    xa_back('danger', 'Action inconnue.', 't=' . $t);
}

$tab = isset($types[$_GET['t'] ?? '']) ? $_GET['t'] : 'manuel';
$countPub = count(array_filter(array_keys($items), fn($k) => $stored[$k]['published'] ?? true));
$adminActions = '<a class="btn btn-outline-primary btn-sm" href="../ccc.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Aperçu membre</a>';
include __DIR__ . '/../partials/admin-top.php';
?>
<div class="row g-3 mb-4">
	<?= xa_kpi('Outils numérisés', count($items), 'bi-collection-play') ?>
	<?= xa_kpi('Publiés', $countPub, 'bi-eye', 'success') ?>
	<?= xa_kpi('Masqués', count($items) - $countPub, 'bi-eye-slash', 'warning') ?>
	<?= xa_kpi('Images personnalisées', count(array_filter($stored, fn($s) => !empty($s['image']))), 'bi-image', 'violet') ?>
</div>
<ul class="nav nav-tabs mb-4">
	<?php foreach ($types as $k => $t): $n = count(array_filter($items, fn($i) => $i[0] === $k)); ?>
	<li class="nav-item"><a class="nav-link <?= $tab === $k ? 'active' : '' ?>" href="?t=<?= $k ?>"><i class="bi <?= $t[1] ?> me-1"></i><?= e($t[0]) ?> <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= $n ?></span></a></li>
	<?php endforeach; ?>
</ul>
<div class="row g-3">
<?php foreach ($items as $key => $it): if ($it[0] !== $tab) continue; $pub = $stored[$key]['published'] ?? true; $im = !empty($stored[$key]['image']) ? '../' . $stored[$key]['image'] : img($it[3]); ?>
	<div class="col-md-6 col-xl-4"><div class="card h-100 <?= $pub ? '' : 'opacity-75' ?>">
		<div class="xm-ratio-16x9 position-relative"><img src="<?= e($im) ?>" class="xm-img-cover" alt="" loading="lazy"><span class="position-absolute top-0 end-0 m-2 badge bg-<?= $pub ? 'success' : 'secondary' ?>"><?= $pub ? 'Publié' : 'Masqué' ?></span></div>
		<div class="card-body"><h3 class="h6"><?= e($it[1]) ?></h3><p class="small text-secondary mb-0"><?= e($it[2]) ?></p></div>
		<div class="card-footer d-flex align-items-center gap-2">
			<form method="post" class="form-check form-switch mb-0"><?= csrf_field() ?><input type="hidden" name="do" value="toggle"><input type="hidden" name="key" value="<?= e($key) ?>"><input type="hidden" name="t" value="<?= e($tab) ?>">
				<input class="form-check-input" type="checkbox" role="switch" <?= $pub ? 'checked' : '' ?> onchange="this.form.submit()" id="sw<?= e($key) ?>"><label class="form-check-label small" for="sw<?= e($key) ?>">Publié</label></form>
			<button class="btn btn-sm btn-light ms-auto" data-bs-toggle="modal" data-bs-target="#mUp" data-key="<?= e($key) ?>" data-name="<?= e($it[1]) ?>"><i class="bi bi-image me-1"></i>Image</button>
		</div>
	</div></div>
<?php endforeach; ?>
</div>

<div class="modal fade" id="mUp" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" enctype="multipart/form-data">
	<?= csrf_field() ?><input type="hidden" name="do" value="upload"><input type="hidden" name="key" id="upKey"><input type="hidden" name="t" value="<?= e($tab) ?>">
	<div class="modal-header"><h5 class="modal-title">Remplacer l’image</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body"><p class="small text-secondary mb-2" id="upName"></p><input type="file" name="img" accept="image/jpeg,image/png,image/webp" class="form-control" required><div class="form-text">JPG, PNG ou WebP · 3 Mo maximum. Image de substitution utilisée dans la vignette de l’outil.</div></div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Téléverser</button></div>
</form></div></div>
<?php
$adminScripts = '<script>document.getElementById("mUp").addEventListener("show.bs.modal",function(e){var b=e.relatedTarget;document.getElementById("upKey").value=b.dataset.key;document.getElementById("upName").textContent=b.dataset.name;});</script>';
include __DIR__ . '/../partials/admin-bottom.php';
