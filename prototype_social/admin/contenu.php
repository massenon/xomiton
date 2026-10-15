<?php
require __DIR__ . '/../inc/bootstrap.php'; require_admin(); require_once __DIR__ . '/../inc/x_admin_sys.php';
$pageTitle = 'Contenu : thématiques & ressources'; $adminPage = 'contenu';
$cats = x_thematique_cats(); $base = x_thematiques();
$images = []; for ($i = 1; $i <= 16; $i++) $images[] = sprintf('hero-%02d.jpg', $i); $images = array_merge($images, ['3995814.jpg', '4137441.jpg', '6033855.jpg', '7150569.jpg', 'about-us.jpg']); for ($i = 1; $i <= 29; $i++) $images[] = 'covers/th-' . $i . '.jpg';
/* Seed des FAQ depuis le code */
if (!file_exists(store_path('faqs'))) { $seed = []; foreach (x_faq() as $i => $f) $seed[] = ['id' => $i + 1, 'q' => $f[0], 'a' => $f[1], 'published' => true]; store_save('faqs', $seed); }
$tab = in_array($_GET['tab'] ?? '', ['thematiques', 'ressources', 'faq', 'categories'], true) ? $_GET['tab'] : 'ressources';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    xa_guard();
    $do = $_POST['do'] ?? '';
    if ($do === 'res_save') {
        $title = xa_post('title'); $cat = xa_post('cat'); $sum = xa_post('summary'); $st = xa_post('statut', 'brouillon');
        if (mb_strlen($title) < 5 || !isset($cats[$cat]) || mb_strlen($sum) < 20) xa_back('danger', 'Titre (5 car. min.), catégorie et résumé (20 car. min.) requis.', 'tab=ressources');
        $row = ['title' => $title, 'cat' => $cat, 'summary' => $sum, 'image' => in_array(xa_post('image'), $images, true) ? xa_post('image') : $images[0], 'statut' => in_array($st, ['brouillon', 'publié'], true) ? $st : 'brouillon', 'base_id' => (int)xa_post('base_id'), 'updated' => date('Y-m-d')];
        if (xa_post('id') !== '') store_update('ressources_admin', xa_post('id'), $row); else store_append('ressources_admin', $row);
        x_log('Contenu', 'Ressource : ' . $title); xa_back('success', 'Ressource enregistrée.', 'tab=ressources');
    }
    if ($do === 'res_toggle') { $r = store_find('ressources_admin', xa_post('id')); if ($r) store_update('ressources_admin', $r['id'], ['statut' => $r['statut'] === 'publié' ? 'brouillon' : 'publié']); xa_back('success', 'Statut modifié.', 'tab=ressources'); }
    if ($do === 'res_delete') { store_delete('ressources_admin', xa_post('id')); x_log('Contenu', 'Ressource supprimée #' . xa_post('id')); xa_back('success', 'Ressource supprimée.', 'tab=ressources'); }
    if ($do === 'faq_save') {
        $q = xa_post('q'); $a = xa_post('a');
        if (mb_strlen($q) < 8 || mb_strlen($a) < 20) xa_back('danger', 'Question (8 car. min.) et réponse (20 car. min.) requises.', 'tab=faq');
        $row = ['q' => $q, 'a' => $a, 'published' => !empty($_POST['published'])];
        if (xa_post('id') !== '') store_update('faqs', xa_post('id'), $row); else store_append('faqs', $row);
        x_log('Contenu', 'FAQ : ' . mb_substr($q, 0, 50)); xa_back('success', 'Question enregistrée.', 'tab=faq');
    }
    if ($do === 'faq_delete') { store_delete('faqs', xa_post('id')); xa_back('success', 'Question supprimée.', 'tab=faq'); }
    if ($do === 'faq_move') {
        $rows = store_all('faqs'); $i = array_search(xa_post('id'), array_map('strval', array_column($rows, 'id')));
        $j = $i === false ? false : $i + (xa_post('dir') === 'up' ? -1 : 1);
        if ($i !== false && isset($rows[$j])) { [$rows[$i], $rows[$j]] = [$rows[$j], $rows[$i]]; store_save('faqs', $rows); }
        xa_back('success', 'Ordre modifié.', 'tab=faq');
    }
    xa_back('danger', 'Action inconnue.');
}

$res = store_all('ressources_admin'); $faqs = store_all('faqs');
$fc = $_GET['cat'] ?? '';
$baseF = array_values(array_filter($base, fn($t) => $fc === '' || $t['cat'] === $fc));
$catCount = array_count_values(array_column($base, 'cat'));
foreach ($res as $r) $catCount[$r['cat']] = ($catCount[$r['cat']] ?? 0) + 1;

