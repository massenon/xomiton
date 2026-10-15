<?php
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/x_complement.php';
require_login();
$pageTitle = 'Lois et codes du Bénin';
$nav = 'ccc';
$lois = x_lois();
$cats = array_values(array_unique(array_column($lois, 4)));
$simple = [
    'loi-2011-26' => 'La loi protège les femmes : frapper, forcer, harceler ou marier de force est puni. Vous pouvez porter plainte, même pour des violences dans le couple.',
    'code-personnes' => 'Mariage, famille, enfants : les deux époux ont les mêmes droits et personne ne peut être marié·e sans son accord.',
    'code-penal' => 'Le Code pénal dit ce qui est interdit et quelles peines sont prévues. Les violences sont plus sévèrement punies si la victime est vulnérable ou mineure.',
    'loi-sante-repro' => 'Vous avez le droit d’être informée, de choisir une contraception et d’être soignée avec respect et confidentialité.',
    'loi-vih' => 'Personne ne peut vous refuser un emploi, un soin ou un service à cause du VIH. Votre statut reste confidentiel.',
    'charte-africaine' => 'Un texte africain qui garantit à chacun·e la dignité, l’égalité et la sécurité. Le protocole de Maputo protège spécialement les droits des femmes.',
    'constitution' => 'La loi la plus importante du pays : tout le monde est égal devant la loi, sans distinction de sexe.',
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
					<nav aria-label="breadcrumb"><ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="ccc.php">Outils CCC</a></li><li class="breadcrumb-item active">Lois et codes du Bénin</li></ol></nav>
					<div class="card card-body">
						<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
							<div><h1 class="h3 mb-0">Lois et codes du Bénin</h1><p class="text-secondary mb-0">Connaître vos droits pour les faire respecter.</p></div>
							<div class="form-check form-switch align-self-start"><input class="form-check-input" type="checkbox" role="switch" id="simple"><label class="form-check-label" for="simple">Lire en version simple</label></div>
						</div>
						<div class="row g-2">
							<div class="col-md-7"><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" type="search" id="q" placeholder="Rechercher (viol, harcèlement, VIH…)" aria-label="Rechercher"></div></div>
							<div class="col-md-5"><select class="form-select" id="cat" aria-label="Catégorie"><option value="">Toutes les catégories</option><?php foreach ($cats as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
						</div>
					</div>

					<div class="accordion" id="laws">
						<?php foreach ($lois as $i => $l): ?>
						<div class="accordion-item law" data-cat="<?= e($l[4]) ?>" data-text="<?= e(mb_strtolower($l[1] . ' ' . $l[2] . ' ' . $l[3] . ' ' . ($simple[$l[0]] ?? ''))) ?>">
							<h2 class="accordion-header">
								<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#l-<?= e($l[0]) ?>">
									<i class="bi bi-bank2 text-primary me-3 fs-5"></i>
									<span class="me-auto"><span class="d-block fw-semibold"><?= e($l[1]) ?></span><span class="d-block small text-secondary"><?= e($l[2]) ?></span></span>
									<span class="badge bg-primary bg-opacity-10 text-primary me-2"><?= e($l[4]) ?></span>
								</button>
							</h2>
							<div id="l-<?= e($l[0]) ?>" class="accordion-collapse collapse" data-bs-parent="#laws">
								<div class="accordion-body">
									<p class="mb-2 txt-legal"><?= e($l[3]) ?></p>
									<p class="mb-2 txt-simple" hidden><i class="bi bi-chat-heart text-success me-1"></i><?= e($simple[$l[0]] ?? $l[3]) ?></p>
									<div class="d-flex flex-wrap gap-2">
										<a class="btn btn-sm btn-danger-soft" href="urgences.php"><i class="bi bi-telephone-fill me-1"></i>Contacts d’urgence</a>
										<a class="btn btn-sm btn-primary-soft" href="annuaire.php"><i class="bi bi-person-lines-fill me-1"></i>Parler à un·e juriste</a>
									</div>
									<p class="small text-secondary mt-3 mb-0">Résumé indicatif sans valeur juridique. Consultez un·e juriste de l’annuaire pour votre situation.</p>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<div class="card card-body text-center text-secondary" id="none" hidden><i class="bi bi-search fs-1"></i>Aucun texte ne correspond à votre recherche.</div>
				</div>
			</div>
		</div>
	</main>
	<?php include("partials/modals.php"); include("partials/footer-scripts.php"); ?>
	<script>
	(function () {
		var q = document.getElementById('q'), cat = document.getElementById('cat'), sw = document.getElementById('simple');
		function filter() {
			var v = q.value.trim().toLowerCase(), c = cat.value, n = 0;
			document.querySelectorAll('.law').forEach(function (el) {
				var ok = (!c || el.dataset.cat === c) && (!v || el.dataset.text.indexOf(v) > -1);
				el.hidden = !ok; if (ok) n++;
			});
			document.getElementById('none').hidden = n > 0;
		}
		function mode() { var s = sw.checked; document.querySelectorAll('.txt-legal').forEach(function (e) { e.hidden = s; }); document.querySelectorAll('.txt-simple').forEach(function (e) { e.hidden = !s; }); try { localStorage.setItem('xm_law_simple', s ? '1' : '0'); } catch (e) {} }
		q.addEventListener('input', filter); cat.addEventListener('change', filter); sw.addEventListener('change', mode);
		try { sw.checked = localStorage.getItem('xm_law_simple') === '1'; } catch (e) {}
		mode(); filter();
	})();
	</script>
</body>
</html>
