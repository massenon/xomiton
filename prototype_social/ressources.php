<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Ressources';
$nav = 'ressources';
$cats = x_thematique_cats();
$q = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? '';
$all = x_thematiques();
$items = array_filter($all, function ($t) use ($q, $cat) {
    if ($cat !== '' && $t['cat'] !== $cat) return false;
    if ($q !== '' && mb_stripos($t['title'] . ' ' . $t['summary'], $q) === false) return false;
    return true;
});
$fiches = [
    ['Fiche mémo : reconnaître les 5 formes de VBG', '2 pages', 'vbg'],
    ['Fiche : mes droits en cas de violence au Bénin', '2 pages', 'droits'],
    ['Fiche : que faire après une agression ? (72 h)', '1 page', 'vbg'],
    ['Fiche : plan de sécurité à imprimer', '1 page', 'vbg'],
    ['Fiche : hygiène menstruelle, bien choisir', '2 pages', 'regles'],
    ['Fiche : contraception, comparatif des méthodes', '3 pages', 'esc'],
];
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
					<div class="card card-body">
						<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
							<div><h1 class="h3 mb-0">Ressources</h1><p class="text-secondary mb-0">Articles, fiches pratiques et outils à lire, à enregistrer et à partager.</p></div>
							<span class="badge bg-success-soft text-success align-self-start"><i class="bi bi-cloud-check me-1"></i><span id="offCount">0</span> disponible(s) hors connexion</span>
						</div>
						<form method="get" class="row g-2">
							<div class="col-md-6"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Rechercher une ressource…" aria-label="Rechercher"></div></div>
							<div class="col-md-4"><select class="form-select" name="cat" aria-label="Catégorie" onchange="this.form.submit()"><option value="">Toutes les catégories</option><?php foreach ($cats as $k => $c): ?><option value="<?= e($k) ?>" <?= $cat === $k ? 'selected' : '' ?>><?= e($c[0]) ?></option><?php endforeach; ?></select></div>
							<div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">Filtrer</button></div>
						</form>
						<div class="d-flex flex-wrap gap-2 mt-3">
							<a class="xm-chip text-decoration-none <?= $cat === '' ? 'bg-primary text-white' : 'bg-light' ?>" href="ressources.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">Tout</a>
							<?php foreach ($cats as $k => $c): ?>
							<a class="xm-chip text-decoration-none <?= $cat === $k ? 'bg-primary text-white' : 'bg-light' ?>" href="?cat=<?= e($k) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><i class="bi <?= e($c[1]) ?>"></i><?= e($k === 'vbg' ? 'VBG' : $c[0]) ?></a>
							<?php endforeach; ?>
						</div>
					</div>

					<div>
						<h2 class="h5 mb-3"><?= count($items) ?> article(s)<?= $q !== '' ? ' pour « ' . e($q) . ' »' : '' ?></h2>
						<div class="row g-3" id="resGrid">
							<?php foreach ($items as $t): $c = $cats[$t['cat']]; ?>
							<div class="col-sm-6 col-xl-4 res-item" data-id="<?= $t['id'] ?>">
								<div class="card h-100 overflow-hidden xm-card-hover">
									<a href="thematique.php?id=<?= $t['id'] ?>" class="xm-ratio-16x9 d-block"><img class="xm-img-cover" src="<?= img($t['cover']) ?>" alt="" loading="lazy"></a>
									<div class="card-body d-flex flex-column">
										<span class="badge bg-<?= e($c[2]) ?> bg-opacity-10 text-<?= e($c[2]) ?> align-self-start mb-2"><?= e($c[0]) ?></span>
										<h3 class="h6"><a href="thematique.php?id=<?= $t['id'] ?>"><?= e($t['title']) ?></a></h3>
										<p class="small text-secondary flex-grow-1"><?= e($t['summary']) ?></p>
										<div class="d-flex justify-content-between align-items-center">
											<small class="text-secondary"><i class="bi bi-clock me-1"></i><?= (int)$t['minutes'] ?> min</small>
											<div class="form-check form-switch mb-0" title="Rendre disponible hors connexion"><input class="form-check-input off-toggle" type="checkbox" role="switch" id="off<?= $t['id'] ?>" data-id="<?= $t['id'] ?>"><label class="form-check-label small" for="off<?= $t['id'] ?>">Hors ligne</label></div>
										</div>
									</div>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
						<?php if (!$items): ?><div class="card card-body text-center text-secondary"><i class="bi bi-search fs-1"></i><p class="mb-2">Aucune ressource ne correspond à votre recherche.</p><a href="ressources.php">Réinitialiser les filtres</a></div><?php endif; ?>
						<p class="small text-secondary mt-3" id="offEmpty" hidden>Aucune ressource n’est enregistrée pour le hors connexion dans cette sélection.</p>
					</div>

					<div class="card">
						<div class="card-header"><h2 class="h5 mb-0"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Fiches à télécharger</h2></div>
						<div class="list-group list-group-flush">
							<?php foreach ($fiches as $i => $f): ?>
							<div class="list-group-item d-flex flex-wrap align-items-center gap-2">
								<i class="bi bi-file-earmark-pdf-fill text-danger fs-4"></i>
								<div class="flex-grow-1"><div class="fw-semibold"><?= e($f[0]) ?></div><small class="text-secondary">PDF · <?= e($f[1]) ?> · <?= e($cats[$f[2]][0]) ?> <span class="badge bg-warning text-dark">exemple</span></small></div>
								<button class="btn btn-sm btn-primary-soft" type="button" onclick="XM.toast('Fiche PDF de démonstration : le fichier sera ajouté ultérieurement.', 'info')"><i class="bi bi-download me-1"></i>Télécharger</button>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var K = 'xm_offline_res_v1', saved = [];
		try { saved = JSON.parse(localStorage.getItem(K) || '[]'); } catch (e) {}
		var only = false;
		function persist() { try { localStorage.setItem(K, JSON.stringify(saved)); } catch (e) {} }
		function refresh() {
			document.getElementById('offCount').textContent = saved.length;
			var shown = 0;
			document.querySelectorAll('.res-item').forEach(function (el) {
				var on = saved.indexOf(el.dataset.id) > -1;
				el.querySelector('.off-toggle').checked = on;
				var hide = only && !on; el.hidden = hide; if (!hide) shown++;
			});
			document.getElementById('offEmpty').hidden = !(only && shown === 0);
		}
		document.querySelectorAll('.off-toggle').forEach(function (t) {
			t.addEventListener('change', function () {
				var id = t.dataset.id, i = saved.indexOf(id);
				if (t.checked && i < 0) saved.push(id); if (!t.checked && i > -1) saved.splice(i, 1);
				persist(); refresh();
				XM.toast(t.checked ? 'Ressource enregistrée pour une lecture hors connexion.' : 'Ressource retirée du hors connexion.', t.checked ? 'success' : 'secondary');
			});
		});
		refresh();
	})();
	</script>
</body>
</html>