$adminActions = '<a class="btn btn-outline-primary btn-sm" href="../thematiques.php" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Voir côté membre</a>';
include __DIR__ . '/../partials/admin-top.php';
function ct_tab($k, $l, $cur, $n) { return '<li class="nav-item"><a class="nav-link ' . ($cur === $k ? 'active' : '') . '" href="?tab=' . $k . '">' . e($l) . ' <span class="badge bg-secondary-subtle text-secondary-emphasis">' . $n . '</span></a></li>'; }
?>
<ul class="nav nav-tabs mb-4">
	<?= ct_tab('ressources', 'Ressources gérées', $tab, count($res)) ?><?= ct_tab('thematiques', 'Thématiques de base', $tab, count($base)) ?><?= ct_tab('faq', 'FAQ', $tab, count($faqs)) ?><?= ct_tab('categories', 'Catégories', $tab, count($cats)) ?>
</ul>

<?php if ($tab === 'ressources'): ?>
<?= xa_head('Ressources et surcharges de thématiques', 'Créez une nouvelle ressource ou surchargez une thématique de base. Seules les ressources publiées sont visibles des membres.', '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mRes"><i class="bi bi-plus-lg me-1"></i>Nouvelle ressource</button>') ?>
<div class="row g-3">
	<?php foreach ($res as $r): [$cl, $ci, $cc] = $cats[$r['cat']] ?? ['—', 'bi-tag', 'secondary']; ?>
	<div class="col-md-6 col-xl-4"><div class="card h-100">
		<div class="xm-ratio-16x9"><img src="<?= img($r['image']) ?>" class="xm-img-cover" alt="" loading="lazy"></div>
		<div class="card-body">
			<div class="d-flex justify-content-between mb-2"><span class="badge bg-<?= $cc ?>-subtle text-<?= $cc ?>-emphasis"><i class="bi <?= $ci ?> me-1"></i><?= e($cl) ?></span><?= xa_status_badge($r['statut']) ?></div>
			<h3 class="h6"><?= e($r['title']) ?></h3><p class="small text-secondary mb-0"><?= e($r['summary']) ?></p>
			<?php if (!empty($r['base_id'])): ?><div class="small mt-2"><i class="bi bi-layers me-1"></i>Surcharge la thématique n°<?= (int)$r['base_id'] ?></div><?php endif; ?>
		</div>
		<div class="card-footer d-flex gap-2">
			<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mRes" data-fill='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>'><i class="bi bi-pencil"></i></button>
			<form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="res_toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light"><?= $r['statut'] === 'publié' ? 'Dépublier' : 'Publier' ?></button></form>
			<form method="post" class="ms-auto" data-confirm="Supprimer cette ressource ?"><?= csrf_field() ?><input type="hidden" name="do" value="res_delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-light text-danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form>
		</div>
	</div></div>
	<?php endforeach; if (!$res): ?><div class="col-12"><div class="card"><div class="card-body text-center text-secondary py-5">Aucune ressource gérée pour l’instant. Cliquez sur « Nouvelle ressource ».</div></div></div><?php endif; ?>
</div>

<?php elseif ($tab === 'thematiques'): ?>
<?= xa_head('29 thématiques de base (lecture seule)', 'Contenu éditorial source. Pour le modifier, créez une ressource qui la surcharge.') ?>
<form method="get" class="mb-3 d-flex gap-2"><input type="hidden" name="tab" value="thematiques"><select class="form-select form-select-sm w-auto" name="cat" onchange="this.form.submit()"><option value="">Toutes les catégories</option><?php foreach ($cats as $k => $c): ?><option value="<?= $k ?>" <?= $fc === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option><?php endforeach; ?></select></form>
<div class="card"><div class="xm-table-wrap"><table class="table table-hover align-middle mb-0">
	<thead><tr><th>N°</th><th></th><th>Titre</th><th>Catégorie</th><th>Durée</th><th></th></tr></thead>
	<tbody><?php foreach ($baseF as $t): [$cl, , $cc] = $cats[$t['cat']]; ?>
		<tr><td><?= (int)$t['id'] ?></td><td><img src="<?= img($t['cover']) ?>" width="72" height="48" style="min-width:72px" class="rounded object-fit-cover" alt="Couverture de la thématique : <?= e($t['title']) ?>" title="<?= e($t['title']) ?>" loading="lazy"></td><td><div class="fw-semibold"><?= e($t['title']) ?></div><div class="small text-secondary"><?= e($t['summary']) ?></div></td>
		<td><span class="badge bg-<?= $cc ?>-subtle text-<?= $cc ?>-emphasis"><?= e($cl) ?></span></td><td><?= (int)$t['minutes'] ?> min</td>
		<td class="text-end"><button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mRes" data-fill='<?= e(json_encode(['title' => $t['title'], 'cat' => $t['cat'], 'summary' => $t['summary'], 'image' => $t['cover'], 'base_id' => $t['id'], 'statut' => 'brouillon'], JSON_UNESCAPED_UNICODE)) ?>' title="Surcharger"><i class="bi bi-layers"></i></button></td></tr>
	<?php endforeach; ?></tbody></table></div></div>

<?php elseif ($tab === 'faq'): ?>
<?= xa_head('Foire aux questions', 'Éditez, ordonnez et publiez les questions affichées sur la page d’accueil et l’aide.', '<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#mFaq"><i class="bi bi-plus-lg me-1"></i>Question</button>') ?>
<div class="card"><ul class="list-group list-group-flush">
	<?php foreach ($faqs as $f): ?><li class="list-group-item d-flex flex-wrap gap-3 align-items-start">
		<div class="flex-grow-1"><div class="fw-semibold"><?= e($f['q']) ?> <?= $f['published'] ? '' : '<span class="badge bg-secondary">masquée</span>' ?></div><div class="small text-secondary"><?= e($f['a']) ?></div></div>
		<form method="post" class="btn-group btn-group-sm"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="do" value="faq_move">
			<button name="dir" value="up" class="btn btn-light" aria-label="Monter"><i class="bi bi-arrow-up"></i></button><button name="dir" value="down" class="btn btn-light" aria-label="Descendre"><i class="bi bi-arrow-down"></i></button></form>
		<button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#mFaq" data-fill='<?= e(json_encode($f, JSON_UNESCAPED_UNICODE)) ?>' aria-label="Modifier"><i class="bi bi-pencil"></i></button>
		<form method="post" data-confirm="Supprimer cette question ?"><?= csrf_field() ?><input type="hidden" name="do" value="faq_delete"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><button class="btn btn-sm btn-light text-danger" aria-label="Supprimer"><i class="bi bi-trash"></i></button></form>
	</li><?php endforeach; ?>
</ul></div>

<?php else: ?>
<?= xa_head('Catégories de contenu', 'Les catégories sont définies dans le référentiel de la plateforme.') ?>
<div class="row g-3"><?php foreach ($cats as $k => $c): ?><div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body d-flex gap-3 align-items-center"><div class="xm-feature-icon bg-<?= $c[2] ?>-subtle text-<?= $c[2] ?>"><i class="bi <?= $c[1] ?>"></i></div><div><div class="fw-semibold"><?= e($c[0]) ?></div><div class="small text-secondary"><code><?= $k ?></code> · <?= (int)($catCount[$k] ?? 0) ?> contenu(s)</div></div></div></div></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="modal fade" id="mRes" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg"><form class="modal-content" method="post" data-xm-validate data-fill-form>
	<?= csrf_field() ?><input type="hidden" name="do" value="res_save"><input type="hidden" name="id"><input type="hidden" name="base_id">
	<div class="modal-header"><h5 class="modal-title" data-title-new="Nouvelle ressource" data-title-edit="Modifier la ressource">Nouvelle ressource</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-12"><label class="form-label">Titre *</label><input class="form-control" name="title" required></div>
		<div class="col-md-6"><label class="form-label">Catégorie *</label><select class="form-select" name="cat" required><?php foreach ($cats as $k => $c): ?><option value="<?= $k ?>"><?= e($c[0]) ?></option><?php endforeach; ?></select></div>
		<div class="col-md-6"><label class="form-label">Statut</label><select class="form-select" name="statut"><option value="brouillon">Brouillon</option><option value="publié">Publié</option></select></div>
		<div class="col-12"><label class="form-label">Résumé * <small class="text-secondary">(20 caractères min.)</small></label><textarea class="form-control" name="summary" rows="4" required data-rule="min_len_20"></textarea></div>
		<div class="col-12"><label class="form-label">Image</label><select class="form-select" name="image" id="resImg"><?php foreach ($images as $im): ?><option><?= e($im) ?></option><?php endforeach; ?></select><img id="resImgPrev" class="rounded mt-2" style="height:80px" src="<?= img($images[0]) ?>" alt="Aperçu"></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>

<div class="modal fade" id="mFaq" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><form class="modal-content" method="post" data-xm-validate data-fill-form>
	<?= csrf_field() ?><input type="hidden" name="do" value="faq_save"><input type="hidden" name="id">
	<div class="modal-header"><h5 class="modal-title" data-title-new="Nouvelle question" data-title-edit="Modifier la question">Nouvelle question</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
	<div class="modal-body row g-3">
		<div class="col-12"><label class="form-label">Question *</label><input class="form-control" name="q" required></div>
		<div class="col-12"><label class="form-label">Réponse *</label><textarea class="form-control" name="a" rows="5" required data-rule="min_len_20"></textarea></div>
		<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="published" value="1" id="faqPub" checked><label class="form-check-label" for="faqPub">Publiée</label></div></div>
	</div>
	<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button><button class="btn btn-primary">Enregistrer</button></div>
</form></div></div>
<?php
$adminScripts = xa_common_js() . '<script>(function(){var s=document.getElementById("resImg"),p=document.getElementById("resImgPrev");if(!s)return;function u(){p.src="' . img('') . '"+s.value}s.addEventListener("change",u);document.getElementById("mRes").addEventListener("shown.bs.modal",u);})();</script>';
include __DIR__ . '/../partials/admin-bottom.php';
